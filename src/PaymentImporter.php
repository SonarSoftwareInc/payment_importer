<?php

namespace SonarSoftware\PaymentImporter;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;

class PaymentImporter
{
    /**
     * This function imports payments that are formatted into a standard CSV format.
     * The CSV format should be:
     * Sonar Account ID, Payment Amount, Payment Date/Time in RFC 3339 format (e.g. 2017-06-23T20:39:16+00:00) or empty to use the current date/time, Payment Reference (can be blank/empty).
     * All columns must exist. If you don't want to submit a date and reference, an example of the format would be 1,12.43,,
     * @param $pathToCsv
     * @return array
     */
    public function importPayments($pathToCsv)
    {
        $this->validateFileExists($pathToCsv);
        $this->validateFileContents($pathToCsv);
        return $this->importPaymentsToSonar($pathToCsv);
    }

    /**
     * Import the payments into Sonar
     * @param $pathToCsv
     * @return array
     */
    private function importPaymentsToSonar($pathToCsv)
    {
        $dotenv = new Dotenv(dirname(__FILE__) . "/../");
        $dotenv->required(['SONAR_URL','SONAR_USERNAME','SONAR_PASSWORD']);
        $dotenv->load();

        $failureLog = tempnam(sys_get_temp_dir(),"failures");
        $successLog = tempnam(sys_get_temp_dir(),"successes");
        $failureLogHandle = fopen($failureLog,"w");
        $successLogHandle = fopen($successLog, "w");

        $results = [
            'successes' => 0,
            'failures' => 0,
            'failure_log' => $failureLog,
            'success_log' => $successLog,
        ];

        $mapping = [];

        $requests = function () use ($pathToCsv, $mapping)
        {
            if (($handle = fopen($pathToCsv, "r")) !== FALSE)
            {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
                {
                    $mapping[] = $data;
                    if ($data[2])
                    {
                        $carbon = new Carbon($data[2]);
                        $date = $carbon->toDateString();
                    }
                    else
                    {
                        $date = null;
                    }

                    yield new Request("POST", getenv("SONAR_URL") . "/api/v1/accounts/" . (int)$data[0] . "/transactions/payments", [
                            'Content-Type' => 'application/json; charset=UTF8',
                            'timeout' => 30,
                            'Authorization' => 'Basic ' . base64_encode(getenv("SONAR_USERNAME") . ':' . getenv("SONAR_PASSWORD")),
                        ]
                        , json_encode([
                            'payment_method' => 'other',
                            'reference' => $data[3],
                            'amount' => (float)$data[1],
                            'date' => $date,
                        ]));
                }
            }
        };

        $client = new Client();
        $pool = new Pool($client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) use (&$returnData, $successLogHandle, $failureLogHandle, $mapping)
            {
                $statusCode = $response->getStatusCode();
                if ($statusCode > 201)
                {
                    $body = json_decode($response->getBody()->getContents());
                    $line = $mapping[$index];
                    array_push($line,$body);
                    fputcsv($failureLogHandle,$line);
                    $returnData['failures'] += 1;
                }
                else
                {
                    $returnData['successes'] += 1;
                    fwrite($successLogHandle,"Payment submission succeeded for account ID {$mapping[$index][0]} for {$mapping[$index][1]}" . "\n");
                }
            },
            'rejected' => function($reason, $index) use (&$returnData, $failureLogHandle, $mapping)
            {
                $response = $reason->getResponse();
                if ($response !== null)
                {
                    $body = json_decode($response->getBody()->getContents());
                    $returnMessage = implode(", ",(array)$body->error->message);
                }
                else
                {
                    $returnMessage = "No response from Sonar instance.";
                }
                $line = $mapping[$index];
                array_push($line,$returnMessage);
                fputcsv($failureLogHandle,$line);
                $returnData['failures'] += 1;
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }

    /**
     * Validate that the file is correctly formatted
     * @param $pathToCsv
     */
    private function validateFileContents($pathToCsv)
    {
        $row = 1;
        if (($handle = fopen($pathToCsv, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
            {
                if (count($data) < 4)
                {
                    throw new InvalidArgumentException("Row $row does not have sufficient columns, there are only " . count($data) . " columns, and there should be 4.");
                }
                if (!ctype_digit($data[0]))
                {
                    throw new InvalidArgumentException("The account ID on row $row is not a number.");
                }
                if ($data[0] < 1)
                {
                    throw new InvalidArgumentException("The account ID on row $row must be greater than 0.");
                }
                if (!is_numeric($data[1]))
                {
                    throw new InvalidArgumentException("The payment amount on row $row is not a number.");
                }
                if ($data[1] <= 0)
                {
                    throw new InvalidArgumentException("The payment amount on row $row must be greater than 0.");
                }
                if ($data[2])
                {
                    try {
                        $carbon = new Carbon($data[2]);
                    }
                    catch (Exception $e)
                    {
                        throw new InvalidArgumentException("The date/time value on row $row is not a valid date/time string.");
                    }
                }
                $row++;
            }
            fclose($handle);
        }
    }

    /**
     * Validate that the path to CSV var is a valid file.
     * @param $pathToCsv
     */
    private function validateFileExists($pathToCsv)
    {
        if (!is_file($pathToCsv))
        {
            throw new InvalidArgumentException("$pathToCsv is not a regular file.");
        }
    }
}
<?php

namespace SonarSoftware\PaymentImporter;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Exception;
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

        $results = [
            'successes' => 0,
            'failures' => 0,
            'failure_log' => $failureLog,
            'success_log' => $successLog,
        ];

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
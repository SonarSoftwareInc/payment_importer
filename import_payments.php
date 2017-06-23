<?php
require(__DIR__ . "/vendor/autoload.php");
$importer = new \SonarSoftware\PaymentImporter\PaymentImporter();
try {
    $results = $importer->importPayments("my_csv.csv");
}
catch (Exception $e)
{
    echo "There was an error importing the CSV - {$e->getMessage()}\n";
    return;
}

echo "Import completed. There were {$results['successes']} successful payments entered, and {$results['failures']} failures.\n";
echo "Failure log: {$results['failure_log']}\n";
echo "Success log: {$results['success_log']}\n";
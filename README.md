# Payment Importer

This is a simple, command line tool that can be used to import CSVs of payments into Sonar. This can be used if you have an external system you receive payments in, and you wish to automate the import of payments from that system into Sonar.

## Using this tool

This tool has been tested on Linux, but should work on any platform that is capable of running PHP.

First, copy the `.env.example` file to `.env`. Next, edit the contents of the .env file, replacing the `SONAR_URL`, `SONAR_USERNAME`, and `SONAR_PASSWORD` values with the URL of your Sonar instance, and a username and password for a user that has the
necessary permissions to enter payments into the system.

Next, you must create a small script to utilize this tool. There is an example script in this repository named `import_payments.php` that you can use for reference. If you wish to simply use the example script,
edit it, and replace `my_csv.csv` with the proper path to your import CSV file.

When running the example import script, it will output an error message if the CSV format is incorrect, or the data is bad. If all data passes validation, it will output a count of successful payment entries, a count of failures, and a path to a detailed failure and success log.

## CSV format

The format of the CSV file is as follows:

`Sonar Account ID, Payment Amount, Payment Date/Time in RFC 3339 format (e.g. 2017-06-23T20:39:16+00:00) or empty to use the current date/time, Payment Reference (can be blank/empty).`

For example, a valid line would be:
1,12.93,,"Check Number 12345"

This would import a payment to account ID 1 for 12.93, with a payment reference of `Check Number 12345`.
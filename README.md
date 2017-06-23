# Payment Importer

This is a simple, command line tool that can be used to import CSVs of payments into Sonar. This can be used if you have an external system you receive payments in, and you wish to automate the import of payments from that system into Sonar.

## Using this tool

This tool has been tested on Linux, but should work on any platform that is capable of running PHP.

First, copy the `.env.example` file to `.env`. Next, edit the contents of the .env file, replacing the `SONAR_URL`, `SONAR_USERNAME`, and `SONAR_PASSWORD` values with the URL of your Sonar instance, and a username and password for a user that has the
necessary permissions to enter payments into the system.
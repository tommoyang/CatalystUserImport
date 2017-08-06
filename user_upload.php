<?php
/**
 * Created by PhpStorm.
 * User: thomas
 * Date: 8/4/17
 * Time: 8:31 PM
 */

/**
 * General script options
 *
 * --file [csv file name]       (Required) The name of the CSV users file to be parsed.
 * --create_table               Create a new MYSQL tabe with the name 'users'. Other commands will be ignored.
 * --dry_run                    Used with --file, runs the script without altering the database.
 * --help                       Lists all commands and instructions (what you're looking at right now).
 */
$longopts = array(
    "file:",
    "create_table",
    "dry_run",
    "help",
);

/**
 * MYSQL database log-in details
 *
 * -u       MySQL Username
 * -p       MySQL Password
 * -h       MySQL Hostname
 */
$options = "u::p::h::";

$commands = getopt($options, $longopts);

$driver = new UserUpload($commands);
if ($driver->ready()) {
    $driver->run();
}

class UserUpload {
    private $username;
    private $password;
    private $host;

    private $database;

    private $createTable = false;
    private $file;
    private $dryRun = false;

    private $ready = false;

    function __construct($commands) {
        // --help   Lists all commands and instructions (what you're looking at right now).
        if (empty($commands) || array_key_exists("help", $commands)) {
            echo "
Usage:    php user_upload.php [long options] -u=[MYSQL Username] -p=[MYSQL Password] -h=[MYSQL host]

Long options:
--file [csv file name]      (Required) The name of the CSV users file to be parsed.
--create_table              Create a new MYSQL tabe with the name 'users'. Other commands will be ignored.
--dry_run                   Used with --file, runs the script without altering the database.
--help                      Lists all commands and instructions (what you're looking at right now).

Options: 
-u      MySQL Username
-p      MySQL Password
-h      MySQL Hostname
";

            return;
        }

        // --create_table       Create a new MYSQL tabe with the name 'users'. Other commands will be ignored.
        if (array_key_exists("create_table", $commands)) {
            $this->createTable = true;
        }

        // --dry_run        Used with --file, runs the script without altering the database.
        if (!$this->createTable && array_key_exists("dry_run", $commands)) {
            $this->dryRun = true;
        } else {
            //*
            if (array_key_exists("u", $commands) && $commands["u"]) {
                $this->username = $commands["u"];
            } else {
                echo "Please provide a MYSQL Database Username (-u)\n";
                return;
            }

            if (array_key_exists("p", $commands) && $commands["p"]) {
                $this->password = $commands["p"];
            } else {
                echo "Please provide a MYSQL Database Password (-p)\n";
                return;
            }

            if (array_key_exists("h", $commands) && $commands["h"]) {
                $this->host = $commands["h"];
            } else {
                echo "Please provide a MYSQL Database Hostname (-h)\n";
                return;
            }
            //*/

            $database = UserDatabase::getDriver($this->username, $this->password, $this->host);
            if (is_null($database)) {
                return;
            }
            $this->database = $database;
        }

        // --file [csv file name]       The name of the CSV file to be parsed
        if (array_key_exists("file", $commands) && $commands["file"]) {
            $commands["file"];

            $file = fopen($commands["file"], "r");

            if ($file) {
                $this->file = $file;
            } else {
                echo "File could not be read (are you pointing to the right file and/or does it exist?";
                return;
            }
        }

        $this->ready = true;
    }


    /**
     * Returns a boolean of whether this script is able to run properly
     *
     * @return bool
     */
    public function ready()
    {
        return $this->ready;
    }

    public function run() {
        if ($this->createTable) {
            // TODO: Database commands

        } else {
            $this->readCsv($this->file);
        }
    }

    /**
     * Reads input from the file
     * CSV file input is assumed to be three columns, with columns being "name" "surname" and "email" in that orders
     *
     * @param $file
     */
    private function readCsv($file)
    {
        if (!$this->ready()) return;

        while ($row = fgetcsv($file)) {
            // Exclude column name row
            if ($row[0] == "name" && $row[1] == "surname" && $row[2] == "email") continue;

            $success = self::formatRow($row);

            if (!$success) continue;

            if ($this->dryRun) {
                echo sprintf("Found row: %s\n", self::printableRow($row));
            } else {
                // TODO: Database commands
            }

        }
    }

    /**
     * Formats user row for database input. Names and Surnames will be lowercase with capitalized first letter.
     * Rows with invalid emails will be skipped
     *
     * @param $row
     * @return bool
     */
    private static function formatRow(&$row) {
        $row = array_map("trim", $row);

        if (filter_var($row[2], FILTER_VALIDATE_EMAIL)) {
            $row[0] = Tools::nameFormat($row[0]);
            $row[1] = Tools::nameFormat($row[1]);
            $row[2] = strtolower($row[2]);

            return true;
        } else {
            echo sprintf("Invalid email, skipping: %s\n", self::printableRow($row));
            return false;
        }
    }

    private static function printableRow($row) {
        return sprintf("{'%s', '%s', '%s'}", $row[0], $row[1], $row[2]);
    }
}

class UserDatabase
{
    const DSN_BASE = "mysql:dbname=users;host=";

    private static $driver = null;

    private function __construct($username, $password, $host) {
        try {
            $dsn = self::DSN_BASE . $host;
            $driver = new PDO($dsn, $username, $password);

            return $driver;
        } catch (PDOException $exception) {
            echo "Connection Failed: " . $exception->getMessage() . "\n";
        }

        return null;
    }

    public static function getDriver($username, $password, $host) {
        if (!self::$driver) {
            self::$driver = new UserDatabase($username, $password, $host);
        }

        return self::$driver;
    }
}

class Tools
{
    /**
     * Returns a string with the first character capitalised and the rest lower case
     *
     * @param $input
     * @return string
     */
    public static function nameFormat($input) {
        return ucfirst(strtolower($input));
    }
}
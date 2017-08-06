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
    public function ready() {
        return $this->ready;
    }

    public function run() {
        if ($this->createTable) {
            $exists = $this->database->isUsersExists();
            if ($exists) {
                // Existing database found, confirm overwrite
                $confirmation = readline("Users table already exists, are you sure you want to overwrite? y/n: ");

                if (trim($confirmation) != "y") {
                    echo "No changes have been made, Exiting\n";
                    return;
                }
            }

            $this->database->createTable();
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
    private function readCsv($file) {
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

class UserDatabase {
    const DSN_BASE = "mysql:dbname=catalystUsers;host=";

    private static $driver = null;

    private $db = null;

    private function __construct($username, $password, $host) {
        try {
            $dsn = "mysql:dbname=catalystUsers;host=" . $host;
            $this->db = new PDO($dsn, $username, $password);
        } catch (PDOException $exception) {
            echo "Connection Failed: " . $exception->getMessage() . "\n";
        }
    }

    /**
     * Initializes the PDO database driver if it hasn't been initialized already.
     * Otherwise, returns the current database driver.
     * If an error occurs when initializing the database, a null object is returned.
     *
     * @param $username
     * @param $password
     * @param $host
     * @return null|UserDatabase
     */
    public static function getDriver($username, $password, $host) {
        if (!self::$driver) {
            self::$driver = new UserDatabase($username, $password, $host);
        }

        return self::$driver;
    }

    /**
     * Returns a boolean variable indicating whether the users table already exists.
     *
     * @return bool
     */
    public function isUsersExists() {
        // Check to see if the users table already exists
        $testUsersTableQuery = /** @lang MySQL */
            "SHOW TABLES LIKE 'users'";

        $statement = $this->db->query($testUsersTableQuery);
        $statement->execute();
        $exists = (bool)$statement->fetch();

        return $exists;
    }

    /**
     * Creates a 'users' table. Table has columns id, firstname, surname and email
     *
     * Note that while the design spec has asked for a "name" field, I have chosen to name this
     * "firstname" to avoid using the MySQL reserved keyword
     */
    public function createTable() {
        $dropUsersTableQuery = /** @lang MySQL */
            "DROP TABLE IF EXISTS `users`";
        $this->db->exec($dropUsersTableQuery);

        $createUsersTableQuery = /** @lang MySQL */
            "CREATE TABLE `users`
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    firstname VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL
);
CREATE UNIQUE INDEX users_email_uindex ON `users` (email);";
        $success = $this->db->exec($createUsersTableQuery);

        if ($success === false) {
            echo "An error occurred when creating users table:\n";
            echo "\t" . $this->db->errorInfo()[2] . "\n";
        } else {
            echo "Users table successfully created\n";
        }
    }
}

class Tools {
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
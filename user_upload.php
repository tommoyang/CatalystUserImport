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
 * --create_table              Create a new MYSQL table with the name 'users'. Other commands will be ignored.
 *
 * --file [csv file name]      The name of the CSV users file to be parsed.
 * --dry_run                   Used with --file, runs the script without altering the database.
 * --simple_names              Removes all special characters from names
 * --help                      Lists all commands and instructions.
 */
$longopts = array(
    "file:",
    "create_table",
    "dry_run",
    "simple_names",
    "help",
);

/**
 * MYSQL database log-in details
 *
 * -u       MySQL Username
 * -p       MySQL Password
 * -h       MySQL Hostname
 * -d       MySQL Database Name (defaults to 'catalystUsers')
 */
$options = "u::p::h::";

$commands = getopt($options, $longopts);

$driver = new UserUpload($commands);
if ($driver->ready()) {
    $driver->run();
}

/**
 * A simple script that processes a CSV file and imports to a MySQL database
 * This script expects the CSV file to have three columns: name, surname and email.
 * Users will not be imported if duplicate emails are found, or if emails are malformed
 */
class UserUpload {
    private $username;
    private $password;
    private $host;
    private $databaseName = "catalystUsers";

    private $database;

    private $createTable = false;
    private $file;
    private $dryRun = false;
    private $simpleNames = false;

    private $ready = false;

    function __construct($commands) {
        // --help   Lists all commands and instructions (what you're looking at right now).
        if (empty($commands) || array_key_exists("help", $commands)) {
            echo "
Usage:    php user_upload.php [long options] -u=[MYSQL Username] -p=[MYSQL Password] -h=[MYSQL host]

Long options:
--create_table              Create a new MYSQL table with the name 'users'. Other commands will be ignored.

--file [csv file name]      The name of the CSV users file to be parsed.
--dry_run                   Used with --file, runs the script without altering the database.
--simple_names              Removes all special characters from names
--help                      Lists all commands and instructions (what you're looking at right now).

Options: 
-u      MySQL Username
-p      MySQL Password
-h      MySQL Hostname
-d      MySQL Database Name (defaults to 'catalystUsers')
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

            if (array_key_exists("d", $commands) && $commands["h"]) {
                $this->databaseName = $commands["d"];
            }
            //*/

            $database = UserDatabase::getDriver($this->username, $this->password, $this->host, $this->databaseName);
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
        } else if (!$this->createTable) {
            echo "Please specify a file to import (--file [csv file name])\n";
            return;
        }

        // --simple_names              Removes all special characters from names
        if (array_key_exists("simple_names", $commands)) {
            $this->simpleNames = true;
        }

        $this->ready = true;
    }

    /**
     * Returns a boolean of whether this script is able to run properly
     *
     * @return bool     TRUE if the imports can be safely run
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
     * @param resource $file CSV file to be imported
     */
    private function readCsv($file) {
        if (!$this->dryRun && !$this->database->isUsersExists()) {
            echo "Users table does not exist. Please run this script using the --create_table command";
            return;
        }

        while ($row = fgetcsv($file)) {
            $row = array_map("trim", $row);

            // Exclude column name row
            if (strtolower($row[0]) == "name" && strtolower($row[1]) == "surname" && strtolower($row[2]) == "email") continue;

            $user = new User($row[0], $row[1], $row[2], $this->simpleNames);

            if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                echo sprintf("Invalid email, skipping: %s\n", $user->toString());
                continue;
            }

            $this->database->insertUser($user, $this->dryRun);
        }
    }
}

class User {

    private $name, $surname, $email;

    public function __construct($name, $surname, $email, $simpleNames = false) {
        $this->name = Tools::nameFormat($name);
        $this->surname = Tools::nameFormat($surname);

        if ($simpleNames) {
            $this->name = Tools::alphabeticalOnly($this->name);
            $this->surname = Tools::alphabeticalOnly($this->surname);
        }

        $this->email = strtolower($email);
    }

    /**
     * @return string   Name of the user
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string   Surname of the user
     */
    public function getSurname() {
        return $this->surname;
    }

    /**
     * @return string   Email of the user
     */
    public function getEmail() {
        return $this->email;
    }

    /**
     * Returns a string representation of the user
     *
     * @return string   String representation of the user
     */
    public function toString() {
        return sprintf("{'%s', '%s', '%s'}", $this->name, $this->surname, $this->email);
    }
}

class UserDatabase {
    private static $driver = null;

    private $db = null;

    private function __construct($username, $password, $host, $database) {
        try {
            $dsn = sprintf("mysql:dbname=%s;host=%s", $database, $host);
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
     * @param string $username MySQL Database Username
     * @param string $password MySQL Database Password
     * @param string $host     MySQL Database Hostname
     * @param string $database MySQL Database name
     *
     * @return UserDatabase         A UserDatabase object
     */
    public static function getDriver($username, $password, $host, $database) {
        if (!self::$driver) {
            self::$driver = new UserDatabase($username, $password, $host, $database);
        }

        return self::$driver;
    }

    /**
     * Returns a boolean variable indicating whether the users table already exists.
     *
     * @return bool     TRUE if the users table exists
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
     */
    public function createTable() {
        $this->db->beginTransaction();

        $dropUsersTableQuery = /** @lang MySQL */
            "DROP TABLE IF EXISTS `users`";
        $this->db->exec($dropUsersTableQuery);

        $createUsersTableQuery = /** @lang MySQL */
            "CREATE TABLE `users`
(
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `surname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL
);
CREATE UNIQUE INDEX users_email_uindex ON `users` (email);";
        $success = $this->db->exec($createUsersTableQuery);

        if ($success === false) {
            echo "An error occurred when creating users table:\n";
            echo sprintf("\t%s\n", $this->db->errorInfo()[2]);
            echo "No changes have been made to the database.\n";

            $this->db->rollBack();
        } else {
            echo "Users table successfully created.\n";

            $this->db->commit();
        }
    }

    /**
     * Inserts a user into the database.
     *
     * @param User $user    The user to insert
     * @param bool $dry_run FALSE if values should be input into the database
     */
    public function insertUser($user, $dry_run = false) {
        // Check if email already exists in table
        $testUserExistsQuery = /** @lang MySQL */
            "SELECT u.email FROM users u WHERE u.email = ?";
        $statement = $this->db->prepare($testUserExistsQuery);
        $success = $statement->execute(array($user->getEmail()));

        if ($success === false) {
            echo sprintf("An error occurred when checking for user: %s\n"), $user->toString();
            echo sprintf("\t%s\n", $this->db->errorInfo()[2]);
            echo "No changes have been made.\n";

            return;
        }

        $result = $statement->fetchAll();

        // Email already exists
        if (count($result) > 0) {
            echo sprintf("User already exists, skipping: %s\n", $user->toString());
            return;
        }

        $this->db->beginTransaction();

        $insertUserQuery = /** @lang MySQL */
            "INSERT INTO users (`name`, `surname`, `email`) VALUES (?, ?, ?)";

        $statement = $this->db->prepare($insertUserQuery);
        $success = $statement->execute(array($user->getName(), $user->getSurname(), $user->getEmail()));

        if ($success === false) {
            echo sprintf("User was not added: %s\n", $user->toString());
            echo sprintf("\t%s\n", $this->db->errorInfo()[2]);
            echo "No changes have been made.\n";

            $this->db->rollBack();
            return;
        }

        if ($dry_run) {
            // Dry run, no database commits should be made
            $this->db->rollBack();
        } else {
            $this->db->commit();
        }
    }
}

class Tools {
    /**
     * Returns a string with the first character capitalised and the rest lower case
     *
     * @param string $input String to format
     *
     * @return string   Formatted string
     */
    public static function nameFormat($input) {
        return ucfirst(strtolower($input));
    }

    /**
     * Returns a string with all non-alphabetical characters removed
     *
     * @param string $input String to format
     *
     * @return string   Formatted string
     */
    public static function alphabeticalOnly($input) {
        return preg_replace("/[^A-Za-z]+/", "", $input);
    }
}
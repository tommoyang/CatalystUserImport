# CSV to MYSQL User Import CLI Script

A simple script that processes a CSV file and imports to a MySQL database 

Usage:

`php user_upload.php [long options] -u=[MYSQL Username] -p=[MYSQL Password] -h=[MYSQL host]`

Long options:

`--file [csv file name]      (Required) The name of the CSV users file to be parsed.`
  
`--create_table              Create a new MYSQL tabe with the name 'users'. Other commands will be ignored.`

`--dry_run                   Used with --file, runs the script without altering the database.`

`--help                      Lists all commands and instructions (what you're looking at right now).`

Options:

`-u      MySQL Username`

`-p      MySQL Password`

`-h      MySQL Hostname`
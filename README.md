# Dr Strainlove strain database / server

## Description:
A MySQL database for storing and retrieving information about bacterial strains, with a simple web interface. The database contains two tables: `strains` and `users`.

**Table overview**

| Table | Purpose | Key columns |
| --- | --- | --- |
| `strains` | Stores strain records, one row per strain | `Strain` (INT, auto-increment PK, unique), `Genotype` (TEXT), `Recipient` (INT, nullable), `Donor` (INT, nullable), `Comment` (TEXT), `Signature` (VARCHAR(50), nullable), `Created` (TIMESTAMP, defaults to current time) |
| `users` | Stores application users and roles | `Id` (TINYINT, auto-increment PK), `Username` (VARCHAR(20), unique), `Usertype` (ENUM('User','Superuser','Guest') default 'Guest'), `Password` (VARCHAR(255), defaults to empty string), `Signature` (VARCHAR(20)) |

**Column notes**
- `Strain` is a running number. Each new strain gets the next free number.
- `Genotype` is a string of text describing the strain’s genotype.
- `Recipient` and `Donor` are the parental strains used for construction of the strain.
- `Comment` is a string of text where you can put whatever information you like that does not belong in the genotype. Details for how the strain was constructed, who you got the strain from, references, any important information on how to handle it etc.
- `Signature` is an identifier of whoever saved the strain (i.e. initials, first name etc.)
- `Created` is a timestamp for when the strain was created.
- `Usertype` controls permissions: `Superuser` has full access, `User` can search/add/edit strains, and `Guest` is search-only.

**Get the current schema**
If you already have a database and want to confirm the exact structure before applying changes, this MySQL command will print the create statements for both tables:
```
SHOW CREATE TABLE strains; SHOW CREATE TABLE users;
```

## Licence/Warranty/Support: 
None. You are free to modify the included files in any way you want to suit your needs. If you break it, you fix it. Backup the database regularly to avoid loosing data.

## Known bugs:
?

## Files:
In  strainlove/
- actions.php
- db-example.php
- db.php
- edit.php - contains functions for editing strains in the database
- error.php - just a silly error message. Probably useless.
- export.php - a function for exporting search results as a .csv file
- functions.php
- guidelines.htm
- images
- index.php - generates the web page
- insert.php - contains the functions for adding new strains
- lib/input_sanitize.php - numeric clamping helpers used by actions.php and search.php for strain/ID inputs
- lib/search_filters.php - legacy search SQL helper (currently unused; removing it does not change behavior)
- js
- login.php - A simple user verification system. Probably not very safe.
- misc.html
- phpinfo.php
- popstrains.html
- print.css - generates a clean printout of search results
- print.php
- search.php - contains all the search functions
- search1.php
- signup.php - contains functions to add new users
- variant.css




## Requirements:
- Basic Unix/Linux (MySQL experience or a good MySQL manual will help).
- A running LAMP server (Linux/Apache/MySQL/PHP).
- If you want to change anything in how the web interface display the data (names of fields, prefix to the strain numbers etc.) you need to edit some of the included .php files.

## Installation:
Copy the strain server files to the server’s /var/www directory (if there already are some files there, make a new sub-directory, e.g. /var/www/strains. In this case you may need to edit some paths in some of the actual .php files). The “datalogin.php” file goes to /var/local/. Check that it’s working by browsing to the server’s (IP-) address from a web browser on another computer. If you made a subdirectory, add “/strains” after the server’s address (this may also require you to make some adjustments in some of the .php files). You should see the strain database’s web interface.

Make the new MySQL database, the strain table, the user table and the first user (update the example values before running):
```
mysql -u root -p
CREATE DATABASE strains;
USE strains;

CREATE TABLE `strains` (
  `Strain` int NOT NULL AUTO_INCREMENT,
  `Genotype` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `Recipient` int DEFAULT NULL,
  `Donor` int DEFAULT NULL,
  `Comment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `Signature` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Strain`),
  UNIQUE KEY `Strain` (`Strain`),
  KEY `idx_strains_recipient` (`Recipient`),
  KEY `idx_strains_donor` (`Donor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `users` (
  `Id` tinyint NOT NULL AUTO_INCREMENT,
  `Username` varchar(20) NOT NULL,
  `Usertype` enum('User','Superuser','Guest') NOT NULL DEFAULT 'Guest',
  `Password` varchar(255) NOT NULL DEFAULT '',
  `Signature` varchar(20) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `Username` (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- First superuser with an empty password placeholder
INSERT INTO users (`Username`, `Usertype`, `Password`, `Signature`)
VALUES ('admin', 'Superuser', '', 'admin');
```
Any other users can be added through the web interface. Update the example values (`admin`, signatures) as needed. Note the `Usertype` parameter: a `Superuser` has full access, a `User` can search/add/edit strains, and a `Guest` is limited to searching only.

## Some useful MySQL commands 
(be careful, make a backup of the existing database first):
### Load data from a .csv file 
(first place the correctly formatted .csv file in the MySQL data directory, usually /var/lib/mysql):
```
LOAD DATA INFILE 'strains.csv' INTO TABLE strains FIELDS OPTIONALLY ENCLOSED BY '"' TERMINATED BY ',' LINES TERMINATED BY '\n' IGNORE 1 LINES;
```

### Delete a strain 
(in this case the strain with the number 24033):
```
delete from strains.strains where Strain like '24033';
ALTER TABLE strains AUTO_INCREMENT=1;
```

### Lost your login password? 
I haven’t bothered to make any function for changing the password of an existing user. Use phpMyAdmin (if it’s installed on the server) to change the password, or delete the user and make a new one with the same name. This should work for deleting a user:
```
mysql –u root –p
connect strains
delete from strains.users where Username like ‘Username’;
```
## Make backups. 
I use mysqldump to drop a copy of the database…

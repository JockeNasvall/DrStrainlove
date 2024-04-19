# Dr Strainlove strain database / server

## Description:
A MySQL database for storing and retrieving information about bacterial strains, with a simple web interface. The database contains two tables: `strains` and `users`.

The strain table has seven columns: `Strain`, `Genotype`, `Recipient`, `Donor`, `Comment`, `Signature` and `Created`.
- `Strain` is a running number. Each new strain gets the next free number.
- `Genotype` is a string of text describing the strain’s genotype.
- `Recipient` and `Donor` are the parental strains used for construction of the strain.
- `Comment` is a string of text where you can put whatever information you like that does not belong in the genotype. Details for how the strain was constructed, who you got the strain from, references, any important information on how to handle it etc.
- `Signature` is an identifier of whoever saved the strain (i.e. initials, first name etc.)
- `Created` is a timestamp for when the strain was created.

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

Make the new MySQL database, the strain table, the user table and the first user (update the user information before running)
```
mysql –u root –p
create database strains;
connect strains;
create table strains (`Strain` INT(5) AUTO_INCREMENT UNIQUE KEY NOT NULL PRIMARY KEY,`Genotype` VARCHAR(999) NOT NULL, `Recipient` INT(5), `Donor` INT(5), `Comment` VARCHAR(999), `Signature` VARCHAR(50), `Created` TIMESTAMP DEFAULT NOW());
create table users (`Id` tinyint(3) AUTO_INCREMENT UNIQUE KEY NOT NULL PRIMARY KEY,`Username` VARCHAR(20) NOT NULL, `Usertype` enum('User','Superuser') NOT NULL, `Password` VARCHAR(32) NOT NULL, `Signature` VARCHAR(20) NOT NULL, `FullName` VARCHAR(255) NOT NULL);
connect strains;
INSERT INTO `strains`.`users` (`Id`, `Username`, `Usertype`, `Password`,`Signature`,`FullName`) VALUES ('', '*******', 'Superuser', MD5('*******'),'********','******');
```
Any other users can be added through the web interface. Change the asterisks to your actual user information. Note the `Usertype` parameter. A `Superuser` will have full access to create and edit strains, while a `User` can only search for strains.

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

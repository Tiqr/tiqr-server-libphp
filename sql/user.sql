/*
SQL (mysql) Table for use with the Tiqr_UserStorage pdo

Note: You can combine the user and usersecret tables in one table, by adding a `secret` column to this table.
      See user_combined.sql
*/

CREATE TABLE user (
    id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
    userid VARCHAR (30) NOT NULL UNIQUE,
    displayname VARCHAR (30) NOT NULL,
    loginattempts INTEGER,
    tmpblocktimestamp INTEGER,
    tmpblockattempts INTEGER,
    blocked INTEGER,
    notificationtype VARCHAR(15),
    notificationaddress VARCHAR(256)
);
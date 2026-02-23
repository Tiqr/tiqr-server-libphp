/*
SQL (mysql) Table for use with Tiqr_UserStorage pdo and Tiqr_UserSecretStorage pdo
*/

CREATE TABLE user (
    id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
    userid VARCHAR (30) NOT NULL UNIQUE,
    displayname VARCHAR (30) NOT NULL,
    secret VARCHAR (128),
    loginattempts INTEGER,
    tmpblocktimestamp INTEGER,
    tmpblockattempts INTEGER,
    blocked INTEGER,
    notificationtype VARCHAR(15),
    notificationaddress VARCHAR(256)
);
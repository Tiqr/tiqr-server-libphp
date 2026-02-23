/*
SQL (mysql) Table to use with the Tiqr_UserSecretStorage pdo

Note: You can combine the user and usersecret tables in one table, by adding the `secret` column to the user table.
      See: sql/user_combined.sql
*/

CREATE TABLE IF NOT EXISTS usersecret (
    id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
    userid varchar(30) NOT NULL UNIQUE,
    secret varchar(128)
);
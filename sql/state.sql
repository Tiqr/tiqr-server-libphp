/* Create state table for use with Tiqr_StateStorage pdo */

CREATE TABLE state (
    key varchar(255) PRIMARY KEY,
    expire int,
    value text
);

CREATE INDEX IF NOT EXISTS index_tiqrstate_expire ON state (expire);
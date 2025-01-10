CREATE TABLE tx_univie_pure_cache (
  mkey varchar(128) DEFAULT '' NOT NULL,
  mvalue mediumtext NOT NULL,
  unixtimestamp int(11) DEFAULT '0' NOT NULL
);
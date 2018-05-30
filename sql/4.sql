


CREATE TABLE IF NOT EXISTS email_verification_by_hardbouncecleaner_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tablename VARCHAR(64) NULL,
  columnname VARCHAR(64) NULL,
  PRIMARY KEY (id))
  ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci
;
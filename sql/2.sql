

CREATE TABLE IF NOT EXISTS email_verification_by_hardbouncecleaner_group (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(45) NULL,
  PRIMARY KEY (id))
  ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci
;
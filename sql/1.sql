

CREATE TABLE IF NOT EXISTS email_verification_by_hardbouncecleaner_list (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  role TINYINT(1) NULL,
  free TINYINT(1) NULL,
  disposable TINYINT(1) NULL,
  mx TINYINT(1) NULL,
  risky TINYINT(1) NULL,
  status VARCHAR(45) NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX unique_email (email ASC),
  INDEX index3 (email ASC),
  INDEX index4 (role ASC),
  INDEX index5 (free ASC),
  INDEX index6 (disposable ASC),
  INDEX index7 (risky ASC),
  INDEX index8 (status ASC),
  INDEX index9 (created_at ASC))
  ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci
;


CREATE TABLE IF NOT EXISTS email_verification_by_hardbouncecleaner_list_has_group (
  list_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (list_id, group_id),
  INDEX fk_email_verification_by_hardbouncecleaner_list_has_email_v_idx (group_id ASC),
  INDEX fk_email_verification_by_hardbouncecleaner_list_has_email_v_idx1 (list_id ASC),
  CONSTRAINT fk_email_verification_by_hardbouncecleaner_list_has_email_ver1
  FOREIGN KEY (list_id)
  REFERENCES email_verification_by_hardbouncecleaner_list (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_email_verification_by_hardbouncecleaner_list_has_email_ver2
  FOREIGN KEY (group_id)
  REFERENCES email_verification_by_hardbouncecleaner_group (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
  ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci
;
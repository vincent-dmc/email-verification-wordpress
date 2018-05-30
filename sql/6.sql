 

ALTER TABLE  email_verification_by_hardbouncecleaner_list 
ADD COLUMN pending TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER status,
ADD COLUMN checked TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER pending;

 
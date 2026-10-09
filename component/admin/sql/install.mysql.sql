CREATE TABLE IF NOT EXISTS `#__pungacontactform_limits` (
  `ip_hash` CHAR(64) NOT NULL,
  `hour_bucket` BIGINT NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`ip_hash`, `hour_bucket`),
  KEY `idx_bucket` (`hour_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `#__pungacontactform_messages` (
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `created_at` DATETIME NOT NULL,
 `status` VARCHAR(16) NOT NULL,
 `reason` VARCHAR(32) NOT NULL DEFAULT '',
 `source_kind` VARCHAR(16) NOT NULL,
 `source_id` INT NOT NULL DEFAULT 0,
 `is_guest` TINYINT(1) NOT NULL DEFAULT 1,
 `sender_name` VARCHAR(120) NOT NULL DEFAULT '',
 `sender_email` VARCHAR(254) NOT NULL DEFAULT '',
 `subject` VARCHAR(200) NOT NULL DEFAULT '',
 `body` MEDIUMTEXT NOT NULL,
 `spam_score` INT NOT NULL DEFAULT 0,
 `spam_threshold` INT NOT NULL DEFAULT 0,
 `spam_details` MEDIUMTEXT NOT NULL,
 `archived` TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (`id`), KEY `idx_pcf_created` (`created_at`), KEY `idx_pcf_status` (`status`,`archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `#__pungacontactform_counts` (
 `day` DATE NOT NULL,
 `status` VARCHAR(16) NOT NULL,
 `total` BIGINT NOT NULL DEFAULT 0,
 PRIMARY KEY (`day`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

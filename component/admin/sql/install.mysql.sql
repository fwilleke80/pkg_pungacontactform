CREATE TABLE IF NOT EXISTS `#__pungacontactform_limits` (
  `ip_hash` CHAR(64) NOT NULL,
  `hour_bucket` BIGINT NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`ip_hash`, `hour_bucket`),
  KEY `idx_bucket` (`hour_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

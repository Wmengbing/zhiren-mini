CREATE TABLE IF NOT EXISTS admin_login_limit (
  bucket CHAR(64) NOT NULL PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL,
  reset_at BIGINT UNSIGNED NOT NULL,
  KEY idx_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Other additions are applied idempotently by bin/migrate.php.

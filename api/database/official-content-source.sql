CREATE TABLE IF NOT EXISTS official_content_source (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  resource VARCHAR(40) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  source_key CHAR(64) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  source_url VARCHAR(1000) NOT NULL,
  metadata MEDIUMTEXT NOT NULL,
  imported_at DATETIME NOT NULL,
  UNIQUE KEY uq_resource_source (resource, source_key),
  KEY idx_resource_record (resource, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

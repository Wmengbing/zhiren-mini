CREATE TABLE IF NOT EXISTS admin (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_session (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_id), KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_setting (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(80) NOT NULL UNIQUE,
  `value` TEXT NOT NULL,
  label VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS banner (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  link_type VARCHAR(30) NOT NULL DEFAULT 'none',
  link_value VARCHAR(255) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_page (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  slug VARCHAR(60) NOT NULL UNIQUE,
  summary VARCHAR(500) NOT NULL DEFAULT '',
  content TEXT NOT NULL,
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_category (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(500) NOT NULL DEFAULT '',
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(255) NOT NULL DEFAULT '',
  cover_url VARCHAR(500) NOT NULL DEFAULT '',
  gallery_json TEXT NULL,
  summary TEXT NOT NULL,
  features TEXT NOT NULL,
  specifications TEXT NOT NULL,
  application TEXT NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  is_featured TINYINT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_category_status (category_id, status),
  KEY idx_featured (is_featured, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  summary VARCHAR(500) NOT NULL DEFAULT '',
  cover_url VARCHAR(500) NOT NULL DEFAULT '',
  content TEXT NOT NULL,
  published_at DATETIME NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_status_publish (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inquiry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NULL,
  name VARCHAR(50) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  status TINYINT NOT NULL DEFAULT 0,
  admin_note VARCHAR(1000) NOT NULL DEFAULT '',
  ip_hash CHAR(64) NOT NULL,
  consent_version VARCHAR(40) NOT NULL DEFAULT '',
  consented_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_status_created (status, created_at),
  KEY idx_ip_created (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT INTO site_setting (`key`, `value`, label) VALUES
('company_name', '江西志人不锈钢制品有限公司', '公司名称'),
('hero_title', '精密铸造 · 专注品质', '首页主标题'),
('hero_subtitle', '阀类、管件、五金及各类精密铸造件', '首页副标题'),
('company_summary', '江西志人不锈钢制品有限公司专注金属加工机械制造、有色金属铸造和金属制品销售，提供多类型精密铸造件。', '公司简介摘要'),
('phone', '', '联系电话'),
('email', '', '邮箱'),
('address', '', '公司地址'),
('work_hours', '', '工作时间'),
('latitude', '', '地图纬度'),
('longitude', '', '地图经度'),
('wechat_qr', '', '微信二维码图片地址')
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);

INSERT INTO company_page (title, slug, summary, content, sort, status, created_at, updated_at) VALUES
('公司简介', 'about', '专注精密铸造件制造与金属制品销售', '江西志人不锈钢制品有限公司的核心业务涵盖金属加工机械制造、有色金属铸造和金属制品销售。可生产阀类、管件、建筑五金、船用五金、厨具、医疗器械配件、机械配件等精密铸造件。', 100, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO product_category (name, description, sort, status, created_at, updated_at) VALUES
('阀类', '', 100, 1, NOW(), NOW()), ('管件', '', 90, 1, NOW(), NOW()),
('建筑五金', '', 80, 1, NOW(), NOW()), ('船用五金', '', 70, 1, NOW(), NOW()),
('厨具配件', '', 60, 1, NOW(), NOW()), ('医疗器械配件', '', 50, 1, NOW(), NOW()),
('机械配件', '', 40, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

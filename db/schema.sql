-- TwitchSpawn Rule Builder — database schema
-- Import via Plesk -> Databases -> <dbname> -> phpMyAdmin -> Import

CREATE TABLE IF NOT EXISTS rulesets (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              CHAR(12) NOT NULL,
  edit_secret_hash  VARCHAR(255) NOT NULL,
  mc_profile        ENUM('A','B') NOT NULL,
  mc_version_label  VARCHAR(20) NOT NULL,
  mc_nick           VARCHAR(64) NULL,
  title             VARCHAR(120) NULL,
  rules_json        LONGTEXT NOT NULL,
  raw_tsl           LONGTEXT NOT NULL,
  import_source_tsl LONGTEXT NULL,
  view_count        INT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ruleset_revisions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ruleset_id   BIGINT UNSIGNED NOT NULL,
  rules_json   LONGTEXT NOT NULL,
  raw_tsl      LONGTEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ruleset_id (ruleset_id),
  CONSTRAINT fk_revision_ruleset FOREIGN KEY (ruleset_id) REFERENCES rulesets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lightweight abuse throttle for the no-login save endpoint.
CREATE TABLE IF NOT EXISTS save_throttle (
  ip_hash      CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  save_count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ip_hash, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

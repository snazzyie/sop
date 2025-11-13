-- COMPREHENSIVE SCHEMA FIX MIGRATION
-- This migration aligns the database schema with the refactored code
-- Run AFTER schema.sql and saas_migration.sql

-- ============================================================================
-- 1. ADD MISSING COLUMNS TO USERS TABLE
-- ============================================================================

-- Add user_group for super admin (0-10 permission levels)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS user_group INT DEFAULT 0 AFTER status;

-- Add company_id for multi-tenancy
ALTER TABLE users
ADD COLUMN IF NOT EXISTS company_id INT NULL AFTER user_group,
ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- Add FK for company_id (if not exists)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND CONSTRAINT_NAME = 'fk_users_company');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE SET NULL',
    'SELECT "FK already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 2. RENAME TABLES TO MATCH CODE EXPECTATIONS
-- ============================================================================

-- Rename sessions to recording_sessions
RENAME TABLE sessions TO recording_sessions;

-- Rename steps to sop_steps
RENAME TABLE steps TO sop_steps;

-- Rename shares to sop_shares
RENAME TABLE shares TO sop_shares;

-- Rename exports to sop_exports
RENAME TABLE exports TO sop_exports;

-- ============================================================================
-- 3. FIX SOPS TABLE
-- ============================================================================

-- Add company_id for multi-tenancy
ALTER TABLE sops
ADD COLUMN IF NOT EXISTS company_id INT NULL AFTER sop_id,
ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- Rename user_id to created_by for clarity
ALTER TABLE sops
CHANGE COLUMN user_id created_by INT NOT NULL;

-- Add tags column
ALTER TABLE sops
ADD COLUMN IF NOT EXISTS tags JSON NULL AFTER metadata;

-- Add FK for company_id
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sops'
    AND CONSTRAINT_NAME = 'fk_sops_company');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE sops ADD CONSTRAINT fk_sops_company FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE CASCADE',
    'SELECT "FK already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 4. FIX RECORDING_SESSIONS TABLE
-- ============================================================================

-- Add company_id
ALTER TABLE recording_sessions
ADD COLUMN IF NOT EXISTS company_id INT NULL AFTER user_id,
ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- Add paused status and paused_at
ALTER TABLE recording_sessions
MODIFY COLUMN status ENUM('recording', 'paused', 'processing', 'completed', 'failed') DEFAULT 'recording';

ALTER TABLE recording_sessions
ADD COLUMN IF NOT EXISTS paused_at TIMESTAMP NULL AFTER end_time;

-- Rename start_time to created_at (or add created_at if missing)
-- Since created_at already exists, just ensure start_time is there
ALTER TABLE recording_sessions
MODIFY COLUMN start_time TIMESTAMP NOT NULL;

-- Add duration column
ALTER TABLE recording_sessions
ADD COLUMN IF NOT EXISTS duration INT NULL AFTER total_steps;

-- Add end_url column
ALTER TABLE recording_sessions
ADD COLUMN IF NOT EXISTS end_url TEXT NULL AFTER start_url;

-- Add FK for company_id
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'recording_sessions'
    AND CONSTRAINT_NAME = 'fk_sessions_company');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE recording_sessions ADD CONSTRAINT fk_sessions_company FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE CASCADE',
    'SELECT "FK already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 5. FIX SOP_STEPS TABLE
-- ============================================================================

-- Rename step_number to step_order
ALTER TABLE sop_steps
CHANGE COLUMN step_number step_order INT NOT NULL;

-- Add individual columns from JSON
ALTER TABLE sop_steps
ADD COLUMN IF NOT EXISTS element_selector TEXT NULL AFTER action_type,
ADD COLUMN IF NOT EXISTS element_text TEXT NULL AFTER element_selector,
ADD COLUMN IF NOT EXISTS url TEXT NULL AFTER element_text;

-- Rename screenshot_path to screenshot_url
ALTER TABLE sop_steps
CHANGE COLUMN screenshot_path screenshot_url VARCHAR(500) NULL;

-- Rename ai_description to description
ALTER TABLE sop_steps
CHANGE COLUMN ai_description description TEXT NULL;

-- Keep element_data and page_data for backward compatibility
-- Add metadata column if needed
ALTER TABLE sop_steps
ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER description;

-- ============================================================================
-- 6. FIX SOP_SHARES TABLE
-- ============================================================================

-- Add missing columns
ALTER TABLE sop_shares
ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER sop_id,
ADD COLUMN IF NOT EXISTS allow_comments BOOLEAN DEFAULT FALSE AFTER password_hash,
ADD COLUMN IF NOT EXISTS max_views INT NULL AFTER allow_comments,
ADD COLUMN IF NOT EXISTS last_viewed_at TIMESTAMP NULL AFTER views_count;

-- Rename views_count to view_count
ALTER TABLE sop_shares
CHANGE COLUMN views_count view_count INT DEFAULT 0;

-- Add FK for created_by
ALTER TABLE sop_shares
ADD CONSTRAINT fk_shares_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- ============================================================================
-- 7. FIX SOP_EXPORTS TABLE
-- ============================================================================

-- Rename user_id to exported_by
ALTER TABLE sop_exports
CHANGE COLUMN user_id exported_by INT NULL;

-- Rename created_at to exported_at
ALTER TABLE sop_exports
ADD COLUMN IF NOT EXISTS exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER file_size;

-- ============================================================================
-- 8. FIX API_KEYS TABLE
-- ============================================================================

-- Rename key_name to name
ALTER TABLE api_keys
CHANGE COLUMN key_name name VARCHAR(255) NOT NULL;

-- Rename api_secret to api_secret_hash (will need to rehash existing keys)
ALTER TABLE api_keys
CHANGE COLUMN api_secret api_secret_hash VARCHAR(128) NOT NULL;

-- Rename is_active to status
ALTER TABLE api_keys
ADD COLUMN IF NOT EXISTS status ENUM('active', 'revoked') DEFAULT 'active' AFTER api_secret_hash;

-- Drop is_active after migrating data
UPDATE api_keys SET status = IF(is_active = 1, 'active', 'revoked');
ALTER TABLE api_keys DROP COLUMN IF EXISTS is_active;

-- Make user_id nullable (team owner can create for team)
ALTER TABLE api_keys
MODIFY COLUMN user_id INT NULL;

-- ============================================================================
-- 9. CREATE SESSION_ACTIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS session_actions (
  action_id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(36) NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  element_selector TEXT NULL,
  element_text TEXT NULL,
  input_value TEXT NULL,
  url TEXT NULL,
  screenshot_url VARCHAR(500) NULL,
  timestamp BIGINT NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES recording_sessions(session_id) ON DELETE CASCADE,
  INDEX idx_session_id (session_id),
  INDEX idx_action_type (action_type),
  INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. CREATE SETTINGS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS settings (
  setting_id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  setting_type ENUM('string', 'int', 'float', 'bool', 'json', 'array') DEFAULT 'string',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. CREATE TEAM_INVITATIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS team_invitations (
  invitation_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  invited_by INT NOT NULL,
  role ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'editor',
  invitation_token VARCHAR(64) UNIQUE NOT NULL,
  status ENUM('pending', 'accepted', 'expired', 'revoked') DEFAULT 'pending',
  expires_at TIMESTAMP NOT NULL,
  accepted_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_token (invitation_token),
  INDEX idx_email (email),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. FIX USAGE_TRACKING TABLE
-- ============================================================================

-- Modify to match code expectations
ALTER TABLE usage_tracking
DROP COLUMN IF EXISTS period_start,
DROP COLUMN IF EXISTS period_end;

ALTER TABLE usage_tracking
ADD COLUMN IF NOT EXISTS usage_value INT DEFAULT 1 AFTER metric_name,
ADD COLUMN IF NOT EXISTS tracked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER usage_value;

-- Rename metric_value to usage_value if needed
ALTER TABLE usage_tracking
CHANGE COLUMN metric_value usage_value INT NOT NULL DEFAULT 1;

-- Add index for date queries
ALTER TABLE usage_tracking
ADD INDEX IF NOT EXISTS idx_tracked_at (tracked_at);

-- ============================================================================
-- 13. UPDATE FOREIGN KEYS
-- ============================================================================

-- Update sop_steps foreign keys to use new table name
-- Drop old FK
ALTER TABLE sop_steps DROP FOREIGN KEY IF EXISTS steps_ibfk_1;
ALTER TABLE sop_steps DROP FOREIGN KEY IF EXISTS steps_ibfk_2;

-- Add new FKs
ALTER TABLE sop_steps
ADD CONSTRAINT fk_steps_sop FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_steps_session FOREIGN KEY (session_id) REFERENCES recording_sessions(session_id) ON DELETE CASCADE;

-- Update comments FK
ALTER TABLE comments DROP FOREIGN KEY IF EXISTS comments_ibfk_2;
ALTER TABLE comments
ADD CONSTRAINT fk_comments_step FOREIGN KEY (step_id) REFERENCES sop_steps(step_id) ON DELETE CASCADE;

-- ============================================================================
-- 14. UPDATE EXISTING DATA
-- ============================================================================

-- Set company_id for existing users based on team ownership
UPDATE users u
JOIN teams t ON t.owner_user_id = u.user_id
SET u.company_id = t.team_id
WHERE u.company_id IS NULL;

-- Set company_id for existing SOPs based on creator
UPDATE sops s
JOIN users u ON s.created_by = u.user_id
SET s.company_id = u.company_id
WHERE s.company_id IS NULL AND u.company_id IS NOT NULL;

-- Set company_id for existing recording_sessions
UPDATE recording_sessions rs
JOIN users u ON rs.user_id = u.user_id
SET rs.company_id = u.company_id
WHERE rs.company_id IS NULL AND u.company_id IS NOT NULL;

-- Set created_by for shares (if we can infer from SOP)
UPDATE sop_shares sh
JOIN sops s ON sh.sop_id = s.sop_id
SET sh.created_by = s.created_by
WHERE sh.created_by IS NULL;

-- ============================================================================
-- 15. INSERT DEFAULT SETTINGS
-- ============================================================================

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('app_name', 'SOP Recorder', 'string'),
('app_version', '1.0.0', 'string'),
('max_sops_per_team', '100', 'int'),
('max_steps_per_sop', '50', 'int'),
('screenshot_max_size', '5242880', 'int'),
('enable_oauth', '1', 'bool'),
('enable_api', '1', 'bool'),
('maintenance_mode', '0', 'bool');

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

-- ============================================================================
-- COMPLETE DATABASE INITIALIZATION SCRIPT FOR SOP RECORDER
-- Run this script on a fresh database to set up everything
-- ============================================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS sop_recorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sop_recorder;

-- ============================================================================
-- STEP 1: CREATE CORE TABLES
-- ============================================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  name VARCHAR(255),
  oauth_provider VARCHAR(50) NULL,
  oauth_id VARCHAR(255) NULL,
  avatar_url TEXT NULL,
  user_group INT DEFAULT 0,
  company_id INT NULL,
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  owner_user_id INT NOT NULL,
  stripe_customer_id VARCHAR(255) NULL,
  billing_email VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_owner (owner_user_id),
  INDEX idx_stripe_customer (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK for users.company_id
ALTER TABLE users
ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE SET NULL;

-- Recording sessions table
CREATE TABLE IF NOT EXISTS recording_sessions (
  session_id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  company_id INT NULL,
  status ENUM('recording', 'paused', 'processing', 'completed', 'failed') DEFAULT 'recording',
  start_time TIMESTAMP NOT NULL,
  end_time TIMESTAMP NULL,
  paused_at TIMESTAMP NULL,
  recording_options JSON,
  sop_id INT NULL,
  start_url TEXT,
  start_title VARCHAR(500),
  end_url TEXT,
  total_steps INT DEFAULT 0,
  duration INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_company_id (company_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOPs table
CREATE TABLE IF NOT EXISTS sops (
  sop_id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NULL,
  created_by INT NOT NULL,
  session_id VARCHAR(36),
  title VARCHAR(500) NOT NULL,
  description TEXT,
  status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  branding JSON,
  metadata JSON,
  tags JSON,
  total_steps INT DEFAULT 0,
  views_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES recording_sessions(session_id) ON DELETE SET NULL,
  FOREIGN KEY (company_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  INDEX idx_created_by (created_by),
  INDEX idx_company_id (company_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update recording_sessions FK for sop_id
ALTER TABLE recording_sessions
ADD FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE SET NULL;

-- SOP Steps table
CREATE TABLE IF NOT EXISTS sop_steps (
  step_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT,
  session_id VARCHAR(36) NOT NULL,
  step_order INT NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  element_selector TEXT NULL,
  element_text TEXT NULL,
  url TEXT NULL,
  element_data JSON,
  page_data JSON,
  screenshot_url VARCHAR(500),
  thumbnail_path VARCHAR(500),
  description TEXT,
  action_verb VARCHAR(50),
  tips TEXT,
  metadata JSON,
  timestamp BIGINT,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES recording_sessions(session_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_session_id (session_id),
  INDEX idx_step_order (step_order),
  INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Actions table (for individual recording actions)
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

-- SOP Shares table
CREATE TABLE IF NOT EXISTS sop_shares (
  share_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  created_by INT NULL,
  share_token VARCHAR(64) UNIQUE NOT NULL,
  visibility ENUM('public', 'password', 'link-only') DEFAULT 'link-only',
  password_hash VARCHAR(255) NULL,
  allow_comments BOOLEAN DEFAULT FALSE,
  max_views INT NULL,
  view_count INT DEFAULT 0,
  last_viewed_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_share_token (share_token),
  INDEX idx_sop_id (sop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOP Exports table
CREATE TABLE IF NOT EXISTS sop_exports (
  export_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  exported_by INT NULL,
  format ENUM('pdf', 'html', 'markdown', 'json') NOT NULL,
  file_path VARCHAR(500),
  file_size INT,
  exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (exported_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_sop_id (sop_id),
  INDEX idx_exported_by (exported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team members table
CREATE TABLE IF NOT EXISTS team_members (
  team_member_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('owner', 'admin', 'editor', 'viewer') NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY unique_team_user (team_id, user_id),
  INDEX idx_team_id (team_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Invitations table
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

-- SOP permissions table
CREATE TABLE IF NOT EXISTS sop_permissions (
  permission_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  team_id INT NULL,
  user_id INT NULL,
  permission_level ENUM('view', 'edit', 'admin') NOT NULL,
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_team_id (team_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOP revisions table
CREATE TABLE IF NOT EXISTS sop_revisions (
  revision_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  user_id INT NOT NULL,
  changes JSON,
  revision_note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments table
CREATE TABLE IF NOT EXISTS comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  step_id INT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (step_id) REFERENCES sop_steps(step_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_step_id (step_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOP analytics table
CREATE TABLE IF NOT EXISTS sop_analytics (
  view_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  user_id INT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  time_spent INT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  referrer TEXT,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_sop_id (sop_id),
  INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 2: CREATE SAAS TABLES
-- ============================================================================

-- Subscription Plans
CREATE TABLE IF NOT EXISTS subscription_plans (
  plan_id INT AUTO_INCREMENT PRIMARY KEY,
  plan_name VARCHAR(100) NOT NULL UNIQUE,
  plan_slug VARCHAR(50) NOT NULL UNIQUE,
  description TEXT,
  price_monthly DECIMAL(10,2) NOT NULL,
  price_yearly DECIMAL(10,2) NOT NULL,
  stripe_price_id_monthly VARCHAR(255),
  stripe_price_id_yearly VARCHAR(255),
  features JSON,
  limits JSON,
  is_active BOOLEAN DEFAULT TRUE,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug (plan_slug),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Subscriptions
CREATE TABLE IF NOT EXISTS team_subscriptions (
  subscription_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  plan_id INT NOT NULL,
  stripe_subscription_id VARCHAR(255) UNIQUE,
  stripe_customer_id VARCHAR(255),
  status ENUM('active', 'trialing', 'past_due', 'canceled', 'incomplete', 'incomplete_expired', 'unpaid') DEFAULT 'active',
  billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
  current_period_start TIMESTAMP NULL,
  current_period_end TIMESTAMP NULL,
  trial_end TIMESTAMP NULL,
  cancel_at_period_end BOOLEAN DEFAULT FALSE,
  canceled_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id),
  INDEX idx_team_id (team_id),
  INDEX idx_status (status),
  INDEX idx_stripe_subscription (stripe_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment History
CREATE TABLE IF NOT EXISTS payment_history (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  subscription_id INT,
  stripe_payment_intent_id VARCHAR(255),
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  status ENUM('succeeded', 'pending', 'failed', 'refunded') DEFAULT 'pending',
  description TEXT,
  receipt_url TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (subscription_id) REFERENCES team_subscriptions(subscription_id) ON DELETE SET NULL,
  INDEX idx_team_id (team_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth Providers
CREATE TABLE IF NOT EXISTS oauth_providers (
  oauth_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider ENUM('google', 'github', 'microsoft') NOT NULL,
  provider_user_id VARCHAR(255) NOT NULL,
  provider_email VARCHAR(255),
  provider_name VARCHAR(255),
  provider_avatar TEXT,
  access_token TEXT,
  refresh_token TEXT,
  token_expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY unique_provider_user (provider, provider_user_id),
  INDEX idx_user_id (user_id),
  INDEX idx_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Keys for External Access
CREATE TABLE IF NOT EXISTS api_keys (
  api_key_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  user_id INT NULL,
  name VARCHAR(255) NOT NULL,
  api_key VARCHAR(64) UNIQUE NOT NULL,
  api_secret_hash VARCHAR(128) NOT NULL,
  status ENUM('active', 'revoked') DEFAULT 'active',
  permissions JSON,
  last_used_at TIMESTAMP NULL,
  last_used_ip VARCHAR(45),
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_api_key (api_key),
  INDEX idx_team_id (team_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usage Tracking
CREATE TABLE IF NOT EXISTS usage_tracking (
  usage_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  metric_name VARCHAR(100) NOT NULL,
  usage_value INT NOT NULL DEFAULT 1,
  tracked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  INDEX idx_team_id (team_id),
  INDEX idx_tracked_at (tracked_at),
  INDEX idx_metric (metric_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe Webhook Events Log
CREATE TABLE IF NOT EXISTS stripe_webhook_events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  stripe_event_id VARCHAR(255) UNIQUE NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload JSON NOT NULL,
  processed BOOLEAN DEFAULT FALSE,
  processed_at TIMESTAMP NULL,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_processed (processed),
  INDEX idx_stripe_event_id (stripe_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
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
-- STEP 3: INSERT DEFAULT DATA
-- ============================================================================

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, plan_slug, description, price_monthly, price_yearly, features, limits, sort_order) VALUES
(
  'Free',
  'free',
  'Perfect for individuals getting started',
  0.00,
  0.00,
  JSON_ARRAY('10 SOPs', 'Basic sharing', 'Export to HTML/Markdown', 'Community support'),
  JSON_OBJECT('max_sops', 10, 'max_steps_per_sop', 50, 'max_team_members', 1, 'max_api_keys', 0, 'api_access', FALSE),
  1
),
(
  'Pro',
  'pro',
  'For professionals and small teams',
  29.00,
  290.00,
  JSON_ARRAY('Unlimited SOPs', 'Advanced sharing', 'Export to PDF', 'Priority support', 'Custom branding', 'API access', 'Team collaboration (up to 10)', 'Version history'),
  JSON_OBJECT('max_sops', -1, 'max_steps_per_sop', -1, 'max_team_members', 10, 'max_api_keys', 5, 'api_access', TRUE),
  2
),
(
  'Enterprise',
  'enterprise',
  'For large teams and organizations',
  99.00,
  990.00,
  JSON_ARRAY('Everything in Pro', 'Unlimited team members', 'Advanced analytics', 'SSO integration', 'Priority support', 'Custom integrations', 'Dedicated account manager', 'SLA guarantee', 'Advanced security'),
  JSON_OBJECT('max_sops', -1, 'max_steps_per_sop', -1, 'max_team_members', -1, 'max_api_keys', -1, 'api_access', TRUE),
  3
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('app_name', 'SOP Recorder', 'string'),
('app_version', '1.0.0', 'string'),
('max_upload_size', '10485760', 'int'),
('screenshot_max_size', '5242880', 'int'),
('enable_oauth', '1', 'bool'),
('enable_api', '1', 'bool'),
('maintenance_mode', '0', 'bool'),
('allow_registration', '1', 'bool');

-- Insert test user (password: 'password123')
INSERT INTO users (email, password_hash, name, status, user_group) VALUES
('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'active', 10),
('test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', 'active', 0);

-- Create teams for test users
INSERT INTO teams (name, owner_user_id)
VALUES
('Admin Team', (SELECT user_id FROM users WHERE email = 'admin@example.com' LIMIT 1)),
('Test Team', (SELECT user_id FROM users WHERE email = 'test@example.com' LIMIT 1));

-- Link users to their teams
UPDATE users SET company_id = (SELECT team_id FROM teams WHERE owner_user_id = users.user_id LIMIT 1);

-- Assign free plan to test teams
INSERT INTO team_subscriptions (team_id, plan_id, status)
SELECT t.team_id, p.plan_id, 'active'
FROM teams t
CROSS JOIN subscription_plans p
WHERE p.plan_slug = 'free';

-- ============================================================================
-- INITIALIZATION COMPLETE
-- ============================================================================

SELECT 'Database initialization complete!' AS status;
SELECT COUNT(*) AS user_count FROM users;
SELECT COUNT(*) AS team_count FROM teams;
SELECT COUNT(*) AS plan_count FROM subscription_plans;

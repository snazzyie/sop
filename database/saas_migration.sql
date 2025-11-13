-- SaaS Enhancement: Subscription Plans, Payments, OAuth, API Keys

-- Subscription Plans
CREATE TABLE subscription_plans (
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
CREATE TABLE team_subscriptions (
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
CREATE TABLE payment_history (
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
CREATE TABLE oauth_providers (
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
CREATE TABLE api_keys (
  api_key_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  key_name VARCHAR(255) NOT NULL,
  api_key VARCHAR(64) UNIQUE NOT NULL,
  api_secret VARCHAR(128) NOT NULL,
  permissions JSON,
  is_active BOOLEAN DEFAULT TRUE,
  last_used_at TIMESTAMP NULL,
  last_used_ip VARCHAR(45),
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_api_key (api_key),
  INDEX idx_team_id (team_id),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usage Tracking
CREATE TABLE usage_tracking (
  usage_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  metric_name VARCHAR(100) NOT NULL,
  metric_value INT NOT NULL DEFAULT 1,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
  UNIQUE KEY unique_team_metric_period (team_id, metric_name, period_start),
  INDEX idx_team_id (team_id),
  INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe Webhook Events Log
CREATE TABLE stripe_webhook_events (
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

-- Update users table to support OAuth
ALTER TABLE users
  ADD COLUMN oauth_provider VARCHAR(50) NULL AFTER password_hash,
  ADD COLUMN oauth_id VARCHAR(255) NULL AFTER oauth_provider,
  ADD COLUMN avatar_url TEXT NULL AFTER name,
  MODIFY COLUMN password_hash VARCHAR(255) NULL;

-- Update teams table to support subscriptions
ALTER TABLE teams
  ADD COLUMN stripe_customer_id VARCHAR(255) NULL AFTER owner_user_id,
  ADD COLUMN billing_email VARCHAR(255) NULL AFTER stripe_customer_id,
  ADD INDEX idx_stripe_customer (stripe_customer_id);

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, plan_slug, description, price_monthly, price_yearly, features, limits, sort_order) VALUES
(
  'Free',
  'free',
  'Perfect for individuals getting started',
  0.00,
  0.00,
  JSON_ARRAY('5 SOPs per month', 'Basic sharing', 'Export to HTML/Markdown', 'Community support'),
  JSON_OBJECT('sops_per_month', 5, 'steps_per_sop', 50, 'team_members', 1, 'api_calls_per_day', 100, 'storage_mb', 100),
  1
),
(
  'Pro',
  'pro',
  'For professionals and small teams',
  29.00,
  290.00,
  JSON_ARRAY('Unlimited SOPs', 'Advanced sharing', 'Export to PDF', 'Priority support', 'Custom branding', 'API access', 'Team collaboration (up to 10)', 'Version history'),
  JSON_OBJECT('sops_per_month', -1, 'steps_per_sop', -1, 'team_members', 10, 'api_calls_per_day', 10000, 'storage_mb', 10000),
  2
),
(
  'Enterprise',
  'enterprise',
  'For large teams and organizations',
  99.00,
  990.00,
  JSON_ARRAY('Everything in Pro', 'Unlimited team members', 'Advanced analytics', 'SSO integration', 'Priority support', 'Custom integrations', 'Dedicated account manager', 'SLA guarantee', 'Advanced security'),
  JSON_OBJECT('sops_per_month', -1, 'steps_per_sop', -1, 'team_members', -1, 'api_calls_per_day', -1, 'storage_mb', -1),
  3
);

-- Create a default "Personal" team for existing test user
INSERT INTO teams (name, owner_user_id)
SELECT CONCAT(name, '''s Team'), user_id
FROM users
WHERE email = 'test@example.com';

-- Assign free plan to the test user's team
INSERT INTO team_subscriptions (team_id, plan_id, status)
SELECT t.team_id, p.plan_id, 'active'
FROM teams t
CROSS JOIN subscription_plans p
WHERE p.plan_slug = 'free'
AND t.owner_user_id = (SELECT user_id FROM users WHERE email = 'test@example.com' LIMIT 1)
LIMIT 1;

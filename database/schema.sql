-- SOP Recorder Database Schema

-- Drop existing tables if they exist
DROP TABLE IF EXISTS sop_analytics;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS sop_revisions;
DROP TABLE IF EXISTS sop_permissions;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS exports;
DROP TABLE IF EXISTS shares;
DROP TABLE IF EXISTS steps;
DROP TABLE IF EXISTS sops;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  INDEX idx_email (email),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recording sessions table
CREATE TABLE sessions (
  session_id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('recording', 'processing', 'completed', 'failed') DEFAULT 'recording',
  start_time TIMESTAMP NOT NULL,
  end_time TIMESTAMP NULL,
  recording_options JSON,
  sop_id INT NULL,
  start_url TEXT,
  start_title VARCHAR(500),
  total_steps INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOPs table
CREATE TABLE sops (
  sop_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(36),
  title VARCHAR(500) NOT NULL,
  description TEXT,
  status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  branding JSON,
  metadata JSON,
  total_steps INT DEFAULT 0,
  views_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Steps table
CREATE TABLE steps (
  step_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT,
  session_id VARCHAR(36) NOT NULL,
  step_number INT NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  element_data JSON,
  page_data JSON,
  screenshot_path VARCHAR(500),
  thumbnail_path VARCHAR(500),
  ai_description TEXT,
  action_verb VARCHAR(50),
  tips TEXT,
  timestamp BIGINT,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_session_id (session_id),
  INDEX idx_step_number (step_number),
  INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shares table
CREATE TABLE shares (
  share_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  share_token VARCHAR(64) UNIQUE NOT NULL,
  visibility ENUM('public', 'password', 'link-only') DEFAULT 'link-only',
  password_hash VARCHAR(255) NULL,
  expires_at TIMESTAMP NULL,
  views_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  INDEX idx_share_token (share_token),
  INDEX idx_sop_id (sop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exports table
CREATE TABLE exports (
  export_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  user_id INT NOT NULL,
  format ENUM('pdf', 'html', 'markdown', 'json') NOT NULL,
  file_path VARCHAR(500),
  file_size INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams table
CREATE TABLE teams (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  owner_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team members table
CREATE TABLE team_members (
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

-- SOP permissions table
CREATE TABLE sop_permissions (
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
CREATE TABLE sop_revisions (
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
CREATE TABLE comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  sop_id INT NOT NULL,
  step_id INT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sop_id) REFERENCES sops(sop_id) ON DELETE CASCADE,
  FOREIGN KEY (step_id) REFERENCES steps(step_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sop_id (sop_id),
  INDEX idx_step_id (step_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SOP analytics table
CREATE TABLE sop_analytics (
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

-- Insert a default test user (password: 'password123')
INSERT INTO users (email, password_hash, name, status) VALUES
('test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', 'active');

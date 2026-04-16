-- ConnectIn Database Schema
-- Run this on PlanetScale MySQL

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NULL,
  firebase_uid VARCHAR(255) NULL,
  account_type ENUM('normal','organization') NULL,
  login_type ENUM('email','google','apple') DEFAULT 'email',
  device_token VARCHAR(255) NULL,
  fcm_token VARCHAR(512) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auth_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  device_name VARCHAR(100) DEFAULT 'Flutter',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  profile_image VARCHAR(500) NULL,
  profile_banner VARCHAR(500) NULL,
  headline VARCHAR(255) NULL,
  location VARCHAR(255) NULL,
  gender VARCHAR(32) NULL,
  about TEXT NULL,
  contact_no VARCHAR(20) NULL,
  website VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS work_experience (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  org_user_id INT NULL,
  org_name VARCHAR(255) NOT NULL,
  position VARCHAR(255) NULL,
  org_type VARCHAR(100) NULL,
  start_date VARCHAR(50) NULL,
  end_date VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS education (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  org_name VARCHAR(255) NOT NULL,
  degree VARCHAR(255) NULL,
  pass_year VARCHAR(10) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS skills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  skill_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  image VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_like (user_id, post_id),
  INDEX idx_post_id (post_id)
);

CREATE TABLE IF NOT EXISTS post_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_id (post_id)
);

CREATE TABLE IF NOT EXISTS stories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  text_content TEXT NULL,
  image_url VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS story_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  story_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_view (story_id, user_id),
  INDEX idx_story (story_id)
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  actor_id INT NOT NULL,
  target_id INT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id, is_read, created_at),
  INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  company VARCHAR(255) NOT NULL,
  location VARCHAR(255) NULL,
  job_type VARCHAR(50) DEFAULT 'Full-time',
  salary_min INT NULL,
  salary_max INT NULL,
  description TEXT NULL,
  skills TEXT NULL,
  is_remote TINYINT(1) DEFAULT 0,
  is_easy_apply TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS job_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  user_id INT NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  cover_letter TEXT NULL,
  resume_url VARCHAR(500) NULL,
  salary_expectation VARCHAR(100) NULL,
  status ENUM('pending','reviewed','shortlisted','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_app (job_id, user_id),
  INDEX idx_job (job_id)
);

CREATE TABLE IF NOT EXISTS saved_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  job_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_save (user_id, job_id)
);

CREATE TABLE IF NOT EXISTS connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  status ENUM('pending','accepted','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender (sender_id),
  INDEX idx_receiver (receiver_id),
  UNIQUE KEY unique_connection (sender_id, receiver_id)
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  message TEXT NOT NULL,
  media_url VARCHAR(500) NULL,
  read_at TIMESTAMP NULL,
  is_deleted TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender (sender_id),
  INDEX idx_receiver (receiver_id),
  INDEX idx_created_at (created_at),
  INDEX idx_conversation (sender_id, receiver_id, created_at)
);

-- Block system
CREATE TABLE IF NOT EXISTS blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  blocker_id INT NOT NULL,
  blocked_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_block (blocker_id, blocked_id),
  INDEX idx_blocker (blocker_id),
  INDEX idx_blocked (blocked_id)
);

-- Report system
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  reported_id INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('pending','reviewed','resolved') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reported (reported_id),
  INDEX idx_status (status)
);

-- App static/dynamic HTML content (terms/about/help)
CREATE TABLE IF NOT EXISTS app_contents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  content_key VARCHAR(64) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  html_content MEDIUMTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User feedback submitted from app
CREATE TABLE IF NOT EXISTS feedbacks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(255) NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','resolved','rejected') DEFAULT 'new',
  admin_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_status (status)
);

-- Save posts
CREATE TABLE IF NOT EXISTS saved_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_save (user_id, post_id),
  INDEX idx_user (user_id)
);

-- Share posts
CREATE TABLE IF NOT EXISTS post_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post (post_id)
);

-- Master table for university/college/institute suggestions
CREATE TABLE IF NOT EXISTS institutions_master (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  type ENUM('university','college','institute','school','other') DEFAULT 'other',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_type (type)
);

-- 8.5 DATABASE STRUCTURE
-- 8.5.1 reunite.sql

-- Database: reunite

-- Table: campuses
CREATE TABLE campuses (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  city VARCHAR(100) DEFAULT '',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: admins
CREATE TABLE admins (
  admin_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('master_admin', 'campus_admin', 'superadmin', 'admin', 'moderator') NOT NULL DEFAULT 'campus_admin',
  campus_id INT(11) DEFAULT NULL,
  status ENUM('active', 'suspended') DEFAULT 'active',
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL
);

-- Table: users
CREATE TABLE users (
  user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campus_id INT(11) DEFAULT NULL,
  full_name VARCHAR(100) NOT NULL,
  pin VARCHAR(50) DEFAULT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(100) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  trust_score INT(11) DEFAULT 100,
  role ENUM('user', 'admin') DEFAULT 'user',
  status ENUM('active', 'blocked') DEFAULT 'active',
  claim_ban_until DATETIME DEFAULT NULL,
  dob VARCHAR(10) DEFAULT NULL,
  college VARCHAR(50) DEFAULT NULL,
  branch VARCHAR(5) DEFAULT NULL,
  email_verified TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL
);

-- Table: digital_dna
CREATE TABLE digital_dna (
  dna_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('LOST', 'FOUND') NOT NULL,
  report_id INT(11) NOT NULL,
  category VARCHAR(50) DEFAULT NULL,
  shape VARCHAR(50) DEFAULT NULL,
  encrypted_dna LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: lost_reports
CREATE TABLE lost_reports (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  campus_id INT(11) DEFAULT NULL,
  category VARCHAR(50) NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  date_lost DATE DEFAULT NULL,
  lost_time TIME DEFAULT NULL,
  location VARCHAR(100) DEFAULT NULL,
  dna_id INT(11) DEFAULT NULL,
  status ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL
);

-- Table: found_reports
CREATE TABLE found_reports (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  campus_id INT(11) DEFAULT NULL,
  category VARCHAR(50) NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  privacy_thumbnail_path VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  date_found DATE DEFAULT NULL,
  found_time TIME DEFAULT NULL,
  location VARCHAR(100) DEFAULT NULL,
  dna_id INT(11) DEFAULT NULL,
  status ENUM('active', 'matched', 'claimed', 'closed') DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL
);

-- Table: matches
CREATE TABLE matches (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lost_report_id INT(11) NOT NULL,
  found_report_id INT(11) NOT NULL,
  similarity_score DECIMAL(5,2) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  return_type ENUM('admin_office', 'hand_on_hand') DEFAULT 'hand_on_hand',
  meet_location VARCHAR(255) DEFAULT NULL,
  meet_time VARCHAR(100) DEFAULT NULL,
  otp VARCHAR(6) DEFAULT NULL,
  otp_expires_at DATETIME DEFAULT NULL,
  proof_image VARCHAR(255) DEFAULT NULL,
  claim_details TEXT DEFAULT NULL,
  claim_submitted_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lost_report_id) REFERENCES lost_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (found_report_id) REFERENCES found_reports(id) ON DELETE CASCADE
);

-- Table: recovery_cases
CREATE TABLE recovery_cases (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_id INT(11) DEFAULT NULL UNIQUE,
  return_method ENUM('hand_on_hand', 'admin_office') NOT NULL DEFAULT 'hand_on_hand',
  meeting_location VARCHAR(255) DEFAULT NULL,
  meeting_time DATETIME DEFAULT NULL,
  otp_hash VARCHAR(64) DEFAULT NULL,
  otp_expires_at DATETIME DEFAULT NULL,
  otp_attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
  otp_last_generated DATETIME DEFAULT NULL,
  otp_regen_count TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
  claimant_id INT(11) NOT NULL,
  proof_details TEXT NOT NULL,
  report_id INT(11) NOT NULL,
  owner_verified TINYINT(1) DEFAULT 0,
  finder_confirmed TINYINT(1) DEFAULT 0,
  status ENUM('pending_verification', 'approved', 'rejected', 'handed_off', 'archived') DEFAULT 'pending_verification',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (claimant_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table: notifications
CREATE TABLE notifications (
  notification_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  report_type ENUM('LOST', 'FOUND') NOT NULL DEFAULT 'LOST',
  report_id INT(11) NOT NULL DEFAULT 0,
  type VARCHAR(50) NOT NULL DEFAULT 'match',
  message TEXT NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table: password_resets
CREATE TABLE password_resets (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table: maintenance_settings
CREATE TABLE maintenance_settings (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  message VARCHAR(500) DEFAULT NULL,
  eta DATETIME DEFAULT NULL,
  support_email VARCHAR(150) DEFAULT NULL,
  updated_by VARCHAR(100) DEFAULT 'System Administrator',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: logs
CREATE TABLE logs (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('admin', 'user', 'system', 'anonymous') DEFAULT 'user',
  user_id INT(11) DEFAULT NULL,
  operator_name VARCHAR(100) DEFAULT NULL,
  operator_identifier VARCHAR(100) DEFAULT NULL,
  category ENUM('admin_access', 'user_access', 'security', 'password_reset', 'report_activity', 'match_verification', 'claim_activity', 'system_event') NOT NULL DEFAULT 'user_access',
  action VARCHAR(150) NOT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  device_info VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: question_sets
CREATE TABLE question_sets (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(100) NOT NULL,
  report_type ENUM('LOST', 'FOUND') NOT NULL,
  questions_json LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_question_set (category, report_type)
);

-- Table: access_logs
CREATE TABLE access_logs (
  log_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) DEFAULT NULL,
  action VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  device_info VARCHAR(255) DEFAULT NULL,
  log_time DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: search_lookup_attempts
CREATE TABLE search_lookup_attempts (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table: claim_review_events
CREATE TABLE claim_review_events (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_id INT(11) NOT NULL,
  claimant_id INT(11) NOT NULL,
  actor_user_id INT(11) DEFAULT NULL,
  decision VARCHAR(40) NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  fraud_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  sanction_hours INT(11) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

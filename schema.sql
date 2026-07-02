-- ============================================================
-- Simple RADIUS Manager - Database Schema
-- Works alongside FreeRADIUS's SQL module (rlm_sql).
-- radcheck / radreply / nas match FreeRADIUS's default table
-- layout, so FreeRADIUS can authenticate directly against them.
-- ============================================================

CREATE DATABASE IF NOT EXISTS radius_manager CHARACTER SET utf8mb4;
USE radius_manager;

-- ---------- FreeRADIUS-compatible tables ----------

CREATE TABLE IF NOT EXISTS nas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nasname VARCHAR(128) NOT NULL,      -- router/BRAS IP address
  shortname VARCHAR(32) NOT NULL,
  type VARCHAR(30) DEFAULT 'other',
  ports INT DEFAULT NULL,
  secret VARCHAR(60) NOT NULL,        -- shared RADIUS secret
  description VARCHAR(200) DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS radcheck (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL DEFAULT '',
  attribute VARCHAR(64) NOT NULL DEFAULT '',
  op CHAR(2) NOT NULL DEFAULT '==',
  value VARCHAR(253) NOT NULL DEFAULT '',
  KEY username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS radreply (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL DEFAULT '',
  attribute VARCHAR(64) NOT NULL DEFAULT '',
  op CHAR(2) NOT NULL DEFAULT '=',
  value VARCHAR(253) NOT NULL DEFAULT '',
  KEY username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS radacct (
  radacctid BIGINT AUTO_INCREMENT PRIMARY KEY,
  acctsessionid VARCHAR(64) NOT NULL DEFAULT '',
  acctuniqueid VARCHAR(32) NOT NULL DEFAULT '',
  username VARCHAR(64) NOT NULL DEFAULT '',
  nasipaddress VARCHAR(15) NOT NULL DEFAULT '',
  framedipaddress VARCHAR(15) DEFAULT '',
  acctstarttime DATETIME NULL,
  acctstoptime DATETIME NULL,
  acctsessiontime INT DEFAULT NULL,
  acctinputoctets BIGINT DEFAULT NULL,
  acctoutputoctets BIGINT DEFAULT NULL,
  acctterminatecause VARCHAR(32) DEFAULT '',
  UNIQUE KEY acctuniqueid (acctuniqueid),
  KEY username (username)
) ENGINE=InnoDB;

-- ---------- App tables ----------

CREATE TABLE IF NOT EXISTS plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  download_kbps INT NOT NULL,   -- download speed, in kbps
  upload_kbps INT NOT NULL,     -- upload speed, in kbps
  price DECIMAL(10,2) DEFAULT 0,
  validity_days INT DEFAULT 30,
  description VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pppoe_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password VARCHAR(128) NOT NULL,   -- stored in cleartext to mirror radcheck (PPPoE/CHAP requirement)
  full_name VARCHAR(100) DEFAULT '',
  phone VARCHAR(30) DEFAULT '',
  plan_id INT DEFAULT NULL,
  status ENUM('active','suspended','expired') DEFAULT 'active',
  expiry_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

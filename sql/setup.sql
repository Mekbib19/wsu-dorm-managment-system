-- SQL setup for wsu_dorm (no security, simple schema)
CREATE DATABASE IF NOT EXISTS wsu_dorm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wsu_dorm;

-- students: basic info
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) DEFAULT NULL,
  name VARCHAR(200) DEFAULT NULL,
  last_name VARCHAR(200) DEFAULT NULL,
  room INT DEFAULT NULL,
  block INT DEFAULT NULL,
  otp VARCHAR(50) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- proctor accounts (no security)
CREATE TABLE IF NOT EXISTS proctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- messages from proctor
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  messages TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- maintenance reports
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100) DEFAULT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- dorm list
CREATE TABLE IF NOT EXISTS dorms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name int NOT NULL
) ENGINE=InnoDB;

-- sample data
INSERT INTO students (username, name, last_name, room, block, otp) VALUES
('UGR/919/001', 'Mekbib', 'Tariku', 3, 1, 'AEI112'),
('UGR/919/002', 'Amanuel', 'Bekele', 3, 1, 'BRT221'),
('UGR/919/003', 'Sara', 'Yilma', 5, 1, 'CDE333');

INSERT INTO messages (messages) VALUES
('Welcome to the dorm portal.'),
('Water will be off tomorrow 9:00-11:00.'),
('Please maintain cleanliness in common areas.');

INSERT INTO reports (type, description) VALUES
('Plumbing', 'Leaking sink in room 3.'),
('Light', 'Corridor light not working.');

INSERT INTO proctors (username, password) VALUES
('proctor1', 'pass');

INSERT INTO dorms (name) VALUES
('1'),
('2'),
('3');

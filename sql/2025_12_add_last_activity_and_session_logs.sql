-- Add last_activity columns to track activity timestamps and a session_logs table for audit
ALTER TABLE students ADD COLUMN last_activity DATETIME DEFAULT NULL;
ALTER TABLE proctors ADD COLUMN last_activity DATETIME DEFAULT NULL;
ALTER TABLE admin ADD COLUMN last_activity DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS session_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('student','proctor','admin') NOT NULL,
  user_identifier VARCHAR(255) NOT NULL,
  session_id VARCHAR(255),
  action VARCHAR(50) NOT NULL,
  ip VARCHAR(45),
  user_agent VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

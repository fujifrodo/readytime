-- database.sql - MySQL Database Schema for นัดเพื่อน
-- Simplified version for InfinityFree (No Views, Procedures, Triggers)

-- ===== 1. Users Table =====
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    banned BOOLEAN DEFAULT FALSE,
    token VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    last_login DATETIME DEFAULT NULL,
    INDEX idx_username (username),
    INDEX idx_token (token),
    INDEX idx_banned (banned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 2. Polls Table =====
CREATE TABLE IF NOT EXISTS polls (
    id INT PRIMARY KEY,
    token VARCHAR(20) NOT NULL,
    title VARCHAR(500) NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    allow_maybe TINYINT(1) DEFAULT 0,
    time_mode VARCHAR(50) DEFAULT 'fullday',
    created_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    creator_name VARCHAR(255) NOT NULL,
    expire_date DATE DEFAULT NULL,
    locked_slot_id VARCHAR(50) DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_expire_date (expire_date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 3. Poll Slots Table =====
CREATE TABLE IF NOT EXISTS poll_slots (
    id VARCHAR(50) PRIMARY KEY,
    poll_id INT NOT NULL,
    slot_date DATE NOT NULL,
    period VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    INDEX idx_poll_id (poll_id),
    INDEX idx_slot_date (slot_date),
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 4. Responses Table =====
CREATE TABLE IF NOT EXISTS responses (
    id INT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    submitted_at DATETIME NOT NULL,
    INDEX idx_poll_id (poll_id),
    INDEX idx_user_id (user_id),
    INDEX idx_submitted_at (submitted_at),
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_poll_user (poll_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 5. Votes Table =====
CREATE TABLE IF NOT EXISTS votes (
    id VARCHAR(50) PRIMARY KEY,
    response_id INT NOT NULL,
    slot_id VARCHAR(50) NOT NULL,
    value ENUM('yes', 'maybe', 'no') NOT NULL,
    INDEX idx_response_id (response_id),
    INDEX idx_slot_id (slot_id),
    INDEX idx_value (value),
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES poll_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Installation Complete =====

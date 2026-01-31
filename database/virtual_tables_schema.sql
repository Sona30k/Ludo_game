-- Virtual Tables System Schema
-- This schema separates lobby configuration from active game instances

-- ============================================
-- A. TABLES (Lobby Configuration)
-- ============================================
-- Already exists, but ensure these columns:
-- ALTER TABLE tables ADD COLUMN IF NOT EXISTS name VARCHAR(255);
-- ALTER TABLE tables ADD COLUMN IF NOT EXISTS min_real_players INT DEFAULT 1;
-- ALTER TABLE tables ADD COLUMN IF NOT EXISTS duration_seconds INT;

-- ============================================
-- B. VIRTUAL_TABLES (Active Match Instances)
-- ============================================
CREATE TABLE IF NOT EXISTS virtual_tables (
    id VARCHAR(36) PRIMARY KEY, -- UUID
    table_id INT NOT NULL, -- FK to tables.id
    status ENUM('WAITING', 'RUNNING', 'ENDED', 'CANCELLED') DEFAULT 'WAITING',
    start_time DATETIME NULL,
    wait_end_time DATETIME NULL, -- When wait period ends
    end_time DATETIME NULL,
    current_turn_index INT DEFAULT 0,
    dice_mode ENUM('FAIR', 'CONTROLLED') DEFAULT 'FAIR',
    forced_dice_value TINYINT NULL, -- 1-6 or NULL
    dice_probability_json TEXT NULL, -- JSON for probability control
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    INDEX idx_table_status (table_id, status),
    INDEX idx_wait_end_time (wait_end_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- C. VIRTUAL_TABLE_PLAYERS
-- ============================================
CREATE TABLE IF NOT EXISTS virtual_table_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_table_id VARCHAR(36) NOT NULL,
    user_id INT NULL, -- NULL for bots
    bot_id VARCHAR(50) NULL, -- NULL for humans, e.g., 'bot_1234567890_0'
    bot_name VARCHAR(50) NULL, -- display name for bots
    is_bot TINYINT(1) DEFAULT 0,
    score INT DEFAULT 0,
    seat_no TINYINT NOT NULL, -- 0, 1, 2, 3
    is_connected TINYINT(1) DEFAULT 1,
    last_action_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_table_id) REFERENCES virtual_tables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_seat (virtual_table_id, seat_no),
    INDEX idx_virtual_table (virtual_table_id),
    INDEX idx_user (user_id),
    INDEX idx_bot (bot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- D. VIRTUAL_TABLE_DICE_LOG
-- ============================================
CREATE TABLE IF NOT EXISTS virtual_table_dice_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_table_id VARCHAR(36) NOT NULL,
    player_id INT NULL, -- user_id from virtual_table_players
    bot_id VARCHAR(50) NULL, -- bot_id from virtual_table_players
    dice_value TINYINT NOT NULL, -- 1-6
    is_forced TINYINT(1) DEFAULT 0,
    forced_by_admin_id INT NULL,
    turn_no INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_table_id) REFERENCES virtual_tables(id) ON DELETE CASCADE,
    FOREIGN KEY (forced_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_virtual_table (virtual_table_id),
    INDEX idx_turn (virtual_table_id, turn_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- E. VIRTUAL_TABLE_MOVES (Optional - for game history)
-- ============================================
CREATE TABLE IF NOT EXISTS virtual_table_moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_table_id VARCHAR(36) NOT NULL,
    player_id INT NULL,
    bot_id VARCHAR(50) NULL,
    pawn_index TINYINT NOT NULL,
    from_position INT NOT NULL,
    to_position INT NOT NULL,
    points_earned INT DEFAULT 0,
    killed_opponent TINYINT(1) DEFAULT 0,
    turn_no INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_table_id) REFERENCES virtual_tables(id) ON DELETE CASCADE,
    INDEX idx_virtual_table (virtual_table_id),
    INDEX idx_turn (virtual_table_id, turn_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- F. Update existing tables table (if needed)
-- ============================================
-- Run these ALTER statements if columns don't exist:
-- ALTER TABLE tables ADD COLUMN name VARCHAR(255) AFTER id;
-- ALTER TABLE tables ADD COLUMN min_real_players INT DEFAULT 1 AFTER type;
-- ALTER TABLE tables ADD COLUMN duration_seconds INT AFTER time_limit;


CREATE TABLE IF NOT EXISTS {PREFIX}shows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    postcode VARCHAR(10) DEFAULT NULL,
    latitude DECIMAL(9,6) DEFAULT NULL,
    longitude DECIMAL(9,6) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    image_filename VARCHAR(150) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_shows_name_start (name, start_date),
    KEY idx_shows_published_dates (is_published, start_date, end_date),
    KEY idx_shows_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

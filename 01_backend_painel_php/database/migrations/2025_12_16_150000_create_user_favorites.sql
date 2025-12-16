-- Migration: Create user_favorites table for storing user's favorite pages
-- Created: 2025-12-16
-- Description: Allows users to mark pages as favorites for quick access

CREATE TABLE IF NOT EXISTS user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    page_label VARCHAR(255) NOT NULL,
    page_icon VARCHAR(100) DEFAULT 'fa-star',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_page (user_id, page_url),
    INDEX idx_user (user_id),
    INDEX idx_sort_order (sort_order),
    CONSTRAINT fk_user_favorites_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

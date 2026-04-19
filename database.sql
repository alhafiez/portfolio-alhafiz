-- ============================================================
-- DATABASE SETUP — Portofolio Muhammad Al Hafiz
-- Jalankan file ini sekali di phpMyAdmin atau MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS portfolio_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(255)  DEFAULT NULL,
    message     TEXT          NOT NULL,
    ai_reply    TEXT          DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    status      ENUM('approved','pending','spam') NOT NULL DEFAULT 'approved',
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status     (status),
    INDEX idx_created_at (created_at),
    INDEX idx_ip         (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contoh data awal (opsional, hapus jika tidak perlu)
INSERT INTO comments (name, email, message, ai_reply) VALUES
('Budi Santoso',   NULL,                    'Portofolio yang sangat keren! Keep it up, semangat kuliahnya!', 'Terima kasih banyak Budi! Al Hafiz akan terus semangat dan belajar lebih keras lagi.'),
('Siti Rahma',     'siti@example.com',      'Desainnya bagus banget, modern dan clean. Sukses terus ya!',   'Makasih Siti! Semoga bisa terus berkembang dan memberikan karya terbaik.');

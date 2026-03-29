-- ============================================================
-- Migration: Email-Feature
-- Neue Spalten in user_profile und neue email_log Tabelle
-- ============================================================

-- Neue Spalten zur user_profile Tabelle hinzufügen
ALTER TABLE user_profile
    ADD COLUMN IF NOT EXISTS email_verified               TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS verification_token           VARCHAR(255)          NULL,
    ADD COLUMN IF NOT EXISTS verification_token_expires   TIMESTAMP             NULL,
    ADD COLUMN IF NOT EXISTS password_reset_token         VARCHAR(255)          NULL,
    ADD COLUMN IF NOT EXISTS password_reset_token_expires TIMESTAMP             NULL;

-- Bestehende Benutzer als verifiziert markieren,
-- damit der Login für vorhandene Accounts weiterhin funktioniert.
UPDATE user_profile SET email_verified = 1 WHERE email_verified = 0;

-- Neue Tabelle für Email-Protokollierung
CREATE TABLE IF NOT EXISTS email_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email_type      ENUM('registration', 'password_reset', 'account_deleted') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    metadata        JSON NULL
);

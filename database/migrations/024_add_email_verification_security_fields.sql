-- Migration: 024_add_email_verification_security_fields
-- Description: Add attempt tracking and cooldown fields to email_verification_tokens table.

ALTER TABLE email_verification_tokens 
ADD COLUMN attempt_count INTEGER DEFAULT 0,
ADD COLUMN last_attempt_at TIMESTAMPTZ NULL,
ADD COLUMN resend_available_at TIMESTAMPTZ NULL;

-- Enforce attempt count non-negative check
ALTER TABLE email_verification_tokens
ADD CONSTRAINT email_verification_tokens_attempt_count_check CHECK (attempt_count >= 0);

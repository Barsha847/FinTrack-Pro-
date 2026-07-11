-- Migration: 019_create_email_verification_tokens_table
-- Description: Create email_verification_tokens table and constraints.

CREATE TABLE email_verification_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Indexing token_hash and expires_at
CREATE INDEX email_verification_tokens_token_hash_idx ON email_verification_tokens (token_hash);
CREATE INDEX email_verification_tokens_expires_at_idx ON email_verification_tokens (expires_at);
CREATE INDEX email_verification_tokens_user_id_idx ON email_verification_tokens (user_id);

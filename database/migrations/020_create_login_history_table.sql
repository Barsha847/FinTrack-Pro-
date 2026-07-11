-- Migration: 020_create_login_history_table
-- Description: Create login_history table and constraints.

CREATE TABLE login_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    email_attempted VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    status VARCHAR(30) NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT login_history_status_check CHECK (status IN ('success', 'failed', 'blocked'))
);

-- Indexing strategy
CREATE INDEX login_history_user_id_idx ON login_history (user_id);
CREATE INDEX login_history_created_at_idx ON login_history (created_at);
CREATE INDEX login_history_email_attempted_idx ON login_history (email_attempted);

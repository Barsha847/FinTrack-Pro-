-- Migration: 016_create_notifications_table
-- Description: Create notifications table and indexes.

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50),
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMPTZ NULL
);

-- Indexing user_id, is_read, and created_at
CREATE INDEX notifications_user_id_idx ON notifications (user_id);
CREATE INDEX notifications_is_read_idx ON notifications (is_read);
CREATE INDEX notifications_created_at_idx ON notifications (created_at);
CREATE INDEX notifications_user_read_created_idx ON notifications (user_id, is_read, created_at);

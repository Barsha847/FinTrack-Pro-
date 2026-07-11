-- Migration: 021_create_activity_logs_table
-- Description: Create activity_logs table and indexes.

CREATE TABLE activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100),
    description TEXT,
    metadata JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Indexing strategy
CREATE INDEX activity_logs_user_id_idx ON activity_logs (user_id);
CREATE INDEX activity_logs_action_idx ON activity_logs (action);
CREATE INDEX activity_logs_module_idx ON activity_logs (module);
CREATE INDEX activity_logs_created_at_idx ON activity_logs (created_at);

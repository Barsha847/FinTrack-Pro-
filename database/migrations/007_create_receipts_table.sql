-- Migration: 007_create_receipts_table
-- Description: Create receipts table and constraints.

CREATE TABLE receipts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    expense_id UUID NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255),
    storage_path VARCHAR(500),
    mime_type VARCHAR(100),
    file_size BIGINT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT receipts_file_size_check CHECK (file_size >= 0)
);

-- Indexing user_id and expense_id
CREATE INDEX receipts_user_id_idx ON receipts (user_id);
CREATE INDEX receipts_expense_id_idx ON receipts (expense_id);

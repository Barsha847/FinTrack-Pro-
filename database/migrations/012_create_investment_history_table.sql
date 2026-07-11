-- Migration: 012_create_investment_history_table
-- Description: Create investment_history table and constraints.

CREATE TABLE investment_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    investment_id UUID NOT NULL REFERENCES investments(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    value NUMERIC(20,2) NOT NULL,
    recorded_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT investment_history_value_check CHECK (value >= 0)
);

-- Indexing investment_id and recorded_at
CREATE INDEX investment_history_investment_id_idx ON investment_history (investment_id);
CREATE INDEX investment_history_recorded_at_idx ON investment_history (recorded_at);
CREATE INDEX investment_history_user_id_idx ON investment_history (user_id);

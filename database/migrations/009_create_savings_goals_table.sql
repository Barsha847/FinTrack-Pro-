-- Migration: 009_create_savings_goals_table
-- Description: Create savings_goals table and constraints.

CREATE TABLE savings_goals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    target_amount NUMERIC(15,2) NOT NULL,
    current_amount NUMERIC(15,2) DEFAULT 0,
    target_date DATE,
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT savings_goals_target_amount_check CHECK (target_amount > 0),
    CONSTRAINT savings_goals_current_amount_check CHECK (current_amount >= 0),
    CONSTRAINT savings_goals_status_check CHECK (status IN ('active', 'completed', 'paused', 'cancelled'))
);

-- Indexing user_id
CREATE INDEX savings_goals_user_id_idx ON savings_goals (user_id);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_savings_goals_updated_at
BEFORE UPDATE ON savings_goals
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

-- Migration: 010_create_savings_contributions_table
-- Description: Create savings_contributions table and constraints.

CREATE TABLE savings_contributions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    goal_id UUID NOT NULL REFERENCES savings_goals(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount NUMERIC(15,2) NOT NULL,
    contribution_date DATE NOT NULL,
    note VARCHAR(500),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT savings_contributions_amount_check CHECK (amount > 0)
);

-- Indexing user_id and goal_id
CREATE INDEX savings_contributions_user_id_idx ON savings_contributions (user_id);
CREATE INDEX savings_contributions_goal_id_idx ON savings_contributions (goal_id);

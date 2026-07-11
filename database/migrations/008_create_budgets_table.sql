-- Migration: 008_create_budgets_table
-- Description: Create budgets table and constraints.

CREATE TABLE budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    amount NUMERIC(15,2) NOT NULL,
    budget_month INTEGER NOT NULL,
    budget_year INTEGER NOT NULL,
    alert_50_sent BOOLEAN DEFAULT FALSE,
    alert_75_sent BOOLEAN DEFAULT FALSE,
    alert_90_sent BOOLEAN DEFAULT FALSE,
    alert_100_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT budgets_amount_check CHECK (amount > 0),
    CONSTRAINT budgets_month_check CHECK (budget_month BETWEEN 1 AND 12),
    CONSTRAINT budgets_year_check CHECK (budget_year BETWEEN 2000 AND 2100),
    CONSTRAINT budgets_user_category_month_year_unique UNIQUE (user_id, category_id, budget_month, budget_year)
);

-- Indexing user_id
CREATE INDEX budgets_user_id_idx ON budgets (user_id);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_budgets_updated_at
BEFORE UPDATE ON budgets
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

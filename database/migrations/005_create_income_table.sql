-- Migration: 005_create_income_table
-- Description: Create income table, indexes, and constraints.

CREATE TABLE income (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    payment_method_id UUID REFERENCES payment_methods(id) ON DELETE SET NULL,
    amount NUMERIC(15,2) NOT NULL,
    income_date DATE NOT NULL,
    description VARCHAR(500),
    source VARCHAR(150),
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency VARCHAR(30),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT income_amount_check CHECK (amount > 0)
);

-- Indexing strategy
CREATE INDEX income_user_id_idx ON income (user_id);
CREATE INDEX income_date_idx ON income (income_date);
CREATE INDEX income_category_id_idx ON income (category_id);
CREATE INDEX income_user_id_date_idx ON income (user_id, income_date);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_income_updated_at
BEFORE UPDATE ON income
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

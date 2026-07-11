-- Migration: 006_create_expenses_table
-- Description: Create expenses table, indexes, and constraints.

CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    payment_method_id UUID REFERENCES payment_methods(id) ON DELETE SET NULL,
    amount NUMERIC(15,2) NOT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(500),
    merchant VARCHAR(150),
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency VARCHAR(30),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT expenses_amount_check CHECK (amount > 0)
);

-- Indexing strategy
CREATE INDEX expenses_user_id_idx ON expenses (user_id);
CREATE INDEX expenses_date_idx ON expenses (expense_date);
CREATE INDEX expenses_category_id_idx ON expenses (category_id);
CREATE INDEX expenses_user_id_date_idx ON expenses (user_id, expense_date);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_expenses_updated_at
BEFORE UPDATE ON expenses
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

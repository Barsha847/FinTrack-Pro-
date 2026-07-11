-- Migration: 014_create_emi_payments_table
-- Description: Create emi_payments table and constraints.

CREATE TABLE emi_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    loan_id UUID NOT NULL REFERENCES loans(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount NUMERIC(15,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE,
    status VARCHAR(30) DEFAULT 'pending',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT emi_payments_amount_check CHECK (amount > 0),
    CONSTRAINT emi_payments_status_check CHECK (status IN ('pending', 'paid', 'overdue', 'cancelled'))
);

-- Indexing user_id and loan_id
CREATE INDEX emi_payments_user_id_idx ON emi_payments (user_id);
CREATE INDEX emi_payments_loan_id_idx ON emi_payments (loan_id);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_emi_payments_updated_at
BEFORE UPDATE ON emi_payments
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

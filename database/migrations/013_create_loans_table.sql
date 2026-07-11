-- Migration: 013_create_loans_table
-- Description: Create loans table and constraints.

CREATE TABLE loans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    loan_name VARCHAR(150) NOT NULL,
    loan_type VARCHAR(50),
    lender_name VARCHAR(150),
    principal_amount NUMERIC(15,2) NOT NULL,
    interest_rate NUMERIC(8,4) NOT NULL,
    remaining_amount NUMERIC(15,2) NOT NULL,
    emi_amount NUMERIC(15,2),
    start_date DATE NOT NULL,
    end_date DATE,
    next_due_date DATE,
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT loans_principal_amount_check CHECK (principal_amount > 0),
    CONSTRAINT loans_interest_rate_check CHECK (interest_rate >= 0),
    CONSTRAINT loans_remaining_amount_check CHECK (remaining_amount >= 0),
    CONSTRAINT loans_emi_amount_check CHECK (emi_amount IS NULL OR emi_amount > 0),
    CONSTRAINT loans_status_check CHECK (status IN ('active', 'paid', 'overdue', 'closed'))
);

-- Indexing user_id
CREATE INDEX loans_user_id_idx ON loans (user_id);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_loans_updated_at
BEFORE UPDATE ON loans
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

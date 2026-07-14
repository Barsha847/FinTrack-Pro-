-- Migration: 025_add_tenure_months_to_loans
-- Description: Add tenure_months column to loans table and create performance indexes.

-- Add tenure_months column
ALTER TABLE loans ADD COLUMN tenure_months INTEGER DEFAULT 12 CHECK (tenure_months > 0);

-- Create performance indexes if they do not already exist
CREATE INDEX IF NOT EXISTS investments_buy_date_idx ON investments (buy_date);
CREATE INDEX IF NOT EXISTS loans_status_idx ON loans (status);
CREATE INDEX IF NOT EXISTS loans_next_due_date_idx ON loans (next_due_date);
CREATE INDEX IF NOT EXISTS emi_payments_due_date_idx ON emi_payments (due_date);
CREATE INDEX IF NOT EXISTS emi_payments_status_idx ON emi_payments (status);

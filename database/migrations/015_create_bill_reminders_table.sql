-- Migration: 015_create_bill_reminders_table
-- Description: Create bill_reminders table and constraints.

CREATE TABLE bill_reminders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    bill_name VARCHAR(150) NOT NULL,
    amount NUMERIC(15,2),
    due_date DATE NOT NULL,
    remind_before_days INTEGER DEFAULT 3,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency VARCHAR(30),
    status VARCHAR(30) DEFAULT 'pending',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT bill_reminders_amount_check CHECK (amount IS NULL OR amount >= 0),
    CONSTRAINT bill_reminders_remind_before_days_check CHECK (remind_before_days >= 0),
    CONSTRAINT bill_reminders_status_check CHECK (status IN ('pending', 'paid', 'overdue', 'cancelled'))
);

-- Indexing user_id and due_date
CREATE INDEX bill_reminders_user_id_idx ON bill_reminders (user_id);
CREATE INDEX bill_reminders_due_date_idx ON bill_reminders (due_date);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_bill_reminders_updated_at
BEFORE UPDATE ON bill_reminders
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

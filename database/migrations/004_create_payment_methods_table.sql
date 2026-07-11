-- Migration: 004_create_payment_methods_table
-- Description: Create payment_methods table and constraints.

CREATE TABLE payment_methods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT payment_methods_type_check CHECK (type IN ('cash', 'bank', 'card', 'upi', 'wallet', 'other'))
);

-- Indexing user_id
CREATE INDEX payment_methods_user_id_idx ON payment_methods (user_id);

-- Uniqueness: Prevent duplicate custom payment methods per user
CREATE UNIQUE INDEX payment_methods_name_unique_idx ON payment_methods (user_id, LOWER(name));

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_payment_methods_updated_at
BEFORE UPDATE ON payment_methods
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

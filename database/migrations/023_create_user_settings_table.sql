-- Migration: 023_create_user_settings_table
-- Description: Create user_settings table and constraints.

CREATE TABLE user_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    currency VARCHAR(10) DEFAULT 'INR',
    timezone VARCHAR(100) DEFAULT 'Asia/Kolkata',
    date_format VARCHAR(30) DEFAULT 'DD-MM-YYYY',
    theme VARCHAR(30) DEFAULT 'system',
    email_notifications BOOLEAN DEFAULT TRUE,
    budget_notifications BOOLEAN DEFAULT TRUE,
    bill_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT user_settings_theme_check CHECK (theme IN ('light', 'dark', 'system'))
);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_user_settings_updated_at
BEFORE UPDATE ON user_settings
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

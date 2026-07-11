-- Migration: 003_create_categories_table
-- Description: Create categories table and constraints.

CREATE TABLE categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(30) NOT NULL,
    icon VARCHAR(100),
    color VARCHAR(30),
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT categories_type_check CHECK (type IN ('income', 'expense'))
);

-- Indexing user_id and type for rapid lookup
CREATE INDEX categories_user_id_idx ON categories (user_id);
CREATE INDEX categories_type_idx ON categories (type);

-- Uniqueness: Prevent duplicate system categories (same name & type)
CREATE UNIQUE INDEX categories_system_unique_idx ON categories (name, type) 
WHERE user_id IS NULL AND is_system = TRUE;

-- Uniqueness: Prevent duplicate custom user categories (same user, name & type)
CREATE UNIQUE INDEX categories_user_unique_idx ON categories (user_id, name, type) 
WHERE user_id IS NOT NULL;

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_categories_updated_at
BEFORE UPDATE ON categories
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

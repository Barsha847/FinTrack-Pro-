-- Migration: 011_create_investments_table
-- Description: Create investments table and constraints.

CREATE TABLE investments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    asset_name VARCHAR(150) NOT NULL,
    asset_type VARCHAR(50) NOT NULL,
    quantity NUMERIC(20,8) NOT NULL,
    buy_price NUMERIC(20,4) NOT NULL,
    current_value NUMERIC(20,2) DEFAULT 0,
    buy_date DATE NOT NULL,
    notes VARCHAR(500),
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT investments_quantity_check CHECK (quantity > 0),
    CONSTRAINT investments_buy_price_check CHECK (buy_price > 0),
    CONSTRAINT investments_current_value_check CHECK (current_value >= 0),
    CONSTRAINT investments_asset_type_check CHECK (asset_type IN (
        'stock', 'mutual_fund', 'fixed_deposit', 'gold', 'crypto', 
        'bond', 'real_estate', 'ppf', 'epf', 'sip', 'nps', 'other'
    )),
    CONSTRAINT investments_status_check CHECK (status IN ('active', 'sold', 'closed'))
);

-- Indexing user_id and asset_type
CREATE INDEX investments_user_id_idx ON investments (user_id);
CREATE INDEX investments_asset_type_idx ON investments (asset_type);

-- Automatically update updated_at trigger
CREATE TRIGGER trigger_update_investments_updated_at
BEFORE UPDATE ON investments
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

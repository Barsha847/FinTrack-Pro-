-- Migration: 002_create_users_table
-- Description: Create the users table and its constraints.

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    account_status VARCHAR(30) DEFAULT 'active',
    role VARCHAR(30) DEFAULT 'user',
    profile_image VARCHAR(500),
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMPTZ NULL,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ NULL,

    CONSTRAINT users_role_check CHECK (role IN ('user', 'admin')),
    CONSTRAINT users_status_check CHECK (account_status IN ('active', 'inactive', 'blocked', 'suspended')),
    CONSTRAINT users_failed_login_attempts_check CHECK (failed_login_attempts >= 0)
);

-- Case-insensitive uniqueness for email
CREATE UNIQUE INDEX users_email_unique_idx ON users (LOWER(email));

-- Partial index for username uniqueness (only when username is not null)
CREATE UNIQUE INDEX users_username_unique_idx ON users (username) WHERE username IS NOT NULL;

-- Automatically update updated_at before update
CREATE TRIGGER trigger_update_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

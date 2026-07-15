-- Migration: 026_add_event_key_to_notifications
-- Description: Add event_key column and uniqueness constraints to prevent duplicate alerts.

ALTER TABLE notifications ADD COLUMN event_key VARCHAR(150) NULL;

-- Create unique index to enforce event deduplication per user
CREATE UNIQUE INDEX notifications_user_event_key_uidx ON notifications (user_id, event_key) WHERE event_key IS NOT NULL;

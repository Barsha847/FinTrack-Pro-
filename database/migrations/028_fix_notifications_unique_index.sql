-- Migration: 028_fix_notifications_unique_index
-- Description: Drop partial index and create full unique index to support ON CONFLICT queries.

DROP INDEX IF EXISTS notifications_user_event_key_uidx;
CREATE UNIQUE INDEX notifications_user_event_key_uidx ON notifications (user_id, event_key);

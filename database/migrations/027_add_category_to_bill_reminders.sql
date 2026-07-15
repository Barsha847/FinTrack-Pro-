-- Migration: 027_add_category_to_bill_reminders
-- Description: Add category column to bill_reminders table to sync with frontend categories.

ALTER TABLE bill_reminders ADD COLUMN category VARCHAR(50) DEFAULT 'others';

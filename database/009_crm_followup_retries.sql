ALTER TABLE lead_followups ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status, ADD COLUMN last_attempt_at DATETIME NULL AFTER attempt_count, ADD COLUMN next_attempt_at DATETIME NULL AFTER last_attempt_at;
UPDATE lead_followups SET next_attempt_at=scheduled_at WHERE next_attempt_at IS NULL AND status='pending';

-- Phase 4.4 Lead Activity & Follow-up CRM
-- Compatible with MySQL versions where ALTER TABLE ADD COLUMN IF NOT EXISTS is unavailable.

CREATE TABLE IF NOT EXISTS lead_activities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NOT NULL,
  activity_type ENUM('note','call','email','whatsapp','meeting','proposal','status_change','follow_up') NOT NULL DEFAULT 'note',
  subject VARCHAR(180) NULL,
  description TEXT NULL,
  follow_up_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lead_activity_lead (lead_id),
  KEY idx_lead_activity_follow_up (follow_up_at),
  CONSTRAINT fk_lead_activity_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add each leads column only when it does not already exist.
SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='assigned_to'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN assigned_to VARCHAR(180) NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='follow_up_at'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN follow_up_at DATETIME NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='contacted_at'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN contacted_at DATETIME NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='proposal_at'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN proposal_at DATETIME NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='won_at'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN won_at DATETIME NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='lost_reason'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN lost_reason VARCHAR(500) NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND COLUMN_NAME='estimated_value'),
  'SELECT 1',
  'ALTER TABLE leads ADD COLUMN estimated_value DECIMAL(15,2) NULL'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes only when they do not already exist.
SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND INDEX_NAME='idx_leads_follow_up'),
  'SELECT 1',
  'CREATE INDEX idx_leads_follow_up ON leads(follow_up_at)'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='leads' AND INDEX_NAME='idx_leads_status_priority'),
  'SELECT 1',
  'CREATE INDEX idx_leads_status_priority ON leads(status, priority)'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

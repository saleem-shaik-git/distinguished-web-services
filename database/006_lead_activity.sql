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

ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS assigned_to VARCHAR(180) NULL,
  ADD COLUMN IF NOT EXISTS follow_up_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS contacted_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS proposal_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS won_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS lost_reason VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS estimated_value DECIMAL(15,2) NULL;

CREATE INDEX idx_leads_follow_up ON leads(follow_up_at);
CREATE INDEX idx_leads_status_priority ON leads(status, priority);
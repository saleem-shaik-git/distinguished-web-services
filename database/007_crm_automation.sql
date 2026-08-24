-- Phase 4.6 CRM Automation
CREATE TABLE IF NOT EXISTS lead_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 0,
  temperature ENUM('cold','warm','hot') NOT NULL DEFAULT 'cold',
  reasons TEXT NULL,
  calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_lead_score_lead (lead_id), KEY idx_lead_score_temperature (temperature),
  CONSTRAINT fk_lead_score_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS lead_followups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NOT NULL,
  followup_type ENUM('initial','proposal','no_response','closing','reengagement','custom') NOT NULL DEFAULT 'custom',
  scheduled_at DATETIME NOT NULL,
  status ENUM('pending','sent','cancelled','failed') NOT NULL DEFAULT 'pending',
  subject VARCHAR(255) NULL,
  body TEXT NULL,
  sent_at DATETIME NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_followup_queue (status,scheduled_at), KEY idx_followup_lead (lead_id),
  CONSTRAINT fk_followup_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS automation_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('success','warning','error') NOT NULL DEFAULT 'success',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_automation_log_lead (lead_id), KEY idx_automation_log_created (created_at),
  CONSTRAINT fk_automation_log_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS crm_automation_settings (
  id TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  auto_score TINYINT(1) NOT NULL DEFAULT 1,
  reminder_hours INT NOT NULL DEFAULT 24,
  initial_followup_hours INT NOT NULL DEFAULT 24,
  proposal_followup_days INT NOT NULL DEFAULT 3,
  no_response_days INT NOT NULL DEFAULT 5,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO crm_automation_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS email_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_key VARCHAR(100) NOT NULL,
  name VARCHAR(180) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html LONGTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_email_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS email_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  recipient_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_email_log_lead (lead_id), KEY idx_email_log_status (status),
  CONSTRAINT fk_email_log_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_log_template FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO email_templates (template_key,name,subject,body_html) VALUES
('new_enquiry','New Enquiry Acknowledgement','We received your enquiry — Distinguished Web Services','<p>Hi {{client_name}},</p><p>Thank you for contacting Distinguished Web Services regarding <strong>{{service}}</strong>.</p><p>We have received your enquiry and will review your requirements. We will get back to you shortly.</p><p>Regards,<br>Distinguished Web Services</p>'),
('initial_followup','Initial Follow-up','Following up on your {{service}} enquiry','<p>Hi {{client_name}},</p><p>I am following up regarding your {{service}} enquiry with Distinguished Web Services.</p><p>We would be happy to discuss your requirements and recommend the right approach.</p><p>Regards,<br>Distinguished Web Services</p>'),
('proposal_followup','Proposal Follow-up','Following up on your project proposal','<p>Hi {{client_name}},</p><p>I wanted to follow up regarding the proposal for your project.</p><p>Please let us know if you have any questions or would like to discuss any part of the proposal.</p><p>Regards,<br>Distinguished Web Services</p>'),
('no_response','No Response Follow-up','Just checking in — Distinguished Web Services','<p>Hi {{client_name}},</p><p>Just checking in regarding your enquiry. If the project is still planned, we would be glad to continue the conversation.</p><p>Regards,<br>Distinguished Web Services</p>'),
('closing_followup','Closing Follow-up','Can we help move your project forward?','<p>Hi {{client_name}},</p><p>I wanted to make one final follow-up regarding your project enquiry.</p><p>If the timing is right, we would be happy to help you move forward.</p><p>Regards,<br>Distinguished Web Services</p>')
ON DUPLICATE KEY UPDATE name=VALUES(name);
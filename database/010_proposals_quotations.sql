CREATE TABLE IF NOT EXISTS proposals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 lead_id BIGINT UNSIGNED NOT NULL,
 proposal_number VARCHAR(40) NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL,
 status ENUM('draft','sent','viewed','accepted','rejected','expired','cancelled') NOT NULL DEFAULT 'draft',
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax DECIMAL(15,2) NOT NULL DEFAULT 0,
 total DECIMAL(15,2) NOT NULL DEFAULT 0,
 valid_until DATE NULL,
 notes TEXT NULL,
 terms TEXT NULL,
 public_token CHAR(64) NOT NULL UNIQUE,
 sent_at DATETIME NULL,
 viewed_at DATETIME NULL,
 accepted_at DATETIME NULL,
 rejected_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_proposals_lead(lead_id), KEY idx_proposals_status(status),
 CONSTRAINT fk_proposals_lead FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proposal_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 description VARCHAR(255) NOT NULL,
 quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
 unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), KEY idx_proposal_items_proposal(proposal_id),
 CONSTRAINT fk_proposal_items_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proposal_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(50) NOT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_proposal_events_proposal(proposal_id),
 CONSTRAINT fk_proposal_events_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

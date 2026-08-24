CREATE TABLE IF NOT EXISTS invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lead_id BIGINT UNSIGNED NOT NULL,
 proposal_id BIGINT UNSIGNED NULL,
 invoice_number VARCHAR(80) NOT NULL UNIQUE,
 public_token CHAR(64) NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax DECIMAL(15,2) NOT NULL DEFAULT 0,
 total DECIMAL(15,2) NOT NULL DEFAULT 0,
 amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
 balance_due DECIMAL(15,2) NOT NULL DEFAULT 0,
 status ENUM('draft','sent','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
 issue_date DATE NOT NULL,
 due_date DATE NULL,
 notes TEXT NULL,
 terms TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_invoice_lead(lead_id), INDEX idx_invoice_status(status), INDEX idx_invoice_due(due_date),
 CONSTRAINT fk_invoice_lead FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE,
 CONSTRAINT fk_invoice_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 description VARCHAR(500) NOT NULL,
 quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
 unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 CONSTRAINT fk_invoice_item_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 lead_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(150) NOT NULL UNIQUE,
 gateway VARCHAR(50) NOT NULL DEFAULT 'paystack',
 amount DECIMAL(15,2) NOT NULL,
 currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
 status ENUM('pending','successful','failed','refunded') NOT NULL DEFAULT 'pending',
 gateway_transaction_id VARCHAR(150) NULL,
 paid_at DATETIME NULL,
 metadata JSON NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_payment_invoice(invoice_id), INDEX idx_payment_status(status),
 CONSTRAINT fk_payment_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 CONSTRAINT fk_payment_lead FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 description TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_invoice_event(invoice_id),
 CONSTRAINT fk_invoice_event_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

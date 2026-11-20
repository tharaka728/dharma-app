ALTER TABLE customers ADD COLUMN allow_credit_sales TINYINT(1) NOT NULL DEFAULT 0 CHECK (allow_credit_sales IN (0,1));
ALTER TABLE invoices MODIFY device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, MODIFY number_allocation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, MODIFY policy_grant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD COLUMN posting_channel ENUM('OFFLINE','ONLINE') NOT NULL DEFAULT 'OFFLINE', ADD CONSTRAINT invoices_channel_references CHECK ((posting_channel='ONLINE' AND device_id IS NULL AND number_allocation_id IS NULL AND policy_grant_id IS NULL) OR (posting_channel='OFFLINE' AND device_id IS NOT NULL AND number_allocation_id IS NOT NULL AND policy_grant_id IS NOT NULL)), ADD FOREIGN KEY (rep_id) REFERENCES users(id);
ALTER TABLE invoice_items MODIFY cost_price_snapshot DECIMAL(15,2) NULL CHECK (cost_price_snapshot>=0), ADD COLUMN promotion_snapshot JSON NULL, ADD UNIQUE KEY invoice_product_once(invoice_id,product_id);
CREATE TABLE invoice_settings (id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1), business_prefix VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK(business_prefix REGEXP '^[A-Z][A-Z0-9-]{0,11}$'), version BIGINT UNSIGNED NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO invoice_settings(id,business_prefix) VALUES (1,'DCS');
CREATE TABLE online_invoice_details (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, source_location_id BIGINT UNSIGNED NOT NULL, issued_by_user_id BIGINT UNSIGNED NOT NULL, due_date DATE NOT NULL, credit_terms_days INT UNSIGNED NOT NULL, credit_limit_snapshot DECIMAL(15,2) NULL CHECK(credit_limit_snapshot>=0), review_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id), FOREIGN KEY(source_location_id) REFERENCES stock_locations(id), FOREIGN KEY(issued_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_receipts (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY CHECK(request_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'), invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL, payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 received_amount DECIMAL(15,2) NOT NULL CHECK(received_amount>=0), applied_amount DECIMAL(15,2) NOT NULL CHECK(applied_amount>=0), change_amount DECIMAL(15,2) NOT NULL CHECK(change_amount>=0), payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE, created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CHECK(received_amount=applied_amount+change_amount), CHECK((applied_amount=0 AND payment_id IS NULL) OR (applied_amount>0 AND payment_id IS NOT NULL)),
 FOREIGN KEY(invoice_id) REFERENCES invoices(id), FOREIGN KEY(actor_user_id) REFERENCES users(id), FOREIGN KEY(payment_id) REFERENCES payments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_voids (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE CHECK(request_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'), actor_user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0), payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, reversal_transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, cancelled_outstanding DECIMAL(15,2) NOT NULL CHECK(cancelled_outstanding>=0), refund_due DECIMAL(15,2) NOT NULL CHECK(refund_due>=0), created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES invoices(id), FOREIGN KEY(actor_user_id) REFERENCES users(id), FOREIGN KEY(reversal_transaction_id) REFERENCES inventory_transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER online_invoice_details_no_update BEFORE UPDATE ON online_invoice_details FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable invoice metadata';
CREATE TRIGGER online_invoice_details_no_delete BEFORE DELETE ON online_invoice_details FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable invoice metadata';
CREATE TRIGGER invoice_receipts_no_update BEFORE UPDATE ON invoice_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable receipt';
CREATE TRIGGER invoice_receipts_no_delete BEFORE DELETE ON invoice_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable receipt';
CREATE TRIGGER invoice_voids_no_update BEFORE UPDATE ON invoice_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable void and refund liability';
CREATE TRIGGER invoice_voids_no_delete BEFORE DELETE ON invoice_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable void and refund liability';
CREATE TRIGGER payments_no_update BEFORE UPDATE ON payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable payment - append a reversal';
CREATE TRIGGER payments_no_delete BEFORE DELETE ON payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable payment - append a reversal';
CREATE TRIGGER payment_allocations_no_update BEFORE UPDATE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable payment allocation';
CREATE TRIGGER payment_allocations_no_delete BEFORE DELETE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable payment allocation';

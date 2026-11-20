ALTER TABLE invoices DROP CONSTRAINT invoices_channel_references, MODIFY posting_channel ENUM('OFFLINE','ONLINE','SYNC') NOT NULL DEFAULT 'OFFLINE', ADD CONSTRAINT invoices_channel_references CHECK ((posting_channel='ONLINE' AND device_id IS NULL AND number_allocation_id IS NULL AND policy_grant_id IS NULL) OR (posting_channel='OFFLINE' AND device_id IS NOT NULL AND number_allocation_id IS NOT NULL AND policy_grant_id IS NOT NULL) OR (posting_channel='SYNC' AND device_id IS NOT NULL AND number_allocation_id IS NULL AND policy_grant_id IS NOT NULL));
CREATE TABLE offline_device_controls (
 device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, public_key TEXT CHARACTER SET ascii NOT NULL, last_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, replacement_cleared_at_utc DATETIME(6) NULL,
 FOREIGN KEY(device_id) REFERENCES devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE offline_sale_payloads (
 operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, sequence_number BIGINT NOT NULL CHECK(sequence_number>0), body_json JSON NOT NULL, signature VARCHAR(128) CHARACTER SET ascii NOT NULL, created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(device_id,sequence_number), FOREIGN KEY(operation_id) REFERENCES sync_operations(id), FOREIGN KEY(device_id) REFERENCES devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE offline_sale_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL, action ENUM('RETRY','REJECT') NOT NULL, reason VARCHAR(2000) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0), cash_reconciliation_note VARCHAR(2000) NULL, created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(operation_id) REFERENCES sync_operations(id), FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER offline_device_controls_key_immutable BEFORE UPDATE ON offline_device_controls FOR EACH ROW SET NEW.public_key=OLD.public_key;
CREATE TRIGGER offline_sale_payloads_no_update BEFORE UPDATE ON offline_sale_payloads FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable offline submission';
CREATE TRIGGER offline_sale_payloads_no_delete BEFORE DELETE ON offline_sale_payloads FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable offline submission';
CREATE TRIGGER offline_sale_reviews_no_update BEFORE UPDATE ON offline_sale_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable offline review';
CREATE TRIGGER offline_sale_reviews_no_delete BEFORE DELETE ON offline_sale_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable offline review';

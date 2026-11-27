-- Stage 4 physical custody, independent of financial position consumption.
CREATE TABLE hybrid_custody_receipts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 source_location_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0),
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(id,invoice_id),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_seals(invoice_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(source_location_id) REFERENCES stock_locations(id)
) ENGINE=InnoDB;
CREATE TABLE hybrid_custody_events (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 command_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 custody_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version BIGINT UNSIGNED NOT NULL CHECK(version>0),
 actor_user_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(25) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_json JSON NOT NULL,
 stock_transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0),
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(custody_id,version),
 FOREIGN KEY(custody_id) REFERENCES hybrid_custody_receipts(id),
 FOREIGN KEY(command_id) REFERENCES hybrid_return_commands(request_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(stock_transaction_id) REFERENCES inventory_transactions(id),
 CHECK(event_type IN ('RECEIVED','SUPPLEMENT','ACCEPTED','REVIEW','REJECT','RECLASSIFY','RELEASE','DISPOSE'))
) ENGINE=InnoDB;
CREATE TABLE hybrid_custody_lines (
 event_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 custody_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 paid_saleable BIGINT NOT NULL CHECK(paid_saleable>=0),
 paid_quarantine BIGINT NOT NULL CHECK(paid_quarantine>=0),
 paid_unsaleable BIGINT NOT NULL CHECK(paid_unsaleable>=0),
 free_saleable BIGINT NOT NULL CHECK(free_saleable>=0),
 free_quarantine BIGINT NOT NULL CHECK(free_quarantine>=0),
 free_unsaleable BIGINT NOT NULL CHECK(free_unsaleable>=0),
 holding_reference VARCHAR(200) NOT NULL CHECK(CHAR_LENGTH(TRIM(holding_reference))>0),
 condition_reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(condition_reason))>0),
 PRIMARY KEY(event_id,line_number),
 FOREIGN KEY(event_id) REFERENCES hybrid_custody_events(id),
 FOREIGN KEY(custody_id,invoice_id) REFERENCES hybrid_custody_receipts(id,invoice_id),
 FOREIGN KEY(invoice_id,line_number) REFERENCES hybrid_invoice_line_snapshots(invoice_id,line_number),
 FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB;
CREATE TABLE hybrid_custody_state (
 custody_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 version BIGINT UNSIGNED NOT NULL CHECK(version>0),
 status VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK(status IN ('PENDING','REJECTED','ACCEPTED')),
 accepted_return_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 projection_json JSON NOT NULL,
 FOREIGN KEY(custody_id) REFERENCES hybrid_custody_receipts(id),
 FOREIGN KEY(accepted_return_id) REFERENCES hybrid_returns(id)
) ENGINE=InnoDB;
ALTER TABLE hybrid_returns ADD CONSTRAINT hybrid_return_custody_fk FOREIGN KEY(custody_id) REFERENCES hybrid_custody_receipts(id);
ALTER TABLE hybrid_return_state ADD CONSTRAINT hybrid_pending_custody_fk FOREIGN KEY(pending_custody_id) REFERENCES hybrid_custody_receipts(id);
CREATE TRIGGER hybrid_custody_immutable_0_update BEFORE UPDATE ON hybrid_custody_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_immutable_0_delete BEFORE DELETE ON hybrid_custody_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_immutable_1_update BEFORE UPDATE ON hybrid_custody_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_immutable_1_delete BEFORE DELETE ON hybrid_custody_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_immutable_2_update BEFORE UPDATE ON hybrid_custody_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_immutable_2_delete BEFORE DELETE ON hybrid_custody_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid custody evidence';
CREATE TRIGGER hybrid_custody_intake_open BEFORE INSERT ON hybrid_custody_lines FOR EACH ROW SET NEW.event_id=(SELECT e.id FROM hybrid_custody_events e LEFT JOIN hybrid_return_results r ON r.request_id=e.command_id WHERE e.id=NEW.event_id AND r.request_id IS NULL FOR UPDATE);


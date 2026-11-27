-- Stage 2B PREPARED ONLY. Do not execute without separate DDL authorization.
-- Additive Hybrid sale foundations only. Linked returns and custody DDL are later scope.
CREATE TABLE hybrid_operating_state (
 id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1),
 enabled BOOLEAN NOT NULL DEFAULT FALSE CHECK(enabled IN (0,1)),
 sale_posting BOOLEAN NOT NULL DEFAULT FALSE CHECK(sale_posting IN (0,1)),
 return_posting BOOLEAN NOT NULL DEFAULT FALSE CHECK(return_posting IN (0,1)),
 custody BOOLEAN NOT NULL DEFAULT FALSE CHECK(custody IN (0,1)),
 version BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK(version>0)
) ENGINE=InnoDB;
INSERT INTO hybrid_operating_state(id) VALUES (1);
CREATE TABLE hybrid_control_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 previous_version BIGINT UNSIGNED NOT NULL,
 new_version BIGINT UNSIGNED NOT NULL UNIQUE,
 reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0),
 previous_json JSON NOT NULL,
 new_json JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 CHECK(new_version=previous_version+1)
) ENGINE=InnoDB;
CREATE TABLE hybrid_commands (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 operation VARCHAR(40) CHARACTER SET ascii NOT NULL CHECK(operation='HYBRID_SALE_V1'),
 actor_user_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 CHECK(request_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'),
 CHECK(payload_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB;
CREATE TABLE hybrid_invoice_snapshots (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 policy_version VARCHAR(40) CHARACTER SET ascii NOT NULL CHECK(policy_version='HYBRID_PROMOTION_V1'),
 currency CHAR(3) CHARACTER SET ascii NOT NULL CHECK(currency='LKR'),
 configuration_version VARCHAR(80) CHARACTER SET ascii NOT NULL,
 rounding_version VARCHAR(40) CHARACTER SET ascii NOT NULL CHECK(rounding_version='HYBRID_CENTS_V1'),
 allocation_version VARCHAR(40) CHARACTER SET ascii NOT NULL CHECK(allocation_version='HYBRID_UNITS_B1_V1'),
 actor_user_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 source_location_id BIGINT UNSIGNED NOT NULL,
 business_date DATE NOT NULL,
 original_subtotal DECIMAL(15,2) NOT NULL CHECK(original_subtotal>=0),
 product_discount DECIMAL(15,2) NOT NULL CHECK(product_discount>=0),
 replacement_discount DECIMAL(15,2) NOT NULL CHECK(replacement_discount>=0),
 invoice_discount_base DECIMAL(15,2) NOT NULL CHECK(invoice_discount_base>=0),
 invoice_percent DECIMAL(5,2) NOT NULL CHECK(invoice_percent BETWEEN 0 AND 50),
 invoice_discount DECIMAL(15,2) NOT NULL CHECK(invoice_discount>=0),
 combined_discount DECIMAL(15,2) NOT NULL CHECK(combined_discount>=0),
 payable DECIMAL(15,2) NOT NULL CHECK(payable>=0),
 review_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 snapshot_json JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY hybrid_customer_date(customer_id,business_date),
 FOREIGN KEY(invoice_id) REFERENCES invoices(id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 FOREIGN KEY(source_location_id) REFERENCES stock_locations(id),
 CHECK(combined_discount=product_discount+replacement_discount+invoice_discount),
 CHECK(invoice_discount_base=original_subtotal-product_discount-replacement_discount),
 CHECK(payable=original_subtotal-combined_discount),
 CHECK(2*combined_discount<=original_subtotal)
) ENGINE=InnoDB;
CREATE TABLE hybrid_invoice_line_snapshots (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 price_version BIGINT UNSIGNED NOT NULL CHECK(price_version>0),
 promotion_revision_id BIGINT UNSIGNED NULL,
 paid_quantity BIGINT NOT NULL CHECK(paid_quantity>0),
 qualifying_quantity BIGINT NOT NULL CHECK(qualifying_quantity>=0),
 remainder_quantity BIGINT NOT NULL CHECK(remainder_quantity>=0),
 free_entitlement BIGINT NOT NULL CHECK(free_entitlement>=0),
 actual_free_quantity BIGINT NOT NULL CHECK(actual_free_quantity>=0),
 substituted_quantity BIGINT NOT NULL CHECK(substituted_quantity>=0),
 product_percent DECIMAL(5,2) NOT NULL CHECK(product_percent BETWEEN 0 AND 50),
 replacement_percent DECIMAL(5,2) NULL CHECK(replacement_percent BETWEEN 0 AND 100),
 gross_amount DECIMAL(15,2) NOT NULL CHECK(gross_amount>=0),
 product_discount_amount DECIMAL(15,2) NOT NULL CHECK(product_discount_amount>=0),
 replacement_discount_amount DECIMAL(15,2) NOT NULL CHECK(replacement_discount_amount>=0),
 invoice_discount_amount DECIMAL(15,2) NOT NULL CHECK(invoice_discount_amount>=0),
 payable_amount DECIMAL(15,2) NOT NULL CHECK(payable_amount>=0),
 snapshot_json JSON NOT NULL,
 PRIMARY KEY(invoice_id,line_number),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_snapshots(invoice_id),
 FOREIGN KEY(invoice_id,line_number) REFERENCES invoice_items(invoice_id,line_number),
 FOREIGN KEY(product_id) REFERENCES products(id),
 FOREIGN KEY(promotion_revision_id) REFERENCES promotion_versions(id),
 CHECK(paid_quantity=qualifying_quantity+remainder_quantity),
 CHECK(free_entitlement=actual_free_quantity+substituted_quantity),
 CHECK(substituted_quantity=0 OR (actual_free_quantity=0 AND remainder_quantity=0 AND product_discount_amount=0 AND substituted_quantity=free_entitlement)),
 CHECK(replacement_discount_amount=0 OR substituted_quantity>0),
 CHECK(payable_amount=gross_amount-product_discount_amount-replacement_discount_amount-invoice_discount_amount)
) ENGINE=InnoDB;
CREATE TABLE hybrid_invoice_segments (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 segment_number INT UNSIGNED NOT NULL,
 kind VARCHAR(30) CHARACTER SET ascii NOT NULL,
 return_priority TINYINT UNSIGNED NOT NULL,
 paid_quantity BIGINT NOT NULL CHECK(paid_quantity>0),
 gross_amount DECIMAL(15,2) NOT NULL CHECK(gross_amount>=0),
 product_discount_amount DECIMAL(15,2) NOT NULL CHECK(product_discount_amount>=0),
 replacement_discount_amount DECIMAL(15,2) NOT NULL CHECK(replacement_discount_amount>=0),
 invoice_discount_amount DECIMAL(15,2) NOT NULL CHECK(invoice_discount_amount>=0),
 payable_amount DECIMAL(15,2) NOT NULL CHECK(payable_amount>=0),
 PRIMARY KEY(invoice_id,line_number,segment_number),
 FOREIGN KEY(invoice_id,line_number) REFERENCES hybrid_invoice_line_snapshots(invoice_id,line_number),
 CHECK((kind='DISCOUNT_ELIGIBLE' AND return_priority=0) OR (kind='PROMOTION_QUALIFIED' AND return_priority=1)),
 CHECK(payable_amount=gross_amount-product_discount_amount-replacement_discount_amount-invoice_discount_amount)
) ENGINE=InnoDB;
CREATE TABLE hybrid_unit_allocation_runs (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 segment_number INT UNSIGNED NOT NULL,
 position_start BIGINT NOT NULL CHECK(position_start>=0),
 position_count BIGINT NOT NULL CHECK(position_count>0),
 gross_amount DECIMAL(15,2) NOT NULL CHECK(gross_amount>=0),
 product_discount_amount DECIMAL(15,2) NOT NULL CHECK(product_discount_amount>=0),
 replacement_discount_amount DECIMAL(15,2) NOT NULL CHECK(replacement_discount_amount>=0),
 invoice_discount_amount DECIMAL(15,2) NOT NULL CHECK(invoice_discount_amount>=0),
 payable_amount DECIMAL(15,2) NOT NULL CHECK(payable_amount>=0),
 PRIMARY KEY(invoice_id,line_number,segment_number,position_start),
 FOREIGN KEY(invoice_id,line_number,segment_number) REFERENCES hybrid_invoice_segments(invoice_id,line_number,segment_number),
 CHECK(CAST(position_start AS DECIMAL(20,0))+position_count<=9223372036854775807),
 CHECK(payable_amount=gross_amount-product_discount_amount-replacement_discount_amount-invoice_discount_amount)
) ENGINE=InnoDB;
CREATE TABLE hybrid_invoice_seals (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK(digest REGEXP '^[0-9a-f]{64}$'),
 line_count INT UNSIGNED NOT NULL CHECK(line_count>0),
 segment_count INT UNSIGNED NOT NULL CHECK(segment_count>0),
 run_count INT UNSIGNED NOT NULL CHECK(run_count>0),
 allocation_version VARCHAR(40) CHARACTER SET ascii NOT NULL CHECK(allocation_version='HYBRID_UNITS_B1_V1'),
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_snapshots(invoice_id)
) ENGINE=InnoDB;
CREATE TABLE hybrid_command_results (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 result_json JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(request_id) REFERENCES hybrid_commands(request_id),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_seals(invoice_id)
) ENGINE=InnoDB;
CREATE TRIGGER hybrid_immutable_0_update BEFORE UPDATE ON hybrid_control_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_0_delete BEFORE DELETE ON hybrid_control_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_1_update BEFORE UPDATE ON hybrid_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_1_delete BEFORE DELETE ON hybrid_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_2_update BEFORE UPDATE ON hybrid_command_results FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_2_delete BEFORE DELETE ON hybrid_command_results FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_3_update BEFORE UPDATE ON hybrid_invoice_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_3_delete BEFORE DELETE ON hybrid_invoice_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_4_update BEFORE UPDATE ON hybrid_invoice_line_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_4_delete BEFORE DELETE ON hybrid_invoice_line_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_5_update BEFORE UPDATE ON hybrid_invoice_segments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_5_delete BEFORE DELETE ON hybrid_invoice_segments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_6_update BEFORE UPDATE ON hybrid_unit_allocation_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_6_delete BEFORE DELETE ON hybrid_unit_allocation_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_7_update BEFORE UPDATE ON hybrid_invoice_seals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_immutable_7_delete BEFORE DELETE ON hybrid_invoice_seals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid financial evidence';
CREATE TRIGGER hybrid_child_open_0 BEFORE INSERT ON hybrid_invoice_line_snapshots FOR EACH ROW SET NEW.invoice_id=(SELECT h.invoice_id FROM hybrid_invoice_snapshots h LEFT JOIN hybrid_invoice_seals s ON s.invoice_id=h.invoice_id WHERE h.invoice_id=NEW.invoice_id AND s.invoice_id IS NULL FOR UPDATE);
CREATE TRIGGER hybrid_child_open_1 BEFORE INSERT ON hybrid_invoice_segments FOR EACH ROW SET NEW.invoice_id=(SELECT h.invoice_id FROM hybrid_invoice_snapshots h LEFT JOIN hybrid_invoice_seals s ON s.invoice_id=h.invoice_id WHERE h.invoice_id=NEW.invoice_id AND s.invoice_id IS NULL FOR UPDATE);
CREATE TRIGGER hybrid_child_open_2 BEFORE INSERT ON hybrid_unit_allocation_runs FOR EACH ROW SET NEW.invoice_id=(SELECT h.invoice_id FROM hybrid_invoice_snapshots h LEFT JOIN hybrid_invoice_seals s ON s.invoice_id=h.invoice_id WHERE h.invoice_id=NEW.invoice_id AND s.invoice_id IS NULL FOR UPDATE);
CREATE TRIGGER hybrid_seal_parent_lock BEFORE INSERT ON hybrid_invoice_seals FOR EACH ROW SET NEW.invoice_id=(SELECT h.invoice_id FROM hybrid_invoice_snapshots h WHERE h.invoice_id=NEW.invoice_id FOR UPDATE);


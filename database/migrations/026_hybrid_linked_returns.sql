-- Stage 4 additive linked Hybrid returns. Operational execution is not authorized.
CREATE TABLE hybrid_return_commands (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 operation VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_seals(invoice_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 CHECK(operation IN ('RETURN','VOID','CUSTODY_RECEIVE','CUSTODY_SUPPLEMENT','CUSTODY_MANAGE')),
 CHECK(request_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
) ENGINE=InnoDB;
CREATE TABLE hybrid_returns (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision BIGINT UNSIGNED NOT NULL CHECK(revision>0),
 actor_user_id BIGINT UNSIGNED NOT NULL,
 custody_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 stock_transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 policy_version VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK(policy_version='HYBRID_RETURN_V1'),
 operation VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL CHECK(operation IN ('RETURN','VOID')),
 gross_amount DECIMAL(15,2) NOT NULL CHECK(gross_amount>=0),
 product_discount_amount DECIMAL(15,2) NOT NULL CHECK(product_discount_amount>=0),
 replacement_discount_amount DECIMAL(15,2) NOT NULL CHECK(replacement_discount_amount>=0),
 invoice_discount_amount DECIMAL(15,2) NOT NULL CHECK(invoice_discount_amount>=0),
 net_credit DECIMAL(15,2) NOT NULL CHECK(net_credit>=0),
 cash_at_return DECIMAL(15,2) NOT NULL CHECK(cash_at_return>=0),
 debt_reduction DECIMAL(15,2) NOT NULL CHECK(debt_reduction>=0),
 refund_increment DECIMAL(15,2) NOT NULL CHECK(refund_increment>=0),
 retained_gross DECIMAL(15,2) NOT NULL CHECK(retained_gross>=0),
 retained_discount DECIMAL(15,2) NOT NULL CHECK(retained_discount>=0),
 reason VARCHAR(500) NOT NULL CHECK(CHAR_LENGTH(TRIM(reason))>0),
 review_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 plan_json JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE(invoice_id,revision), UNIQUE(id,invoice_id),
 FOREIGN KEY(id) REFERENCES hybrid_return_commands(request_id),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_seals(invoice_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(stock_transaction_id) REFERENCES inventory_transactions(id),
 CHECK(net_credit=gross_amount-product_discount_amount-replacement_discount_amount-invoice_discount_amount),
 CHECK(net_credit=debt_reduction+refund_increment),
 CHECK(2*retained_discount<=retained_gross)
) ENGINE=InnoDB;
CREATE TABLE hybrid_return_lines (
 return_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 paid_quantity BIGINT NOT NULL CHECK(paid_quantity>=0),
 free_quantity BIGINT NOT NULL CHECK(free_quantity>=0),
 free_position_start BIGINT NOT NULL CHECK(free_position_start>=0),
 receipt_json JSON NOT NULL,
 PRIMARY KEY(return_id,line_number),
 FOREIGN KEY(return_id,invoice_id) REFERENCES hybrid_returns(id,invoice_id),
 FOREIGN KEY(invoice_id,line_number) REFERENCES hybrid_invoice_line_snapshots(invoice_id,line_number),
 CHECK(paid_quantity>0 OR free_quantity>0)
) ENGINE=InnoDB;
CREATE TABLE hybrid_return_allocations (
 return_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
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
 PRIMARY KEY(return_id,line_number,segment_number,position_start),
 UNIQUE(invoice_id,line_number,segment_number,position_start),
 FOREIGN KEY(return_id,line_number) REFERENCES hybrid_return_lines(return_id,line_number),
 FOREIGN KEY(invoice_id,line_number,segment_number) REFERENCES hybrid_invoice_segments(invoice_id,line_number,segment_number),
 CHECK(payable_amount=gross_amount-product_discount_amount-replacement_discount_amount-invoice_discount_amount)
) ENGINE=InnoDB;
CREATE TABLE hybrid_voids (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 return_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(return_id,invoice_id) REFERENCES hybrid_returns(id,invoice_id)
) ENGINE=InnoDB;
CREATE TABLE hybrid_return_state (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
 credit DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK(credit>=0),
 debt_reduction DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK(debt_reduction>=0),
 refund_due DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK(refund_due>=0),
 pending_custody_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK(version>0),
 FOREIGN KEY(invoice_id) REFERENCES hybrid_invoice_seals(invoice_id),
 CHECK(credit=debt_reduction+refund_due)
) ENGINE=InnoDB;
CREATE TABLE hybrid_segment_cursors (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 segment_number INT UNSIGNED NOT NULL,
 consumed_positions BIGINT NOT NULL CHECK(consumed_positions>=0),
 PRIMARY KEY(invoice_id,line_number,segment_number),
 FOREIGN KEY(invoice_id,line_number,segment_number) REFERENCES hybrid_invoice_segments(invoice_id,line_number,segment_number)
) ENGINE=InnoDB;
CREATE TABLE hybrid_return_results (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 result_json JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(request_id) REFERENCES hybrid_return_commands(request_id)
) ENGINE=InnoDB;
CREATE TRIGGER hybrid_return_immutable_0_update BEFORE UPDATE ON hybrid_return_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_0_delete BEFORE DELETE ON hybrid_return_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_1_update BEFORE UPDATE ON hybrid_returns FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_1_delete BEFORE DELETE ON hybrid_returns FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_2_update BEFORE UPDATE ON hybrid_return_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_2_delete BEFORE DELETE ON hybrid_return_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_3_update BEFORE UPDATE ON hybrid_return_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_3_delete BEFORE DELETE ON hybrid_return_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_4_update BEFORE UPDATE ON hybrid_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_4_delete BEFORE DELETE ON hybrid_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_5_update BEFORE UPDATE ON hybrid_return_results FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_immutable_5_delete BEFORE DELETE ON hybrid_return_results FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Hybrid return evidence';
CREATE TRIGGER hybrid_return_children_open_0 BEFORE INSERT ON hybrid_return_lines FOR EACH ROW SET NEW.return_id=(SELECT r.id FROM hybrid_returns r LEFT JOIN hybrid_return_results c ON c.request_id=r.id WHERE r.id=NEW.return_id AND c.request_id IS NULL FOR UPDATE);
CREATE TRIGGER hybrid_return_children_open_1 BEFORE INSERT ON hybrid_return_allocations FOR EACH ROW SET NEW.return_id=(SELECT r.id FROM hybrid_returns r LEFT JOIN hybrid_return_results c ON c.request_id=r.id WHERE r.id=NEW.return_id AND c.request_id IS NULL FOR UPDATE);


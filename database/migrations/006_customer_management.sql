ALTER TABLE customers ADD COLUMN customer_code VARCHAR(64) NULL UNIQUE, ADD COLUMN alternate_phone VARCHAR(40) NULL, ADD COLUMN phone_normalized VARCHAR(15) NULL, ADD COLUMN alternate_phone_normalized VARCHAR(15) NULL, ADD COLUMN city_town VARCHAR(100) NOT NULL DEFAULT '', ADD COLUMN district VARCHAR(100) NOT NULL DEFAULT '', ADD COLUMN customer_type ENUM('PHARMACY','AYURVEDA_SHOP','GENERAL_RETAIL','DISTRIBUTOR','OTHER') NOT NULL DEFAULT 'OTHER', ADD COLUMN notes TEXT NULL, ADD COLUMN credit_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0 CHECK (credit_terms_days <= 3650), ADD COLUMN credit_limit_enabled BOOLEAN NOT NULL DEFAULT TRUE CHECK (credit_limit_enabled IN (0,1)), ADD INDEX customers_phone_normalized (phone_normalized), ADD INDEX customers_alternate_phone_normalized (alternate_phone_normalized);
UPDATE customers SET customer_code=CONCAT('LEGACY-',id);
ALTER TABLE customers MODIFY COLUMN customer_code VARCHAR(64) NOT NULL;
CREATE TABLE customer_code_history (
 customer_code VARCHAR(64) PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 recorded_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO customer_code_history(customer_code,customer_id) SELECT customer_code,id FROM customers;
CREATE TRIGGER customer_code_history_no_update BEFORE UPDATE ON customer_code_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Customer code history is immutable';
CREATE TRIGGER customer_code_history_no_delete BEFORE DELETE ON customer_code_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Customer code history is immutable';
CREATE TABLE customer_phone_locks (phone_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY) ENGINE=InnoDB;

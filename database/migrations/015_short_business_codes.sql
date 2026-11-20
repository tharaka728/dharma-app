CREATE TABLE business_code_counters (
    prefix CHAR(1) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT ck_business_code_next CHECK (next_number BETWEEN 1 AND 999999999999999999)
) ENGINE=InnoDB;
INSERT INTO business_code_counters(prefix,next_number) VALUES ('P',1),('C',1),('S',1),('A',1),('M',1),('R',1);
UPDATE business_code_counters SET next_number=GREATEST(next_number,1+(SELECT COALESCE(MAX(CAST(SUBSTRING(sku,2) AS UNSIGNED)),0) FROM product_sku_history WHERE sku REGEXP '^P[0-9]{1,18}$')) WHERE prefix='P';
UPDATE business_code_counters SET next_number=GREATEST(next_number,1+(SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code,2) AS UNSIGNED)),0) FROM customer_code_history WHERE customer_code REGEXP '^C[0-9]{1,18}$')) WHERE prefix='C';
CREATE TABLE business_code_registry (
    prefix CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sequence_number BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY(prefix,request_key),
    UNIQUE KEY uq_business_code(code),
    UNIQUE KEY uq_business_code_sequence(prefix,sequence_number),
    CONSTRAINT fk_business_code_counter FOREIGN KEY(prefix) REFERENCES business_code_counters(prefix),
    CONSTRAINT ck_business_code_sequence CHECK(sequence_number>0)
) ENGINE=InnoDB;
CREATE TRIGGER business_code_no_update BEFORE UPDATE ON business_code_registry FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Business code reservations are immutable';
CREATE TRIGGER business_code_no_delete BEFORE DELETE ON business_code_registry FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Business codes cannot be recycled';

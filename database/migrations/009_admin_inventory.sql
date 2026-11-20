ALTER TABLE product_batch_versions MODIFY manufacturing_date DATE NULL;
CREATE TABLE inventory_settings (
 id TINYINT UNSIGNED PRIMARY KEY CHECK (id=1), warehouse_location_id BIGINT UNSIGNED NULL,
 near_expiry_days SMALLINT UNSIGNED NOT NULL DEFAULT 90 CHECK (near_expiry_days<=3650),
 version BIGINT UNSIGNED NOT NULL DEFAULT 1, FOREIGN KEY (warehouse_location_id) REFERENCES stock_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO inventory_settings(id) VALUES (1);
CREATE TABLE inventory_product_settings (
 product_id BIGINT UNSIGNED PRIMARY KEY, low_stock_threshold BIGINT NULL CHECK (low_stock_threshold>=0),
 version BIGINT UNSIGNED NOT NULL DEFAULT 1, FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE inventory_batch_controls (
 batch_id BIGINT UNSIGNED PRIMARY KEY, status ENUM('SALEABLE','QUARANTINED','BLOCKED','RECALLED') NOT NULL DEFAULT 'QUARANTINED',
 acquisition_info VARCHAR(1000) NOT NULL DEFAULT '', version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_by_user_id BIGINT UNSIGNED NOT NULL, updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (batch_id) REFERENCES product_batches(id), FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE inventory_transactions
 ADD payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD receipt_date DATE NULL, ADD supplier VARCHAR(200) NOT NULL DEFAULT '',
 ADD reference VARCHAR(200) NOT NULL DEFAULT '', ADD unit_cost DECIMAL(15,2) NULL CHECK (unit_cost>=0),
 ADD notes VARCHAR(2000) NOT NULL DEFAULT '',
 ADD CHECK (payload_hash IS NULL OR type<>'RECEIPT' OR receipt_date IS NOT NULL);
CREATE INDEX inventory_transactions_posted ON inventory_transactions(posted_at_utc,id);

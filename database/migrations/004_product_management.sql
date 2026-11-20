ALTER TABLE products ADD COLUMN unit_of_measure VARCHAR(40) NOT NULL DEFAULT 'unit', ADD COLUMN description TEXT NULL, MODIFY COLUMN cost_price DECIMAL(15,2) NULL;
CREATE TABLE product_sku_history (
 sku VARCHAR(100) NOT NULL PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 recorded_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO product_sku_history(sku,product_id) SELECT sku,id FROM products;
CREATE TRIGGER product_sku_history_no_update BEFORE UPDATE ON product_sku_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SKU history is immutable';
CREATE TRIGGER product_sku_history_no_delete BEFORE DELETE ON product_sku_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SKU history is immutable';

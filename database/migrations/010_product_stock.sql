ALTER TABLE inventory_settings ADD stock_model ENUM('BATCH','PRODUCT') NOT NULL DEFAULT 'BATCH';
CREATE TABLE product_stock_balances (
 product_id BIGINT UNSIGNED PRIMARY KEY, quantity BIGINT NOT NULL DEFAULT 0 CHECK (quantity>=0),
 version BIGINT UNSIGNED NOT NULL DEFAULT 1, updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE inventory_movements MODIFY batch_id BIGINT UNSIGNED NULL, MODIFY batch_version_id BIGINT UNSIGNED NULL,
 ADD FOREIGN KEY (product_id) REFERENCES products(id),
 ADD CHECK ((batch_id IS NULL AND batch_version_id IS NULL) OR (batch_id IS NOT NULL AND batch_version_id IS NOT NULL));
ALTER TABLE inventory_transactions ADD selling_price_snapshot DECIMAL(15,2) NULL CHECK (selling_price_snapshot>=0);
INSERT INTO stock_locations(type,code) SELECT 'WAREHOUSE','CENTRAL' FROM inventory_settings WHERE id=1 AND warehouse_location_id IS NULL AND NOT EXISTS (SELECT 1 FROM stock_locations WHERE code='CENTRAL');
UPDATE inventory_settings s JOIN stock_locations l ON l.code='CENTRAL' AND l.type='WAREHOUSE' AND l.is_active=1 SET s.warehouse_location_id=l.id WHERE s.id=1 AND s.warehouse_location_id IS NULL;
UPDATE inventory_settings SET stock_model='PRODUCT',version=version+1 WHERE id=1 AND warehouse_location_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_balances WHERE quantity<>0) AND NOT EXISTS (SELECT 1 FROM inventory_movements);

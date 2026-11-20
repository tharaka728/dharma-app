ALTER TABLE users ADD phone VARCHAR(40) NOT NULL DEFAULT '', ADD assigned_area VARCHAR(150) NOT NULL DEFAULT '';
CREATE TABLE representative_stock_balances (
 rep_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 quantity BIGINT NOT NULL DEFAULT 0 CHECK (quantity>=0), version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(rep_id,product_id),
 FOREIGN KEY (rep_id) REFERENCES users(id), FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE product_stock_transfer_items (
 transfer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 quantity BIGINT NOT NULL CHECK (quantity>0), PRIMARY KEY(transfer_id,product_id),
 FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id), FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER product_stock_transfer_items_no_update BEFORE UPDATE ON product_stock_transfer_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable record: append a correction instead';
CREATE TRIGGER product_stock_transfer_items_no_delete BEFORE DELETE ON product_stock_transfer_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable record: append a correction instead';

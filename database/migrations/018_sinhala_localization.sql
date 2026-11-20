ALTER TABLE products ADD COLUMN name_si VARCHAR(200) NULL;
CREATE TABLE invoice_language_snapshots (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 default_locale VARCHAR(5) CHARACTER SET ascii NOT NULL CHECK(default_locale IN ('si-LK','en')),
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_product_language_snapshots (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 name_si VARCHAR(200) NULL,
 PRIMARY KEY(invoice_id,product_id),
 FOREIGN KEY(invoice_id,product_id) REFERENCES invoice_items(invoice_id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER invoice_language_no_update BEFORE UPDATE ON invoice_language_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice language snapshots are immutable';
CREATE TRIGGER invoice_language_no_delete BEFORE DELETE ON invoice_language_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice language snapshots cannot be deleted';
CREATE TRIGGER invoice_product_language_no_update BEFORE UPDATE ON invoice_product_language_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice product language snapshots are immutable';
CREATE TRIGGER invoice_product_language_no_delete BEFORE DELETE ON invoice_product_language_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice product language snapshots cannot be deleted';

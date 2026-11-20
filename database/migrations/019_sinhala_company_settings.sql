CREATE TABLE company_language_settings (
 id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1),
 name_si VARCHAR(200) NULL,address_si VARCHAR(1000) NULL,footer_si VARCHAR(1000) NULL,
 approved TINYINT(1) NOT NULL DEFAULT 0 CHECK(approved IN (0,1)),
 version BIGINT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO company_language_settings(id) VALUES(1);
CREATE TABLE invoice_company_snapshots (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 details JSON NOT NULL,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER invoice_company_no_update BEFORE UPDATE ON invoice_company_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice company snapshots are immutable';
CREATE TRIGGER invoice_company_no_delete BEFORE DELETE ON invoice_company_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice company snapshots cannot be deleted';

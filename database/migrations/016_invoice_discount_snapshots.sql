CREATE TABLE invoice_discount_snapshots (
    invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    percent_rate DECIMAL(5,2) NOT NULL,
    discountable_subtotal DECIMAL(15,2) NOT NULL,
    discount_amount DECIMAL(15,2) NOT NULL,
    policy_version VARCHAR(40) CHARACTER SET ascii NOT NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_invoice_discount_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id),
    CONSTRAINT ck_invoice_discount_rate CHECK(percent_rate BETWEEN 0 AND 100),
    CONSTRAINT ck_invoice_discount_amount CHECK(discountable_subtotal>=0 AND discount_amount BETWEEN 0 AND discountable_subtotal),
    CONSTRAINT ck_invoice_discount_policy CHECK(policy_version='INVOICE_PERCENT_V1')
) ENGINE=InnoDB;
CREATE TRIGGER invoice_discount_no_update BEFORE UPDATE ON invoice_discount_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice discount snapshots are immutable';
CREATE TRIGGER invoice_discount_no_delete BEFORE DELETE ON invoice_discount_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice discount snapshots cannot be deleted';

CREATE TABLE invoice_benefit_snapshots (
    invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    policy_version VARCHAR(40) CHARACTER SET ascii NOT NULL,
    benefit_mode VARCHAR(40) CHARACTER SET ascii NOT NULL,
    percent_rate DECIMAL(5,2) NOT NULL,
    eligible_subtotal DECIMAL(15,2) NOT NULL,
    discount_amount DECIMAL(15,2) NOT NULL,
    suppressed_free_quantity BIGINT NOT NULL,
    suppressed_monetary_discount DECIMAL(15,2) NOT NULL,
    promotion_revisions JSON NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_invoice_benefit_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_invoice_benefit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id),
    CONSTRAINT ck_invoice_benefit_policy CHECK(policy_version='PROMO_CHOICE_V1'),
    CONSTRAINT ck_invoice_benefit_mode CHECK(benefit_mode IN ('FREE_PROMOTION','PROMOTION_DISCOUNT_INSTEAD','ORDINARY_INVOICE_DISCOUNT')),
    CONSTRAINT ck_invoice_benefit_rate CHECK(percent_rate BETWEEN 0 AND 100),
    CONSTRAINT ck_invoice_benefit_amounts CHECK(eligible_subtotal>=0 AND discount_amount BETWEEN 0 AND eligible_subtotal AND suppressed_free_quantity>=0 AND suppressed_monetary_discount>=0)
) ENGINE=InnoDB;
CREATE TABLE invoice_benefit_line_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    line_number INT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    paid_quantity BIGINT NOT NULL,
    actual_free_quantity BIGINT NOT NULL,
    qualifying_promotion_id BIGINT UNSIGNED NULL,
    promotion_revision_id BIGINT UNSIGNED NULL,
    promotion_threshold BIGINT NULL,
    eligible BOOLEAN NOT NULL,
    eligibility_reason VARCHAR(200) CHARACTER SET ascii NOT NULL,
    eligible_subtotal DECIMAL(15,2) NOT NULL,
    allocated_discount DECIMAL(15,2) NOT NULL,
    suppressed_free_quantity BIGINT NOT NULL,
    suppressed_monetary_discount DECIMAL(15,2) NOT NULL,
    created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uk_invoice_benefit_line(invoice_id,line_number),
    CONSTRAINT fk_invoice_benefit_line_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id),
    CONSTRAINT ck_invoice_benefit_line_amounts CHECK(paid_quantity>=0 AND actual_free_quantity>=0 AND eligible_subtotal>=0 AND allocated_discount BETWEEN 0 AND eligible_subtotal AND suppressed_free_quantity>=0 AND suppressed_monetary_discount>=0)
) ENGINE=InnoDB;
CREATE TRIGGER invoice_benefit_no_update BEFORE UPDATE ON invoice_benefit_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice benefit snapshots are immutable';
CREATE TRIGGER invoice_benefit_no_delete BEFORE DELETE ON invoice_benefit_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice benefit snapshots cannot be deleted';
CREATE TRIGGER invoice_benefit_line_no_update BEFORE UPDATE ON invoice_benefit_line_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice benefit line snapshots are immutable';
CREATE TRIGGER invoice_benefit_line_no_delete BEFORE DELETE ON invoice_benefit_line_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invoice benefit line snapshots cannot be deleted';

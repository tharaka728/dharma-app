CREATE TABLE historical_customer_returns (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 approved_value DECIMAL(15,2) NOT NULL,
 applied_value DECIMAL(15,2) NOT NULL,
 forfeited_value DECIMAL(15,2) NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES invoices(id),
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 CHECK(CHAR_LENGTH(TRIM(reason))>0),
 CHECK(approved_value>=0 AND applied_value>=0 AND forfeited_value>=0 AND approved_value=applied_value+forfeited_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE historical_customer_return_lines (
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 price_revision_id BIGINT UNSIGNED NOT NULL,
 price_year SMALLINT UNSIGNED NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 quantity BIGINT UNSIGNED NOT NULL,
 line_value DECIMAL(15,2) NOT NULL,
 disposition VARCHAR(32) NOT NULL DEFAULT 'NON_SALEABLE',
 PRIMARY KEY(invoice_id,line_number),
 UNIQUE KEY return_product_year(invoice_id,product_id,price_year),
 FOREIGN KEY(invoice_id) REFERENCES historical_customer_returns(invoice_id),
 FOREIGN KEY(product_id) REFERENCES products(id),
 FOREIGN KEY(price_revision_id) REFERENCES historical_return_price_revisions(id),
 CHECK(line_number>0 AND quantity>0 AND unit_price>=0 AND line_value=unit_price*quantity),
 CHECK(disposition='NON_SALEABLE')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER historical_return_no_update BEFORE UPDATE ON historical_customer_returns FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical returns are immutable';
CREATE TRIGGER historical_return_no_delete BEFORE DELETE ON historical_customer_returns FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical returns cannot be deleted';
CREATE TRIGGER historical_return_line_no_update BEFORE UPDATE ON historical_customer_return_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return lines are immutable';
CREATE TRIGGER historical_return_line_no_delete BEFORE DELETE ON historical_customer_return_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return lines cannot be deleted';

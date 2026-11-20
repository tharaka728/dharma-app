CREATE TABLE historical_return_identities (
 return_intent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(invoice_id) REFERENCES historical_customer_returns(invoice_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 CHECK(return_intent_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER historical_return_identity_no_update BEFORE UPDATE ON historical_return_identities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return identities are immutable';
CREATE TRIGGER historical_return_identity_no_delete BEFORE DELETE ON historical_return_identities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return identities cannot be deleted';

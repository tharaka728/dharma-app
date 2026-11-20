CREATE TABLE historical_return_intents (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 expires_at_utc DATETIME(6) NOT NULL,
 FOREIGN KEY(actor_user_id) REFERENCES users(id),
 FOREIGN KEY(customer_id) REFERENCES customers(id),
 CHECK(expires_at_utc>created_at_utc),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER historical_return_intent_no_update BEFORE UPDATE ON historical_return_intents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Return intents are immutable';
CREATE TRIGGER historical_return_intent_no_delete BEFORE DELETE ON historical_return_intents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Return intents cannot be deleted';

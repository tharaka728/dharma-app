-- Historical price revisions are append-only and never change current product prices.
CREATE TABLE historical_return_price_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 price_year SMALLINT UNSIGNED NOT NULL,
 revision BIGINT UNSIGNED NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 status ENUM('APPROVED','DISABLED') NOT NULL,
 reason VARCHAR(500) NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY historical_price_revision (product_id,price_year,revision),
 FOREIGN KEY (product_id) REFERENCES products(id),
 FOREIGN KEY (actor_user_id) REFERENCES users(id),
 CHECK (price_year BETWEEN 1900 AND 9999),
 CHECK (revision > 0), CHECK (unit_price >= 0),
 CHECK (CHAR_LENGTH(TRIM(reason)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER historical_return_price_no_update BEFORE UPDATE ON historical_return_price_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return price revisions are immutable';
CREATE TRIGGER historical_return_price_no_delete BEFORE DELETE ON historical_return_price_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical return price revisions cannot be deleted';

ALTER TABLE promotions ADD description VARCHAR(2000) NOT NULL DEFAULT '', ADD version BIGINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE promotion_versions DROP CONSTRAINT CONSTRAINT_2,
 MODIFY type ENUM('PERCENTAGE','BUY_X_GET_Y','FIXED') NOT NULL,
 MODIFY valid_from_utc DATETIME(6) NULL,
 ADD fixed_discount DECIMAL(15,2) NULL,
 ADD start_date DATE NULL, ADD end_date DATE NULL,
 ADD name_snapshot VARCHAR(200) NOT NULL DEFAULT '', ADD description_snapshot VARCHAR(2000) NOT NULL DEFAULT '',
 ADD is_active_snapshot BOOLEAN NOT NULL DEFAULT 1 CHECK (is_active_snapshot IN (0,1)),
 ADD CONSTRAINT promotion_calendar_dates CHECK (start_date IS NULL OR end_date IS NULL OR end_date>=start_date),
 ADD CONSTRAINT promotion_rule_values CHECK (
 (type='PERCENTAGE' AND percent_rate IS NOT NULL AND percent_rate>0 AND percent_rate<=100 AND fixed_discount IS NULL AND buy_quantity IS NULL AND free_quantity IS NULL)
 OR (type='BUY_X_GET_Y' AND percent_rate IS NULL AND fixed_discount IS NULL AND buy_quantity IS NOT NULL AND buy_quantity>0 AND free_quantity IS NOT NULL AND free_quantity>0)
 OR (type='FIXED' AND fixed_discount IS NOT NULL AND fixed_discount>0 AND percent_rate IS NULL AND buy_quantity IS NULL AND free_quantity IS NULL));
CREATE TABLE promotion_write_gate (id TINYINT UNSIGNED PRIMARY KEY CHECK (id=1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO promotion_write_gate(id) VALUES (1);
CREATE TABLE promotion_commands (
 request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NOT NULL, payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 promotion_id BIGINT UNSIGNED NOT NULL, revision_id BIGINT UNSIGNED NOT NULL, result_version BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (actor_user_id) REFERENCES users(id), FOREIGN KEY (promotion_id) REFERENCES promotions(id), FOREIGN KEY (revision_id) REFERENCES promotion_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER promotion_commands_no_update BEFORE UPDATE ON promotion_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable promotion command';
CREATE TRIGGER promotion_commands_no_delete BEFORE DELETE ON promotion_commands FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable promotion command';

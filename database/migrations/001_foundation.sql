CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role ENUM('ADMIN','SALES_REP') NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)), archived_at_utc DATETIME(6) NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), version BIGINT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE devices (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(150) NOT NULL, registered_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), revoked_at_utc DATETIME(6) NULL, last_sync_at_utc DATETIME(6) NULL,
 UNIQUE (id,user_id), FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE rep_operating_state (
 rep_id BIGINT UNSIGNED PRIMARY KEY, primary_device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 inventory_revision BIGINT UNSIGNED NOT NULL DEFAULT 0, updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (rep_id) REFERENCES users(id), FOREIGN KEY (primary_device_id,rep_id) REFERENCES devices(id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO permissions (code,description) VALUES ('sales.manual_item_discount','Explicit permission to apply a capped manual item discount');
CREATE TABLE user_permissions (
 user_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, enabled BOOLEAN NOT NULL DEFAULT FALSE CHECK (enabled IN (0,1)),
 max_discount_percent DECIMAL(5,2) NULL CHECK (max_discount_percent BETWEEN 0 AND 100), granted_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY (user_id,permission_id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (permission_id) REFERENCES permissions(id), FOREIGN KEY (granted_by_user_id) REFERENCES users(id),
 CHECK (enabled = 0 OR max_discount_percent IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, shop_name VARCHAR(200) NOT NULL, contact_person VARCHAR(150) NOT NULL,
 phone VARCHAR(40) NOT NULL, address TEXT NOT NULL, area VARCHAR(150) NOT NULL, assigned_rep_id BIGINT UNSIGNED NULL,
 credit_limit DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (credit_limit >= 0), is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)), archived_at_utc DATETIME(6) NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY (assigned_rep_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL UNIQUE, is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)),
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), version BIGINT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(100) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 cost_price DECIMAL(15,2) NOT NULL CHECK (cost_price >= 0), selling_price DECIMAL(15,2) NOT NULL CHECK (selling_price >= 0),
 is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)), archived_at_utc DATETIME(6) NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE product_batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL,
 batch_number VARCHAR(100) COLLATE utf8mb4_bin NOT NULL, created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (product_id,batch_number), UNIQUE (id,product_id), FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE product_batch_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id BIGINT UNSIGNED NOT NULL, version_number BIGINT UNSIGNED NOT NULL CHECK (version_number > 0),
 manufacturing_date DATE NOT NULL, expiry_date DATE NOT NULL, reason VARCHAR(500) NOT NULL,
 recorded_by_user_id BIGINT UNSIGNED NOT NULL, recorded_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (batch_id,version_number), UNIQUE (id,batch_id), FOREIGN KEY (batch_id) REFERENCES product_batches(id), FOREIGN KEY (recorded_by_user_id) REFERENCES users(id),
 CHECK (expiry_date >= manufacturing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE promotions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)), current_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE promotion_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, promotion_id BIGINT UNSIGNED NOT NULL, version_number BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL, type ENUM('PERCENTAGE','BUY_X_GET_Y') NOT NULL, percent_rate DECIMAL(5,2) NULL,
 buy_quantity BIGINT UNSIGNED NULL, free_quantity BIGINT UNSIGNED NULL, valid_from_utc DATETIME(6) NOT NULL, valid_until_utc DATETIME(6) NULL,
 published_by_user_id BIGINT UNSIGNED NOT NULL, published_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (promotion_id,version_number), FOREIGN KEY (promotion_id) REFERENCES promotions(id), FOREIGN KEY (product_id) REFERENCES products(id), FOREIGN KEY (published_by_user_id) REFERENCES users(id),
 CHECK (valid_until_utc IS NULL OR valid_until_utc > valid_from_utc),
 CHECK ((type='PERCENTAGE' AND percent_rate IS NOT NULL AND percent_rate BETWEEN 0 AND 100 AND buy_quantity IS NULL AND free_quantity IS NULL) OR (type='BUY_X_GET_Y' AND percent_rate IS NULL AND buy_quantity IS NOT NULL AND buy_quantity > 0 AND free_quantity IS NOT NULL AND free_quantity > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE stock_locations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type ENUM('WAREHOUSE','REP') NOT NULL, code VARCHAR(80) NOT NULL UNIQUE,
 rep_id BIGINT UNSIGNED NULL UNIQUE, is_active BOOLEAN NOT NULL DEFAULT TRUE CHECK (is_active IN (0,1)), FOREIGN KEY (rep_id) REFERENCES users(id),
 CHECK ((type='REP' AND rep_id IS NOT NULL) OR (type='WAREHOUSE' AND rep_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE inventory_balances (
 location_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL,
 quantity BIGINT NOT NULL DEFAULT 0 CHECK (quantity >= 0), version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (location_id,product_id,batch_id),
 FOREIGN KEY (location_id) REFERENCES stock_locations(id), FOREIGN KEY (batch_id,product_id) REFERENCES product_batches(id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_sequence_counters (
 rep_id BIGINT UNSIGNED PRIMARY KEY, next_sequence BIGINT UNSIGNED NOT NULL DEFAULT 1 CHECK (next_sequence > 0), FOREIGN KEY (rep_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_number_allocations (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, rep_id BIGINT UNSIGNED NOT NULL, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 start_sequence BIGINT UNSIGNED NOT NULL, end_sequence BIGINT UNSIGNED NOT NULL, allocated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (rep_id,start_sequence), UNIQUE (id,rep_id,device_id), FOREIGN KEY (rep_id) REFERENCES users(id), FOREIGN KEY (device_id,rep_id) REFERENCES devices(id,user_id),
 CHECK (start_sequence > 0 AND end_sequence >= start_sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE offline_policy_grants (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, rep_id BIGINT UNSIGNED NOT NULL, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 schema_version INT UNSIGNED NOT NULL, approved_snapshot JSON NOT NULL, snapshot_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 issued_at_utc DATETIME(6) NOT NULL, expires_at_utc DATETIME(6) NOT NULL, UNIQUE (id,rep_id,device_id),
 FOREIGN KEY (device_id,rep_id) REFERENCES devices(id,user_id),
 CHECK (expires_at_utc > issued_at_utc AND expires_at_utc <= DATE_ADD(issued_at_utc, INTERVAL 7 DAY))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoices (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, invoice_number VARCHAR(120) NOT NULL UNIQUE,
 rep_id BIGINT UNSIGNED NOT NULL, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, customer_id BIGINT UNSIGNED NOT NULL,
 number_allocation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, policy_grant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_number BIGINT UNSIGNED NOT NULL CHECK (sequence_number > 0), business_date DATE NOT NULL,
 created_at_client_utc DATETIME(6) NOT NULL, received_at_utc DATETIME(6) NOT NULL, accepted_at_utc DATETIME(6) NOT NULL,
 subtotal DECIMAL(15,2) NOT NULL CHECK (subtotal >= 0), automatic_discount_total DECIMAL(15,2) NOT NULL CHECK (automatic_discount_total >= 0),
 manual_discount_total DECIMAL(15,2) NOT NULL CHECK (manual_discount_total >= 0), discount_total DECIMAL(15,2) NOT NULL CHECK (discount_total >= 0), grand_total DECIMAL(15,2) NOT NULL CHECK (grand_total >= 0),
 payment_type ENUM('CASH','CREDIT','BANK_TRANSFER') NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'LKR' CHECK (currency='LKR'), status ENUM('FINALIZED') NOT NULL DEFAULT 'FINALIZED',
 customer_snapshot JSON NOT NULL, rep_name_snapshot VARCHAR(150) NOT NULL, client_payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 UNIQUE (rep_id,sequence_number), UNIQUE (id,customer_id), UNIQUE (id,rep_id,device_id), FOREIGN KEY (customer_id) REFERENCES customers(id),
 FOREIGN KEY (device_id,rep_id) REFERENCES devices(id,user_id), FOREIGN KEY (number_allocation_id,rep_id,device_id) REFERENCES invoice_number_allocations(id,rep_id,device_id),
 FOREIGN KEY (policy_grant_id,rep_id,device_id) REFERENCES offline_policy_grants(id,rep_id,device_id),
 CHECK (discount_total=automatic_discount_total+manual_discount_total AND discount_total<=subtotal AND grand_total=subtotal-discount_total),
 INDEX (rep_id,created_at_client_utc), INDEX (customer_id,created_at_client_utc), INDEX (payment_type,business_date), INDEX (business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE invoice_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, line_number INT UNSIGNED NOT NULL CHECK (line_number > 0), product_id BIGINT UNSIGNED NOT NULL,
 product_name_snapshot VARCHAR(200) NOT NULL, sku_snapshot VARCHAR(100) NOT NULL, quantity BIGINT NOT NULL CHECK (quantity > 0), free_quantity BIGINT NOT NULL DEFAULT 0 CHECK (free_quantity >= 0),
 unit_price DECIMAL(15,2) NOT NULL CHECK (unit_price >= 0), gross_amount DECIMAL(15,2) NOT NULL CHECK (gross_amount >= 0), automatic_discount_amount DECIMAL(15,2) NOT NULL CHECK (automatic_discount_amount >= 0),
 manual_discount_amount DECIMAL(15,2) NOT NULL CHECK (manual_discount_amount >= 0), discount_amount DECIMAL(15,2) NOT NULL CHECK (discount_amount >= 0), line_total DECIMAL(15,2) NOT NULL CHECK (line_total >= 0),
 price_version BIGINT UNSIGNED NOT NULL, promotion_version_id BIGINT UNSIGNED NULL, manual_discount_permission_version BIGINT UNSIGNED NULL, manual_discount_reason VARCHAR(500) NULL,
 cost_price_snapshot DECIMAL(15,2) NOT NULL CHECK (cost_price_snapshot >= 0), UNIQUE (invoice_id,line_number), UNIQUE (id,product_id),
 FOREIGN KEY (invoice_id) REFERENCES invoices(id), FOREIGN KEY (product_id) REFERENCES products(id), FOREIGN KEY (promotion_version_id) REFERENCES promotion_versions(id),
 CHECK (gross_amount=quantity*unit_price AND discount_amount=automatic_discount_amount+manual_discount_amount AND discount_amount<=gross_amount AND line_total=gross_amount-discount_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sale_item_batch_allocations (
 invoice_item_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL, batch_version_id BIGINT UNSIGNED NOT NULL,
 quantity BIGINT NOT NULL CHECK (quantity >= 0), free_quantity BIGINT NOT NULL CHECK (free_quantity >= 0),
 batch_number_snapshot VARCHAR(100) NOT NULL, manufacturing_date_snapshot DATE NOT NULL, expiry_date_snapshot DATE NOT NULL,
 PRIMARY KEY (invoice_item_id,batch_id), FOREIGN KEY (invoice_item_id,product_id) REFERENCES invoice_items(id,product_id),
 FOREIGN KEY (batch_id,product_id) REFERENCES product_batches(id,product_id), FOREIGN KEY (batch_version_id,batch_id) REFERENCES product_batch_versions(id,batch_id),
 CHECK (quantity+free_quantity > 0), CHECK (expiry_date_snapshot >= manufacturing_date_snapshot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE inventory_transactions (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, type ENUM('OPENING','RECEIPT','TRANSFER','SALE','ADJUSTMENT') NOT NULL,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE, reverses_transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reason VARCHAR(500) NOT NULL, created_by_user_id BIGINT UNSIGNED NOT NULL, posted_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (invoice_id) REFERENCES invoices(id), FOREIGN KEY (reverses_transaction_id) REFERENCES inventory_transactions(id), FOREIGN KEY (created_by_user_id) REFERENCES users(id),
 CHECK ((type='SALE' AND invoice_id IS NOT NULL) OR (type<>'SALE' AND invoice_id IS NULL)), CHECK (CHAR_LENGTH(TRIM(reason)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE stock_transfers (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, inventory_transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 from_location_id BIGINT UNSIGNED NOT NULL, to_location_id BIGINT UNSIGNED NOT NULL, created_by_user_id BIGINT UNSIGNED NOT NULL, posted_at_utc DATETIME(6) NOT NULL,
 FOREIGN KEY (inventory_transaction_id) REFERENCES inventory_transactions(id), FOREIGN KEY (from_location_id) REFERENCES stock_locations(id), FOREIGN KEY (to_location_id) REFERENCES stock_locations(id),
 FOREIGN KEY (created_by_user_id) REFERENCES users(id), CHECK (from_location_id<>to_location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE stock_transfer_items (
 transfer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, product_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL, quantity BIGINT NOT NULL CHECK (quantity > 0),
 PRIMARY KEY (transfer_id,product_id,batch_id), FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id), FOREIGN KEY (batch_id,product_id) REFERENCES product_batches(id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE inventory_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, transaction_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, line_number INT UNSIGNED NOT NULL CHECK (line_number > 0),
 location_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL, batch_version_id BIGINT UNSIGNED NOT NULL,
 quantity_delta BIGINT NOT NULL CHECK (quantity_delta<>0), quantity_after BIGINT NOT NULL CHECK (quantity_after>=0), created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (transaction_id,line_number), FOREIGN KEY (transaction_id) REFERENCES inventory_transactions(id), FOREIGN KEY (location_id) REFERENCES stock_locations(id),
 FOREIGN KEY (batch_id,product_id) REFERENCES product_batches(id,product_id), FOREIGN KEY (batch_version_id,batch_id) REFERENCES product_batch_versions(id,batch_id), INDEX (location_id,product_id,batch_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE payments (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, received_by_user_id BIGINT UNSIGNED NOT NULL,
 method ENUM('CASH','BANK_TRANSFER') NOT NULL, amount DECIMAL(15,2) NOT NULL CHECK (amount>0), reference VARCHAR(200) NULL,
 received_at_client_utc DATETIME(6) NOT NULL, accepted_at_utc DATETIME(6) NOT NULL, status ENUM('PENDING','POSTED') NOT NULL,
 confirmed_by_user_id BIGINT UNSIGNED NULL, confirmed_at_utc DATETIME(6) NULL,
 UNIQUE (id,customer_id), FOREIGN KEY (customer_id) REFERENCES customers(id), FOREIGN KEY (received_by_user_id) REFERENCES users(id), FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id),
 CHECK (method<>'BANK_TRANSFER' OR status='PENDING' OR (confirmed_by_user_id IS NOT NULL AND confirmed_at_utc IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE payment_allocations (
 payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(15,2) NOT NULL CHECK (amount>0), PRIMARY KEY (payment_id,invoice_id),
 FOREIGN KEY (payment_id,customer_id) REFERENCES payments(id,customer_id), FOREIGN KEY (invoice_id,customer_id) REFERENCES invoices(id,customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sync_operations (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 operation_type ENUM('SALE_CREATE') NOT NULL, payload_schema_version INT UNSIGNED NOT NULL, canonical_payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result_status ENUM('RECEIVED','ACCEPTED','REJECTED') NOT NULL, invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
 attempt_count INT UNSIGNED NOT NULL DEFAULT 1, last_error_code VARCHAR(100) NULL, last_error_message VARCHAR(500) NULL,
 received_at_utc DATETIME(6) NOT NULL, last_attempt_at_utc DATETIME(6) NOT NULL, accepted_at_utc DATETIME(6) NULL,
 FOREIGN KEY (device_id,user_id) REFERENCES devices(id,user_id), FOREIGN KEY (invoice_id,user_id,device_id) REFERENCES invoices(id,rep_id,device_id),
 CHECK (invoice_id IS NULL OR invoice_id=id), CHECK ((result_status='ACCEPTED' AND invoice_id IS NOT NULL AND accepted_at_utc IS NOT NULL) OR (result_status<>'ACCEPTED' AND invoice_id IS NULL AND accepted_at_utc IS NULL)), INDEX (user_id,result_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE sync_stream_state (
 scope_key VARCHAR(80) PRIMARY KEY, last_change_id BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE change_log (
 scope_key VARCHAR(80) NOT NULL, change_id BIGINT UNSIGNED NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_key VARCHAR(120) NOT NULL,
 entity_version BIGINT UNSIGNED NOT NULL, action ENUM('UPSERT','REMOVE') NOT NULL, changed_at_utc DATETIME(6) NOT NULL,
 PRIMARY KEY (scope_key,change_id), FOREIGN KEY (scope_key) REFERENCES sync_stream_state(scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_user_id BIGINT UNSIGNED NULL, device_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_key VARCHAR(120) NOT NULL, reason VARCHAR(500) NULL,
 before_json JSON NULL, after_json JSON NULL, occurred_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY (actor_user_id) REFERENCES users(id), FOREIGN KEY (device_id) REFERENCES devices(id), INDEX (entity_type,entity_key,occurred_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE review_flags (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 invoice_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, code VARCHAR(100) NOT NULL, details JSON NOT NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), resolved_by_user_id BIGINT UNSIGNED NULL, resolved_at_utc DATETIME(6) NULL, resolution VARCHAR(1000) NULL,
 FOREIGN KEY (operation_id) REFERENCES sync_operations(id), FOREIGN KEY (invoice_id) REFERENCES invoices(id), FOREIGN KEY (resolved_by_user_id) REFERENCES users(id),
 CHECK (operation_id IS NOT NULL OR invoice_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

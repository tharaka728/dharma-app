CREATE TABLE location_districts (
 id INT UNSIGNED PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE location_towns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 district_id INT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 name_key VARCHAR(100) AS (LOWER(TRIM(name))) STORED,
 source VARCHAR(255) NULL,
 created_by_user_id BIGINT UNSIGNED NULL,
 created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE (district_id,name_key), UNIQUE (id,district_id),
 FOREIGN KEY (district_id) REFERENCES location_districts(id),
 FOREIGN KEY (created_by_user_id) REFERENCES users(id),
 CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO location_districts(id,name) VALUES
 (1,'Ampara'),(2,'Anuradhapura'),(3,'Badulla'),(4,'Batticaloa'),(5,'Colombo'),
 (6,'Galle'),(7,'Gampaha'),(8,'Hambantota'),(9,'Jaffna'),(10,'Kalutara'),
 (11,'Kandy'),(12,'Kegalle'),(13,'Kilinochchi'),(14,'Kurunegala'),(15,'Mannar'),
 (16,'Matale'),(17,'Matara'),(18,'Moneragala'),(19,'Mullaitivu'),(20,'Nuwara Eliya'),
 (21,'Polonnaruwa'),(22,'Puttalam'),(23,'Ratnapura'),(24,'Trincomalee'),(25,'Vavuniya');
INSERT INTO location_towns(district_id,name,source) SELECT id,IF(id=18,'Monaragala',name),'https://www.statistics.gov.lk/Contact_Us/index' FROM location_districts;
INSERT INTO location_towns(district_id,name,source) VALUES
 (5,'Maharagama','https://rti.gov.lk/io-list/'),
 (11,'Gampola','https://rti.gov.lk/io-list/'),
 (8,'Beliatta','https://rti.gov.lk/io-list/'),
 (8,'Tangalla','https://rti.gov.lk/io-list/'),
 (8,'Ambalantota','https://rti.gov.lk/io-list/');
ALTER TABLE customers ADD COLUMN district_id INT UNSIGNED NULL, ADD COLUMN town_id BIGINT UNSIGNED NULL, ADD FOREIGN KEY (district_id) REFERENCES location_districts(id), ADD FOREIGN KEY (town_id,district_id) REFERENCES location_towns(id,district_id), ADD CONSTRAINT customers_town_requires_district CHECK (town_id IS NULL OR district_id IS NOT NULL);
UPDATE customers c JOIN location_districts d ON LOWER(TRIM(c.district))=LOWER(d.name) SET c.district_id=d.id;
UPDATE customers c JOIN location_towns t ON t.district_id=c.district_id AND t.name_key=LOWER(TRIM(c.city_town)) SET c.town_id=t.id;

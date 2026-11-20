UPDATE customers c JOIN location_districts d ON d.id=c.district_id SET c.district_id=NULL WHERE c.town_id IS NULL AND LOWER(TRIM(c.district)) COLLATE utf8mb4_bin <> LOWER(d.name) COLLATE utf8mb4_bin;
UPDATE customers c JOIN location_districts d ON LOWER(TRIM(c.district)) COLLATE utf8mb4_bin=LOWER(d.name) COLLATE utf8mb4_bin SET c.district_id=d.id WHERE c.district_id IS NULL;
UPDATE customers c JOIN location_towns t ON t.district_id=c.district_id AND t.name_key COLLATE utf8mb4_bin=LOWER(TRIM(c.city_town)) COLLATE utf8mb4_bin SET c.town_id=t.id WHERE c.town_id IS NULL;

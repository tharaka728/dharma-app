ALTER TABLE promotion_versions ADD COLUMN mixed_product_ids JSON NULL,
 ADD CONSTRAINT mixed_oil_rule CHECK (mixed_product_ids IS NULL OR (JSON_TYPE(mixed_product_ids)='ARRAY' AND JSON_LENGTH(mixed_product_ids)>0 AND type='BUY_X_GET_Y' AND buy_quantity=6 AND free_quantity=1));
ALTER TABLE invoice_items MODIFY quantity BIGINT NOT NULL CHECK(quantity>=0),
 ADD CONSTRAINT invoice_paid_or_mixed_reward CHECK (quantity>0 OR (quantity=0 AND free_quantity>0 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(promotion_snapshot,'$.mixed_policy')),'')='MIXED_OIL_V1'));

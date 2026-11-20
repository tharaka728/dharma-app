ALTER TABLE products ADD CONSTRAINT products_cost_price_nonnegative CHECK (cost_price IS NULL OR cost_price >= 0);

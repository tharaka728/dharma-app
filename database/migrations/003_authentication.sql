CREATE TABLE auth_login_limits (
 bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 window_started_utc DATETIME NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 INDEX (window_started_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

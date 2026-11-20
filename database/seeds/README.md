# Development provisioning

There are no default credentials or business-data seeds. The migration inserts only the manual-discount permission code. Use `php bin/create-admin.php username "Display Name"` with `APP_ENV=development` after migrations. It generates a random password, hashes it with `password_hash`, writes a safe audit entry and displays the password once. Duplicate usernames fail without altering the existing user. Do not put output in shared logs. Login and password-change screens are later-phase work; never use this provisioning command in production.

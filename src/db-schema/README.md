# DB Schema Reference

This folder contains canonical CREATE TABLE statements for the main tables used by the Penguin Patient Connect API. Keep these files up-to-date as the application evolves; they serve as a source-of-truth for developers and for running development database migrations.

Files:
- `create_tables.sql` — Combined, ordered CREATE statements suitable for MySQL.
- `patients.sql` — `patients` table definition.
- `contact.sql` — `contact` table definition.
- `consent_tokens.sql` — `consent_tokens` table definition.
- `customer.sql` — `customer` table definition (includes AiSensy config fields).

Usage (local MySQL / XAMPP):

```powershell
# from project root
mysql -u root -p penguin-patient-connect < src/db-schema/create_tables.sql
```

Notes:
- The SQL here aims to match columns referenced by the PHP code (e.g. `consent_granted_at`, `consent_sent_at`, `consent_response_at`, `last_message_id`, `last_error`, `token_hash`, etc.).
- Adjust column sizes/types to match your production environment and add indexes/foreign keys as required.
- If you use a migration tool, convert these statements into migrations and track them in VCS.

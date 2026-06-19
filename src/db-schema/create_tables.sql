-- Combined create script (order matters if you add foreign keys later)

-- customer
SOURCE customer.sql;

-- patients
SOURCE patients.sql;

-- contact
SOURCE contact.sql;

-- consent_tokens
SOURCE consent_tokens.sql;

-- Note: If your MySQL client doesn't support SOURCE from relative paths when importing,
-- use `cat customers.sql patients.sql contact.sql consent_tokens.sql > create_tables_full.sql`
-- or paste the contents into a single file.

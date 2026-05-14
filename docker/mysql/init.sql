-- MySQL init script: grant minimum privileges to the clinic_app user.
-- clinic_app is created automatically by MYSQL_USER/MYSQL_PASSWORD env vars.
-- This script runs once on first container start.

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW,
      SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER
ON `clinic_system`.* TO 'clinic_app'@'%';

FLUSH PRIVILEGES;

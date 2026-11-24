-- Inicialización de base de datos dedicada para Testing
SELECT 'CREATE DATABASE centinela_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'centinela_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE centinela_test TO centinela;

\c centinela_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE SCHEMA IF NOT EXISTS centinela;

-- Base de datos aislada para el Hub Analítico FastAPI (Ley N° 25.326: sin acceso a tablas del CAPS)
\c centinela
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'hub_user') THEN
        CREATE ROLE hub_user LOGIN PASSWORD 'hub_secret';
    END IF;
END
$$;

SELECT 'CREATE DATABASE centinela_hub OWNER hub_user'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'centinela_hub')\gexec

REVOKE ALL ON DATABASE centinela FROM PUBLIC;
REVOKE ALL ON DATABASE centinela FROM hub_user;
GRANT ALL PRIVILEGES ON DATABASE centinela_hub TO hub_user;

\c centinela_hub
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE SCHEMA IF NOT EXISTS hub AUTHORIZATION hub_user;

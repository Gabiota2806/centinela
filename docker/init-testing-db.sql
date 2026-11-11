-- Inicialización de base de datos dedicada para Testing
SELECT 'CREATE DATABASE centinela_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'centinela_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE centinela_test TO centinela;

\c centinela_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE SCHEMA IF NOT EXISTS centinela;

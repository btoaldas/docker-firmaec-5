#!/bin/bash
# Init script ejecutado por postgres:15-alpine al primer arranque.
# Crea extensiones útiles y otorga privilegios al usuario aplicación.
# Las tablas (sistema, apiurl, sistemamobile, firma, documento, log, version)
# las crea Hibernate automáticamente al desplegar servicio.war en WildFly.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
    CREATE EXTENSION IF NOT EXISTS pgcrypto;

    GRANT ALL PRIVILEGES ON DATABASE "$POSTGRES_DB" TO "$POSTGRES_USER";
    GRANT ALL ON SCHEMA public TO "$POSTGRES_USER";
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES    TO "$POSTGRES_USER";
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO "$POSTGRES_USER";
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO "$POSTGRES_USER";
EOSQL

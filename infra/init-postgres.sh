#!/bin/sh
set -eu

psql --username "$POSTGRES_USER" --dbname postgres --set ON_ERROR_STOP=1 \
    --set=laras_password="$LARAS_DB_PASSWORD" <<'SQL'
CREATE ROLE laras LOGIN PASSWORD :'laras_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE laras OWNER laras;
CREATE DATABASE laras_test OWNER laras;
SQL

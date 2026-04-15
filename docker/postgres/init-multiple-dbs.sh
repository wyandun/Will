#!/usr/bin/env bash
# docker/postgres/init-multiple-dbs.sh
#
# Creates additional PostgreSQL databases listed in POSTGRES_MULTIPLE_DATABASES.
# The default database (POSTGRES_DB) is already created by the official postgres
# entrypoint before this script runs — we skip it to avoid a duplicate-table error.
#
# Usage (docker-compose environment):
#   POSTGRES_MULTIPLE_DATABASES=sm_portal,sm_docuseal
#
# All databases are owned by POSTGRES_USER.
# This script only runs on the very first container start (empty data volume).

set -euo pipefail

create_database() {
    local database="$1"

    # Skip the default database — already created by the entrypoint.
    if [ "$database" = "$POSTGRES_DB" ]; then
        echo "  [init-multiple-dbs] Skipping '$database' (already created by entrypoint)."
        return
    fi

    echo "  [init-multiple-dbs] Creating database '$database' owned by '$POSTGRES_USER'..."

    # Use DO block so the script is idempotent if re-run against an existing volume.
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
        -c "SELECT 'CREATE DATABASE \"$database\" OWNER \"$POSTGRES_USER\"' \
            WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = '$database')" \
        | grep -q "CREATE DATABASE" \
        && psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
               -c "CREATE DATABASE \"$database\" OWNER \"$POSTGRES_USER\";" \
        || true

    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
        -c "GRANT ALL PRIVILEGES ON DATABASE \"$database\" TO \"$POSTGRES_USER\";"

    echo "  [init-multiple-dbs] Database '$database' ready."
}

if [ -n "${POSTGRES_MULTIPLE_DATABASES:-}" ]; then
    echo "[init-multiple-dbs] Databases requested: $POSTGRES_MULTIPLE_DATABASES"

    IFS=',' read -ra databases <<< "$POSTGRES_MULTIPLE_DATABASES"
    for db in "${databases[@]}"; do
        # Trim any accidental whitespace around the comma
        db="${db// /}"
        create_database "$db"
    done

    echo "[init-multiple-dbs] All databases initialized."
fi

#!/bin/bash
# Entrypoint contenedor wildfly:
#   1. Espera a que PostgreSQL acepte conexiones.
#   2. Sustituye placeholders __DB_*__ en standalone.xml con valores de entorno.
#   3. Arranca WildFly en foreground.
set -euo pipefail

: "${DB_HOST:=postgres}"
: "${DB_PORT:=5432}"
: "${DB_NAME:=firmaec}"
: "${DB_USER:=firmaec}"
: "${DB_PASS:?DB_PASS no definido — abortando}"

STANDALONE="/opt/jboss/wildfly/standalone/configuration/standalone.xml"

echo "[firmaec] Inicio $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "[firmaec] BD destino: ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

# 1. Esperar PostgreSQL (max 120s)
echo "[firmaec] Esperando PostgreSQL..."
TIMEOUT_S=120
for i in $(seq 1 $((TIMEOUT_S / 2))); do
    if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -q 2>/dev/null; then
        echo "[firmaec] PostgreSQL OK tras $((i * 2))s"
        break
    fi
    if [ "$i" -eq $((TIMEOUT_S / 2)) ]; then
        echo "[firmaec] ERROR: timeout (${TIMEOUT_S}s) esperando PostgreSQL"
        exit 1
    fi
    sleep 2
done

# 2. Sustituir placeholders __DB_*__
[ -f "$STANDALONE" ] || { echo "[firmaec] ERROR: no existe $STANDALONE"; exit 1; }

echo "[firmaec] Inyectando configuración BD en standalone.xml..."
sed -i \
    -e "s|__DB_HOST__|${DB_HOST}|g" \
    -e "s|__DB_PORT__|${DB_PORT}|g" \
    -e "s|__DB_NAME__|${DB_NAME}|g" \
    -e "s|__DB_USER__|${DB_USER}|g" \
    -e "s|__DB_PASS__|${DB_PASS}|g" \
    "$STANDALONE"

if grep -q "__DB_" "$STANDALONE"; then
    echo "[firmaec] ERROR: placeholders no sustituidos:"
    grep "__DB_" "$STANDALONE" | head -10
    exit 1
fi
echo "[firmaec] standalone.xml configurado"

# 3. Mostrar manifiesto y arrancar
[ -f /opt/jboss/version-manifest.txt ] && cat /opt/jboss/version-manifest.txt
echo "[firmaec] Arrancando WildFly..."
exec /opt/jboss/wildfly/bin/standalone.sh \
    -b 0.0.0.0 \
    -bmanagement 0.0.0.0 \
    -c standalone.xml

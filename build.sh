#!/usr/bin/env bash
# Compila los WARs de FirmaEC desde los ZIPs en recursos-minka/${FIRMAEC_VERSION}/.
# Salida: wildfly/build-artifacts/{servicio.war, api.war, libreria.jar, version-manifest.txt}
set -euo pipefail

cd "$(dirname "$0")"

# 1. Cargar .env
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "[build] Creado .env desde .env.example — edítelo y vuelva a ejecutar."
        exit 1
    else
        echo "[build] ERROR: no existe .env ni .env.example"
        exit 1
    fi
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

VER="${FIRMAEC_VERSION:-5.1}"
SRC_DIR="recursos/minka/${VER}"

# 2. Validar ZIPs
echo "[build] Versión: ${VER}"
echo "[build] Fuentes: ${SRC_DIR}/"

if [ ! -d "$SRC_DIR" ]; then
    echo "[build] ERROR: no existe $SRC_DIR"
    echo "  Cree el directorio y coloque los ZIPs de MINKA. Ver recursos/minka/README.md"
    exit 1
fi

missing=0
for prefix in firmadigital-libreria firmadigital-api firmadigital-servicio; do
    if ! ls "$SRC_DIR"/${prefix}*.zip >/dev/null 2>&1; then
        echo "[build] FALTA: $SRC_DIR/${prefix}*.zip"
        missing=1
    fi
done
[ "$missing" -eq 0 ] || exit 1

# 3. Limpiar artefactos previos
mkdir -p wildfly/build-artifacts
rm -f wildfly/build-artifacts/*.war wildfly/build-artifacts/*.jar wildfly/build-artifacts/version-manifest.txt

# 4. Build Docker multi-stage (target=export)
echo "[build] Lanzando docker build (multi-stage)..."
DOCKER_BUILDKIT=1 docker build \
    --progress=plain \
    --build-arg "FIRMAEC_VERSION=${VER}" \
    --target export \
    -f wildfly/Dockerfile.build \
    -t "docker-firmaec-5/builder:${VER}" \
    .

# 5. Extraer artefactos del contenedor efímero
CID="firmaec-build-tmp-$$"
docker create --name "$CID" "docker-firmaec-5/builder:${VER}" >/dev/null
docker cp "${CID}:/artifacts/." ./wildfly/build-artifacts/
docker rm "$CID" >/dev/null

# 6. Validar tamaños mínimos
SZ_SVC=$(stat -c%s wildfly/build-artifacts/servicio.war 2>/dev/null || echo 0)
SZ_API=$(stat -c%s wildfly/build-artifacts/api.war      2>/dev/null || echo 0)
SZ_LIB=$(stat -c%s wildfly/build-artifacts/libreria.jar 2>/dev/null || echo 0)

[ "$SZ_SVC" -ge 1000000 ] || { echo "[build] ERROR: servicio.war demasiado pequeño ($SZ_SVC bytes)"; exit 1; }
[ "$SZ_API" -ge   50000 ] || { echo "[build] ERROR: api.war demasiado pequeño ($SZ_API bytes)"; exit 1; }
[ "$SZ_LIB" -ge  100000 ] || { echo "[build] ERROR: libreria.jar demasiado pequeño ($SZ_LIB bytes)"; exit 1; }

echo ""
echo "[build] OK — artefactos en wildfly/build-artifacts/:"
ls -lh wildfly/build-artifacts/
echo ""
cat wildfly/build-artifacts/version-manifest.txt
echo ""
echo "[build] Siguiente paso:  docker compose up --build -d"

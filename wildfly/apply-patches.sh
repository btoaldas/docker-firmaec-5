#!/bin/bash
# Aplica patches de /patches/*.patch sobre /sources/ con 3 estrategias fallback.
# Usado por Dockerfile.build (stage patcher).
set -euo pipefail

SRC_DIR="${1:-/sources}"
PATCH_DIR="${2:-/patches}"

if [ ! -d "$PATCH_DIR" ]; then
    echo "[apply-patches] No existe $PATCH_DIR — nada que aplicar"
    exit 0
fi

cd "$SRC_DIR"

# Inicializar repo git efimero (necesario para 'git apply' como fallback)
if [ ! -d .git ]; then
    git init -q
    git -c user.email=patcher@local -c user.name=patcher add -A
    git -c user.email=patcher@local -c user.name=patcher commit -q -m "base" || true
fi

count=0
for p in $(ls "$PATCH_DIR"/*.patch 2>/dev/null | sort); do
    name=$(basename "$p")
    echo "[apply-patches] aplicando $name"

    if patch -p1 --fuzz=3 --dry-run -s < "$p" >/dev/null 2>&1; then
        patch -p1 --fuzz=3 -s < "$p"
        echo "  └─ aplicado (patch -p1)"
    elif patch -p1 -l --fuzz=3 --dry-run -s < "$p" >/dev/null 2>&1; then
        patch -p1 -l --fuzz=3 -s < "$p"
        echo "  └─ aplicado (patch -p1 -l)"
    elif git apply --check --whitespace=nowarn "$p" 2>/dev/null; then
        git apply --whitespace=nowarn "$p"
        echo "  └─ aplicado (git apply)"
    else
        echo "[apply-patches] ERROR: $name no aplica con ninguna estrategia."
        echo "  Probable causa: el upstream cambió y el patch ya no aplica."
        echo "  Diagnóstico:"
        patch -p1 --fuzz=3 --dry-run < "$p" 2>&1 | head -20
        exit 1
    fi
    count=$((count + 1))
done

echo "[apply-patches] OK ($count patches aplicados)"

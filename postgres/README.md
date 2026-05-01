# Configuración PostgreSQL

Este directorio contiene los scripts de inicialización ejecutados automáticamente
por el contenedor `postgres:15-alpine` la primera vez que se crea el volumen.

## Archivos

- `01-init-roles.sh` — Habilita extensiones (`uuid-ossp`, `pgcrypto`) y otorga
  privilegios al usuario aplicación.

## ¿Y las tablas?

**No se crean aquí.** Las tablas de FirmaEC (`sistema`, `apiurl`, `sistemamobile`,
`firma`, `documento`, `log`, `version`, etc.) las crea Hibernate automáticamente
cuando WildFly despliega `servicio.war`.

Esto puede tardar 30–90 s después de que Postgres responde "ready". Si ejecuta
queries inmediatamente después de levantar el stack, las tablas aún pueden no
existir. Para verificar:

```bash
docker compose exec postgres psql -U firmaec_app -d firmaec -c '\dt'
```

## Datos iniciales (semilla)

Para que un sistema cliente pueda firmar a través de FirmaEC, debe estar
registrado en la tabla `sistema` con un `apikey` (hash SHA-256 mayúsculas del
nombre del sistema). El cliente PHP de prueba incluye una pestaña **"Setup"**
que hace este registro automáticamente.

Manual:

```sql
INSERT INTO sistema (nombre, descripcion, url, apikey, apikeyrest)
VALUES (
    'ClienteDemo',
    'Cliente de prueba',
    'http://cliente-prueba/callback.php',
    UPPER(ENCODE(DIGEST('ClienteDemo', 'sha256'), 'hex')),
    NULL  -- IMPORTANTE: NULL fuerza modo SOAP. Si tiene valor, FirmaEC usa REST.
);
```

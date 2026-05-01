# PostgreSQL JDBC driver

Driver JDBC para que WildFly se conecte a PostgreSQL. **Commiteado al
repositorio** (~1MB) para reproducibilidad offline.

## Versión

`postgresql-42.2.2.jar`

- **Origen oficial:** <https://jdbc.postgresql.org/download/postgresql-42.2.2.jar>
- **SHA-256:** `1996524026a3027853f3932e8639ef813807d1b63fe14832f410fffa4274fa70`
- **Por qué 42.2.2:** versión usada en producción quipux (probada 2024-2026).
  Versiones más nuevas (42.7.x) se han reportado con bugs en algunos casos.

## Verificar integridad

```bash
sha256sum recursos/jdbc/postgresql-42.2.2.jar
# 1996524026a3027853f3932e8639ef813807d1b63fe14832f410fffa4274fa70  recursos/jdbc/postgresql-42.2.2.jar
```

## Cómo se usa

`wildfly/Dockerfile.runtime` lo copia a:

```
/opt/jboss/wildfly/modules/org/postgresql/main/postgresql-42.2.2.jar
```

Junto con `wildfly/module.xml` que define el módulo `org.postgresql`.
Después `wildfly/install.cli` lo registra como driver JDBC en WildFly via
JBoss CLI.

## Failover

Si el archivo no existe en este directorio, el Dockerfile descarga la
versión declarada en `ARG PG_DRIVER_VERSION` (default `42.2.2`) desde
`jdbc.postgresql.org` durante el build. Comportamiento idéntico — solo
requiere conexión a internet.

## Cambiar versión

1. Descargar nuevo jar a este directorio.
2. Editar `wildfly/module.xml` para apuntar al nuevo nombre del jar.
3. Editar `ARG PG_DRIVER_VERSION=...` en `wildfly/Dockerfile.runtime`.
4. Actualizar `recursos/jdbc/README.md` (este archivo) con nueva versión + hash.
5. Rebuild: `./build.sh && docker compose up --build -d wildfly`.

## Licencia driver

PostgreSQL JDBC driver está bajo la **BSD 2-Clause License**. Compatible con
AGPL v3 del resto del proyecto.

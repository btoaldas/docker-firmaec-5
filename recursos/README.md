# recursos/ — fuentes y binarios usados por el build

Centraliza todo lo que el build consume aparte de las imágenes Docker base.

## Layout

```
recursos/
├── README.md                            # este archivo
├── minka/                               # fuentes upstream FirmaEC
│   ├── README.md
│   ├── 5.1/                             # versión actual
│   │   ├── README.md
│   │   ├── firmadigital-libreria-master.zip   ← gitignored, descargar
│   │   ├── firmadigital-api-master.zip        ← gitignored, descargar
│   │   └── firmadigital-servicio-master.zip   ← gitignored, descargar
│   └── 5.2/                             # placeholder futura versión
└── jdbc/                                # driver PostgreSQL JDBC
    ├── README.md
    └── postgresql-42.2.2.jar            ← commiteado al repo (~1MB)
```

## ¿Qué se commitea y qué no?

| Tipo                          | Commiteado al repo | Razón                                                   |
|-------------------------------|--------------------|---------------------------------------------------------|
| ZIPs MINKA                    | ❌ No              | Cada quien los descarga; respeto upstream; pesan ~10MB  |
| `postgresql-42.2.2.jar`       | ✅ Sí              | 1MB, reproducibilidad offline, build no necesita red    |
| Binarios Maven/WildFly        | ❌ No (no presentes) | Imágenes Docker oficiales los proveen                 |

## Selector de versión MINKA

Variable `FIRMAEC_VERSION` en `.env`. Default `5.1`.

```bash
# Para usar otra versión:
mkdir -p recursos/minka/5.2
# colocar los 3 ZIPs ahí
echo "FIRMAEC_VERSION=5.2" >> .env  # o editar el existente
./build.sh && docker compose up --build -d
```

Ver `recursos/minka/README.md` para detalles.

## Failover JDBC

`wildfly/Dockerfile.runtime` chequea si existe
`recursos/jdbc/postgresql-${PG_DRIVER_VERSION}.jar` (default `42.2.2`):

- **Si existe** → lo copia al módulo WildFly (offline, sin red).
- **Si no existe** → `curl` desde `jdbc.postgresql.org`.

Para upgradear:
1. Descargar nueva versión a `recursos/jdbc/`.
2. Cambiar `wildfly/module.xml` (path del jar).
3. Cambiar `ARG PG_DRIVER_VERSION` en `wildfly/Dockerfile.runtime`.

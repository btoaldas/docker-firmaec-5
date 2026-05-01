# docker-firmaec-5

Empaquetado Docker (no oficial) del sistema **FirmaEC** del MINTEL Ecuador
versión 5.1, **puro** — tal como nace en MINKA. Levanta todo el stack
(PostgreSQL + WildFly con FirmaEC + cliente PHP de prueba + nginx) con
dos comandos. Implementa el flujo correcto: el cliente web coordina pero
**no firma** — la firma criptográfica ocurre en FirmaEC Desktop instalado
en la PC del usuario.

> ⚠️ Este NO es un proyecto oficial del MINTEL/MINKA. Solo es dockerización
> comunitaria del [código fuente público FirmaEC](https://minka.gob.ec/mintel/ge/firmaec),
> entregado AS-IS bajo AGPL v3, sin garantía ni mantenimiento.
> Lea [AVISO-LEGAL.md](AVISO-LEGAL.md) antes de usar.

## Créditos

Todo el código FirmaEC pertenece al **MINTEL Ecuador / MINKA**:

- Librería de firma: <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-libreria>
- API REST:          <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-api>
- Servicio:          <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-servicio>

Este repositorio solo contiene `Dockerfile`s, `docker-compose.yml`, scripts
de build, JBoss CLI, configuración nginx y un cliente PHP de prueba.

## Stack

| Componente | Versión                                |
|------------|----------------------------------------|
| WildFly    | `36.0.1.Final-jdk17` (Jakarta EE 11)   |
| JDK        | 17 (Eclipse Temurin)                   |
| Maven      | 3.8.8                                  |
| PostgreSQL | 15-alpine                              |
| JDBC       | postgresql-42.2.2 (commiteado al repo) |
| nginx      | 1.27-alpine (proxy unificador)         |
| PHP        | 8.2 (cliente prueba)                   |
| FirmaEC    | 5.1 (master, MINKA)                    |

## Arquitectura

```
                        ┌────────────────────────────────────┐
                        │ Browser usuario (Windows / Mac)    │
                        └──────────────┬─────────────────────┘
                                       │ HTTP :8082
                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│ docker-compose.yml  (red firmaec-net)                                │
│                                                                      │
│  ┌──────────────────────────────────────┐                            │
│  │ nginx :80 → host :8082               │                            │
│  │    /              → cliente-prueba   │                            │
│  │    /api/*         → wildfly          │                            │
│  │    /servicio/*    → wildfly          │                            │
│  │    /healthz       → 200 ok           │                            │
│  └─────────┬──────────────────┬─────────┘                            │
│            │                  │                                      │
│            ▼                  ▼                                      │
│  ┌──────────────────┐    ┌────────────────┐    ┌──────────────────┐ │
│  │ cliente-prueba   │◄──►│ wildfly        │◄──►│ postgres         │ │
│  │ php:8.2-apache   │    │ 36-jdk17       │    │ 15-alpine        │ │
│  │ Setup/Firmar UI  │    │ servicio.war   │    │ 9 tablas         │ │
│  │ + callback.php   │    │ api.war        │    │ creadas por      │ │
│  │                  │    │ libreria.jar   │    │ Hibernate        │ │
│  └──────────────────┘    └────────────────┘    └──────────────────┘ │
└──────────────────────────────────────────────────────────────────────┘
                                       ▲
                                       │ HTTP a 192.168.0.100:8082 (LAN)
                                       │
                        ┌──────────────┴─────────────────────┐
                        │ FirmaEC Desktop                    │
                        │ (Windows host, javaw.exe)          │
                        │                                    │
                        │ 1. Recibe firmaec://...            │
                        │ 2. GET /api/.../{token}            │
                        │ 3. Firma localmente con .p12       │
                        │ 4. PUT /servicio/.../{token}       │
                        └────────────────────────────────────┘
```

## Flujo de firma (correcto, como FirmaEC Desktop)

```
1. Browser  → POST /firmar.php (PDF + cédula + opciones estampado)
2. PHP      → POST /servicio/documentos (X-API-KEY: secret)
              ← 201 token JWT
3. PHP devuelve a browser: { token, firmaec_url, ... }
4. Browser abre  firmaec://sistema/firmar?token=X&url=Y&tipo_certificado=Archivo&...
5. SO Windows lanza FirmaEC Desktop (instalado por el usuario)
6. Desktop GET  /servicio/documentos/{token}     → descarga PDF
7. Desktop pide cert (.p12 o token físico) al usuario, firma localmente
8. Desktop PUT  /servicio/documentos/{token}     → sube PDF firmado
9. Servicio    POST http://cliente-prueba/callback.php
              Header: X-API-KEY: <apikeyrest>
              Body: JSON con archivo firmado en base64
10. callback.php valida apikey, guarda PDF en /tmp/firmaec-firmados/
11. Browser polling /status.php cada 5s → cuando ve archivo → habilita botón Descargar
12. Usuario descarga PDF firmado
```

## Requisitos

- Docker Engine 24+
- Docker Compose v2
- ~4 GB RAM
- Conexión a internet primera vez (descarga imágenes Docker + deps Maven)
- **FirmaEC Desktop** instalado en la PC del usuario:
  - Descarga: <https://www.firmadigital.gob.ec/>
  - Registra protocolo `firmaec://` en el SO

## Uso

### 1. Clonar

```bash
git clone https://github.com/btoaldas/docker-firmaec-5.git
cd docker-firmaec-5
```

### 2. Descargar fuentes MINKA

Colocar los 3 ZIPs en `recursos/minka/5.1/`:

- `firmadigital-libreria-master.zip`
- `firmadigital-api-master.zip`
- `firmadigital-servicio-master.zip`

Ver [recursos/minka/README.md](recursos/minka/README.md). Estos archivos
NO se commiten (`.gitignore`).

### 3. Configurar entorno

```bash
cp .env.example .env
# Editar .env. OBLIGATORIO: FIRMAEC_DB_PASSWORD.
# Generar con: openssl rand -base64 24
```

### 4. Compilar WARs

```bash
./build.sh
```

Tarda 8–15 min la primera vez (descarga deps Maven). Salida:
`wildfly/build-artifacts/{servicio.war, api.war, libreria.jar}`.

### 5. Levantar stack

```bash
docker compose up --build -d
```

Esperar 1–2 min a que WildFly despliegue WARs e Hibernate cree tablas.

### 6. Setup inicial

Abrir <http://localhost:8082> → pestaña **Setup** → registrar el sistema.
Copiar el `secret` mostrado al `.env`:

```bash
# en .env
FIRMAEC_CLIENT_API_KEY=<secret_que_devolvió_setup>
```

```bash
docker compose up -d --force-recreate cliente-prueba
```

### 7. Probar firma e2e

Pestaña **Firmar PDF** → subir PDF + cédula + opciones de estampado →
"1. Iniciar firma" → click "Abrir FirmaEC Desktop" → firmar → polling
detecta firmado → "Descargar PDF firmado".

## Endpoints

Todo HTTP entra por `localhost:8082` (nginx). Los puertos directos son
solo para depuración interna.

| Función                          | URL                                              |
|----------------------------------|--------------------------------------------------|
| Cliente UI                       | <http://localhost:8082>                          |
| Healthcheck nginx                | <http://localhost:8082/healthz>                  |
| FirmaEC API (proxy a servicio)   | <http://localhost:8082/api/...>                  |
| FirmaEC Servicio                 | <http://localhost:8082/servicio/...>             |
| WildFly admin                    | <http://localhost:9990> (depuración)             |
| PostgreSQL                       | `localhost:5436` (firmaec_app / FIRMAEC_DB_PASSWORD) |

## Networking Windows (FirmaEC Desktop)

El cliente Desktop corre en Windows host (fuera del container). Java en
Windows tiene preferencia IPv6 (`localhost` → `::1`), nginx solo IPv4 →
Desktop dice "no internet" aunque el stack funciona.

**Solución default:** `FIRMAEC_PUBLIC_BASE=http://127.0.0.1:8082` (IPv4
explícito). Java no traduce `127.0.0.1`, llega directo.

**Si requiere IP LAN** (otra máquina, dominio, etc.):

1. Cambiar `FIRMAEC_PUBLIC_BASE=http://192.168.x.x:8082` en `.env`
2. Configurar Windows portproxy como **administrador**:
   ```cmd
   netsh interface portproxy add v4tov4 listenaddress=0.0.0.0 listenport=8082 connectaddress=<WSL_IP> connectport=8082
   ```
   donde `<WSL_IP>` es la IP del WSL2 distro (`ip addr show eth0`).
3. Abrir firewall:
   ```cmd
   netsh advfirewall firewall add rule name="docker-firmaec-5 inbound 8082" dir=in action=allow protocol=TCP localport=8082
   ```
4. Re-correr Setup en UI con la nueva URL pública (registra en tabla `apiurl`).

## Quirks del cliente FirmaEC Desktop oficial 5.1.0

Descubiertos durante la integración:

| Quirk | Workaround |
|-------|-----------|
| Mal-decodifica `%3A%2F%2F` en URL → `AFF` (corrupta) | Pasar URL EN TEXTO PLANO (sin URL-encoding) |
| Crashea con NPE si falta `tipo_certificado` | Siempre incluir `&tipo_certificado=Archivo` o `Token` |
| `localhost` resuelve a `::1` (IPv6) y nginx solo IPv4 | Usar `127.0.0.1` o IP LAN explícita |
| api.war es proxy y necesita system properties | install.cli registra `firmadigital-servicio.url` y `firmadigital-servicio-mobile.url` |
| Verifica versión cliente contra MINTEL externo (`api.firmadigital.gob.ec`) | OK — MINTEL responde "Version enabled" para 5.1.0 oficial |

## Operación

```bash
# Logs
docker compose logs -f wildfly
docker compose logs -f nginx
docker compose logs -f cliente-prueba

# Tablas creadas por Hibernate
docker exec firmaec-postgres psql -U firmaec_app -d firmaec -c '\dt'

# Filas registradas (sistema, sistemamobile, apiurl, version)
docker exec firmaec-postgres psql -U firmaec_app -d firmaec \
  -c 'SELECT id,nombre,url,status FROM apiurl; SELECT id,nombre FROM sistema;'

# Reset BD (borra volumen + datos firmados)
docker compose down -v && rm -rf /tmp/firmaec-firmados

# Recompilar tras cambiar fuentes MINKA
./build.sh && docker compose up --build -d wildfly

# Cambiar config nginx (sin rebuild — bind-mount)
nano nginx/nginx.conf && docker compose restart nginx

# Cambiar a versión 5.2 (cuando exista)
# 1) Colocar ZIPs en recursos/minka/5.2/
# 2) Editar .env: FIRMAEC_VERSION=5.2
# 3) ./build.sh && docker compose up --build -d
```

## Patches BUILD aplicados

El `master` actual de MINKA no compila out-of-the-box sobre Java 17 +
Jakarta EE 11. Se aplican **5 patches mínimos** (solo configuración Maven,
**sin tocar lógica funcional**):

1. `01` — Sincronizar versión `firmadigital-libreria` 5.0→5.1 en `servicio/pom.xml`.
2. `02` — `<scope>provided</scope>` para `jakarta.activation-api` y `resteasy-jaxrs` en `api/pom.xml`.
3. `03` — `<sourceDirectory>src/java</sourceDirectory>` en `api/pom.xml` (layout NetBeans).
4. `04` — `maven-compiler-plugin:3.14.1` con `<release>17</release>` en `libreria/pom.xml`.
5. `05` — `maven-compiler-plugin:3.14.1` con `<release>17</release>` en `servicio/pom.xml`.

Ver [wildfly/patches/README.md](wildfly/patches/README.md) para detalles.

> Estos patches NO modifican comportamiento funcional — solo arreglan bugs
> upstream de configuración. Quien quiera adaptar FirmaEC (URLs propias,
> bypass JWT, etc.) puede agregar sus propios patches numerados a partir
> del `06-`.

## Solución de problemas

**`build.sh` falla con `ERROR: faltan ZIPs`**
→ Coloque los 3 ZIPs en `recursos/minka/<FIRMAEC_VERSION>/`.

**`mvn` falla con `Source option 5 is no longer supported`**
→ Patches 04/05 no aplicaron. Ver `wildfly/patches/`.

**Endpoints `/api/*` devuelven HTML 404**
→ Patch 02 no aplicó (jakarta-activation/resteasy se shadean).

**`api.war` se construye pero queda vacío**
→ Patch 03 no aplicó (Maven no encuentra `src/java`).

**`/api/...` HTTP 500 RESTEASY004655 ClientProtocolException**
→ Faltan system properties `firmadigital-servicio.url` /
`firmadigital-servicio-mobile.url`. Verificar que `install.cli` se aplicó.
Reset:
```
docker compose up -d --build wildfly
```

**Cliente Desktop dice "no internet" pero el stack funciona**
→ Java intenta `localhost` → `::1` IPv6, falla. Cambiar
`FIRMAEC_PUBLIC_BASE=http://127.0.0.1:8082` en `.env` + re-Setup.

**Cliente Desktop arranca pero UI no aparece (proceso `javaw.exe` zombi)**
→ NullPointerException por `tipo_certificado` faltante o URL URL-encoded.
Confirmar que `firmar.php` genera URL plana con `tipo_certificado=Archivo`.

**WildFly arranca pero datasource falla**
→ Mismatch credenciales `.env`. Reset:
```
docker compose down -v && docker compose up --build -d
```

**Cliente prueba: `Se debe incluir el parametro jwt`**
→ El sistema cliente no está registrado. Pestaña **Setup** primero.

**Polling status.php nunca dice "firmado"**
→ Verificar que `sistema.url = http://cliente-prueba/callback.php` y
`apikeyrest = SHA256(secret)`. Logs:
```
docker exec firmaec-cliente cat /tmp/firmaec-callback.log
docker compose logs wildfly | grep callback
```

## Migración v2 → v3

Si tenías un setup de v2 funcionando:

| Cambió | Cómo migrar |
|--------|-------------|
| `recursos-minka/` → `recursos/minka/` | `git pull` ya lo trae |
| Puerto cliente `:8081` → `:8082/` | Actualizar bookmarks |
| Puerto WildFly `:9086/api` → `:8082/api` | Actualizar scripts externos |
| Cliente firma directa (cert .p12 al server) | Reemplazado por flujo Desktop |
| `recursos/jdbc/postgresql-42.2.2.jar` commiteado | Build offline-capable |
| Nginx unificador en puerto principal | `.env` con `NGINX_HOST_PORT=8082` |
| Default `FIRMAEC_PUBLIC_BASE=http://127.0.0.1:8082` | Java IPv4 explícito |
| 5 patches BUILD (no 3) | Maven Java 17 funciona |

## Estructura del repo

```
.
├── build.sh
├── docker-compose.yml
├── .env.example
├── README.md
├── AVISO-LEGAL.md
├── LICENSE                          # AGPL v3
│
├── recursos/                        # fuentes y binarios para el build
│   ├── README.md
│   ├── minka/
│   │   ├── 5.1/                     # ZIPs MINKA (gitignored)
│   │   └── 5.2/                     # placeholder
│   └── jdbc/
│       └── postgresql-42.2.2.jar    # commiteado (~1MB, offline)
│
├── nginx/
│   ├── README.md
│   └── nginx.conf                   # rutas /api, /servicio, /
│
├── postgres/
│   ├── 01-init-roles.sh
│   └── README.md
│
├── wildfly/
│   ├── Dockerfile.build             # multi-stage extractor→patcher→builder→export
│   ├── Dockerfile.runtime           # WildFly 36 + JDBC + WARs + install.cli
│   ├── module.xml
│   ├── install.cli                  # JBoss CLI offline (driver + datasource + system properties)
│   ├── apply-patches.sh
│   ├── entrypoint.sh
│   ├── patches/                     # 5 patches BUILD
│   └── build-artifacts/             # generado por build.sh (gitignored)
│
└── cliente-prueba/                  # PHP coordinador (NO firma)
    ├── Dockerfile
    ├── README.md
    ├── index.php                    # 4 tabs UI
    ├── firmar.php                   # POST /servicio/documentos → token + firmaec://
    ├── status.php                   # polling — ¿callback recibió firmado?
    ├── download.php                 # descarga PDF firmado
    ├── callback.php                 # endpoint REST que recibe firmados del servicio
    ├── setup-sistema.php            # registra sistema + sistemamobile + apiurl + version en BD
    ├── config.php
    └── assets/{style.css, app.js}
```

## Licencia

GNU AGPL v3 — heredada del proyecto FirmaEC del MINTEL Ecuador. Ver
[LICENSE](LICENSE) y [AVISO-LEGAL.md](AVISO-LEGAL.md).

## Contribuciones

Issues y PRs sobre **la dockerización** son bienvenidos. Para issues sobre
el **código fuente FirmaEC**, dirigirse al upstream oficial en
[MINKA](https://minka.gob.ec/mintel/ge/firmaec).

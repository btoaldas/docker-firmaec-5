# Nginx — proxy unificador

Punto de entrada HTTP único del stack. Une 3 servicios bajo un mismo puerto
(default `8080` en host) con prefijos de URL:

| URL pública                       | Ruteado a                         |
|-----------------------------------|-----------------------------------|
| `http://localhost:8080/`          | `cliente-prueba:80` (PHP UI)      |
| `http://localhost:8080/api/...`   | `wildfly:8080/api/...`            |
| `http://localhost:8080/servicio/...` | `wildfly:8080/servicio/...`    |
| `http://localhost:8080/healthz`   | nginx propio (200 ok)             |

## Configuración

`nginx.conf` se monta read-only en el contenedor. Para cambios:

```bash
# editar nginx/nginx.conf, luego:
docker compose restart nginx
# (no requiere rebuild — es bind-mount)
```

## Ajustes comunes

- **Tamaño máx upload** — `client_max_body_size 100M;` en `nginx.conf`.
  Aumentar si firma PDFs > 100 MB.
- **Timeouts** — `proxy_read_timeout 300s` (firma de PDFs grandes puede
  tomar minutos).
- **HTTPS** — no incluido. Para producción, terminar TLS en otra capa
  (host físico con nginx propio, o reverse-proxy upstream tipo traefik).

## Ejemplo lectura logs

```bash
docker compose logs -f nginx
# muestra todas las requests con timestamp + status + path
```

## Por qué bind-mount sin Dockerfile custom

La imagen `nginx:1.27-alpine` (~25MB) basta. Cambiar config = editar archivo
y reiniciar contenedor. No hay extensiones ni módulos custom que requieran
rebuild.

## Comunicación interna NO atraviesa nginx

El cliente PHP llama directamente a `http://wildfly:8080/...` desde dentro
de la red Docker (ver `cliente-prueba/firmar.php`). Nginx solo sirve para
el usuario del navegador. Esto evita un hop innecesario y mantiene
latencia interna baja.

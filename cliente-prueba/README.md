# Cliente PHP de prueba — flujo FirmaEC Desktop

Implementa el flujo **correcto** de FirmaEC: el cliente web NO firma, solo
coordina. La firma criptográfica ocurre 100% en la PC del usuario, dentro de
**FirmaEC Desktop**.

## Flujo

```
Browser (este cliente)
   │ 1. POST /firmar.php (PDF + cedula)
   │ 1a. PHP -> POST /servicio/documentos
   │     X-API-KEY: <secret>
   │     {cedula, sistema, documentos:[{nombre, documento:<base64>}]}
   │     ← 201 Token JWT
   │
   │ 2. Browser abre  firmaec://sistema/firmar?token=X&url=<api_url>
   ▼
FirmaEC Desktop  (PC del usuario, instalado previamente)
   │ 3. GET /servicio/documentos/{token}
   │ 4. Pide cert .p12 / token físico al usuario
   │ 5. Firma localmente (cert nunca sale de la PC)
   │ 6. PUT /servicio/documentos/{token}  con PDF firmado
   ▼
Servicio FirmaEC
   │ 7. Callback REST -> POST http://cliente-prueba/callback.php
   │     X-API-KEY: <apikeyrest>
   │     JSON con archivo firmado
   ▼
callback.php guarda el PDF en /tmp/firmaec-firmados/
   │
   │ 8. Browser hace polling a /status.php
   │    Cuando ve archivo guardado → muestra botón Descargar
   ▼
download.php sirve el PDF firmado al usuario
```

## Pestañas UI

| Pestaña       | Función                                                      |
|---------------|--------------------------------------------------------------|
| Firmar PDF    | Form upload + cédula → token + URL `firmaec://` + polling    |
| Setup         | Registra `sistema`, `sistemamobile`, `apiurl`, `version` BD  |
| Endpoints     | URLs configuradas (público vía nginx + interno Docker)       |
| Ayuda Desktop | Cómo instalar FirmaEC Desktop                                |

## Archivos

- `index.php` — UI con 4 pestañas.
- `firmar.php` — POST /servicio/documentos → recibe token → devuelve `firmaec://` URL.
- `status.php` — polling: ¿callback recibió firmado?
- `download.php` — descarga PDF firmado.
- `callback.php` — endpoint REST que el servicio invoca con el PDF firmado.
- `setup-sistema.php` — INSERT/UPDATE idempotente en tablas FirmaEC.
- `config.php` — env vars.
- `assets/app.js` — flujo browser-side: submit, polling, abrir Desktop.
- `assets/style.css`.

## Variables env

| Var                       | Default                          | Para qué                                                  |
|---------------------------|----------------------------------|-----------------------------------------------------------|
| `FIRMAEC_API_BASE`        | `http://wildfly:8080/api`        | base interno API (Docker DNS)                             |
| `FIRMAEC_SERVICIO_BASE`   | `http://wildfly:8080/servicio`   | base interno servicio (Docker DNS)                        |
| `FIRMAEC_PUBLIC_BASE`     | `http://127.0.0.1:8082`          | URL pública (FirmaEC Desktop la usa)                      |
| `FIRMAEC_SISTEMA`         | `ClienteDemo`                    | nombre del sistema en BD                                  |
| `FIRMAEC_API_KEY`         | (vacío)                          | secret plano enviado como `X-API-KEY` al servicio         |
| `FIRMAEC_DB_*`            | postgres / firmaec_app / ...     | conexión BD para setup-sistema.php                        |

## Opciones de estampado (form Firmar PDF)

El form acepta los siguientes parámetros (todos opcionales):

| Campo              | Default        | Descripción                                          |
|--------------------|----------------|------------------------------------------------------|
| `tipo_certificado` | `Archivo`      | `Archivo` (.p12) o `Token` (USB físico)              |
| `tipo_estampado`   | `QR`           | `QR`, `information1`, `information2`                 |
| `pagina`           | `1`            | número de página o `"ultima"`                        |
| `llx`              | `100`          | X coordenada inferior-izquierda (px)                 |
| `lly`              | `100`          | Y coordenada inferior-izquierda (px)                 |
| `razon`            | `""`           | razón de la firma (URL-encoded automáticamente)      |
| `ubicacion`        | `""`           | ubicación de la firma                                |

Estos se anexan al URL `firmaec://` que invoca el Desktop. Algunos params
(como `tipo_estampado`) puede que el Desktop oficial los ignore en versión
actual — el tipo se decide internamente al firmar. Se incluyen para
compatibilidad con versiones futuras.

### Tipos de estampado visual

- **QR** (default): genera código QR con info de validación apuntando a
  `https://www.firmadigital.gob.ec`. El más útil para validación pública.
- **information1**: rectángulo con texto firmado-por + razón + fecha (layout 1).
- **information2**: layout alternativo del estampado de texto.

### Posicionamiento

`llx, lly` son coordenadas en píxeles desde la **esquina inferior
izquierda** de la página (sistema PDF estándar). Página tamaño A4 ≈
612 × 792 px.

Ejemplos:
- Esquina inferior izquierda: `llx=20, lly=20`
- Esquina inferior derecha:    `llx=400, lly=20`
- Esquina superior derecha:    `llx=400, lly=700`
- Centro:                       `llx=250, lly=400`

## Requisitos del usuario final

1. **FirmaEC Desktop** instalado: <https://www.firmadigital.gob.ec/>
   Registra el protocolo `firmaec://` en el SO.
2. **Certificado** emitido por una AC ecuatoriana autorizada (BCE, Security
   Data, Anf AC, Consejo de la Judicatura, ESET, Lazos EC, EC-LACSEC).
3. **URL del API** en la lista blanca (`apiurl` BD). El Setup lo registra.

## Por qué este flujo (y no firma directa)

En FirmaEC el certificado **nunca** sale de la PC del usuario. Esto es
requisito para que la firma tenga validez legal en Ecuador. El backend solo:

- Recibe el documento.
- Genera token corto (JWT con `cedula`, `sistema`, `ids`, `exp`).
- Espera que Desktop devuelva el firmado.
- Hace callback REST al sistema cliente.

Cualquier flujo que envíe el `.p12` + clave al servidor sería:
- Inseguro (servidor podría firmar a nombre del usuario sin su consentimiento).
- Sin validez legal (la AC requiere firma local).

# FirmaEC 5.1 — Fuentes MINKA

Coloque aquí los 3 ZIPs del código fuente upstream antes de ejecutar
`./build.sh`. **No se commiten** al repositorio (están en `.gitignore`).

## Archivos esperados

El build acepta cualquier nombre que matchee el patrón
`firmadigital-{libreria,api,servicio}*.zip`. Los nombres por defecto que
entrega MINKA son:

| Nombre                                  | Origen                                                                    |
|-----------------------------------------|---------------------------------------------------------------------------|
| `firmadigital-libreria-master.zip`      | <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-libreria>            |
| `firmadigital-api-master.zip`           | <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-api>                 |
| `firmadigital-servicio-master.zip`      | <https://minka.gob.ec/mintel/ge/firmaec/firmadigital-servicio>            |

## Cómo descargar

1. Ingresar a cada URL.
2. Botón **"Code"** → **"Download source code"** → formato **zip**.
3. Si quiere una versión distinta a `master`, seleccionar la rama/tag antes
   de descargar (botón "Select branch/tag" arriba a la izquierda).
4. Guardar el archivo aquí con su nombre original.

## Verificar

```bash
ls -lh recursos-minka/5.1/
# debe listar los 3 *.zip
```

`./build.sh` falla con un mensaje claro si falta alguno.

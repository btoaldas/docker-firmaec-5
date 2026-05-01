# Fuentes upstream MINKA — multi-versión

Este directorio aloja los ZIPs del código fuente de FirmaEC publicados por el
[MINTEL Ecuador en MINKA](https://minka.gob.ec/mintel/ge/firmaec).

## Layout

Cada versión tiene su propio subdirectorio:

```
recursos-minka/
├── 5.1/
│   ├── firmadigital-libreria-master.zip
│   ├── firmadigital-api-master.zip
│   └── firmadigital-servicio-master.zip
├── 5.2/   ← cuando salga, colocar aquí los nuevos ZIPs
└── ...
```

## Selector de versión

Variable `FIRMAEC_VERSION` en `.env` raíz:

```bash
FIRMAEC_VERSION=5.1
```

`build.sh` y `Dockerfile.build` leen esta variable y descomprimen los ZIPs del
subdirectorio correspondiente.

## Cómo agregar una nueva versión

1. Crear `recursos-minka/<X.Y>/`.
2. Descargar de MINKA los 3 ZIPs (libreria, api, servicio) correspondientes
   al tag/branch de esa versión.
3. Colocarlos con un nombre que matchee el patrón
   `firmadigital-{libreria,api,servicio}*.zip`.
4. Cambiar `FIRMAEC_VERSION=<X.Y>` en `.env`.
5. Ejecutar `./build.sh` para recompilar y `docker compose up --build -d` para
   redesplegar.

## Importante

- Los ZIPs **NO se commiten** al repositorio (están en `.gitignore`). Cada
  quien los descarga directamente del MINKA — ese es el upstream oficial.
- Si los `pom.xml` cambian entre versiones, los patches en `wildfly/patches/`
  pueden necesitar ajuste o ser eliminados (si MINKA fixeó los bugs upstream).

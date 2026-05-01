# Aviso legal y de atribución

## Sobre el código fuente FirmaEC

El sistema **FirmaEC** (componentes `firmadigital-libreria`, `firmadigital-api` y
`firmadigital-servicio`) es un proyecto de código abierto del **Ministerio de
Telecomunicaciones y de la Sociedad de la Información del Ecuador (MINTEL)**,
publicado a través de la plataforma **MINKA** (https://minka.gob.ec/mintel/ge/firmaec).

- Toda la **autoría intelectual del código fuente FirmaEC** corresponde
  enteramente al MINTEL Ecuador y a sus colaboradores oficiales.
- El **soporte oficial, mantenimiento y autoridad del proyecto** son
  responsabilidad exclusiva del MINTEL/MINKA.
- El código se distribuye bajo licencia **GNU AGPL v3**.

## Sobre este repositorio (`docker-firmaec-5`)

Este repositorio **NO es una versión oficial del MINTEL ni de MINKA**. Es un
empaquetado Docker comunitario, mantenido por terceros, cuyo único aporte es:

- Un `docker-compose.yml` que orquesta PostgreSQL, WildFly y un cliente de
  prueba PHP.
- Scripts auxiliares (`Dockerfile`, JBoss CLI, etc.) que automatizan
  compilación y despliegue.

El código fuente FirmaEC empaquetado se descarga directamente del MINKA en
tiempo de build; no se redistribuye dentro del repositorio.

## Sin garantía. Sin mantenimiento.

El autor de este repositorio (`@btoaldas`):

- **NO mantiene** el código fuente FirmaEC.
- **NO garantiza** que este empaquetado funcione con todas las versiones del
  upstream MINKA.
- **NO asume responsabilidad** por daños, pérdidas, fallos legales,
  fallos de firma electrónica o cualquier consecuencia derivada del uso de
  este software.
- **NO ofrece soporte** técnico, comercial ni legal.

El software se entrega **AS-IS / TAL CUAL**, bajo los términos de la licencia
**GNU AGPL v3**. Ver [LICENSE](LICENSE).

## Soporte oficial FirmaEC

Para soporte oficial, reporte de bugs, contribuciones al código fuente o
consultas sobre el proyecto FirmaEC original:

- Sitio MINKA: https://minka.gob.ec/mintel/ge/firmaec
- MINTEL Ecuador: https://www.telecomunicaciones.gob.ec/

## Cumplimiento AGPL v3

Al ser AGPL v3, cualquier modificación que se utilice para ofrecer un servicio
en red **debe publicar el código fuente modificado** a los usuarios del
servicio. Quien levante este stack para terceros queda obligado a respetar
esos términos. Ver el texto completo en [LICENSE](LICENSE).

## Uso del nombre y marcas

Los nombres "FirmaEC", "MINTEL", "MINKA" y similares son referenciados
únicamente con fines de atribución y descripción técnica. Este proyecto no
implica respaldo, alianza ni autorización oficial por parte de dichas
entidades.

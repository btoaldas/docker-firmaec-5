# Patches BUILD para FirmaEC 5.1 master

Estos patches son **mínimos e indispensables** para que el código actual del master
de MINKA compile sobre Java 17 + WildFly 36 + Jakarta EE 11. **NO modifican el
comportamiento funcional** del sistema FirmaEC: solo arreglan bugs de configuración
del proyecto Maven que están pendientes de MR (Merge Request) en el upstream.

## Aplicación

Se aplican automáticamente en `Dockerfile.build` (stage `patcher`) mediante
`apply-patches.sh`, que prueba 3 estrategias por patch:

1. `patch -p1 --fuzz=3`
2. `patch -p1 -l --fuzz=3` (ignora whitespace)
3. `git apply --whitespace=nowarn` (más tolerante)

Si MINKA fixea un bug upstream y el patch ya no aplica, el script falla con un
mensaje claro y el patch puede eliminarse.

## Inventario

### `01-servicio-pom-libreria-version-sync.patch`
Cambia la dependencia de `firmadigital-libreria` en `servicio/pom.xml` de
`5.0.0-SNAPSHOT` (valor obsoleto) a `5.1.0-SNAPSHOT` (valor real del upstream).

**Síntoma sin patch:**
```
Could not resolve dependencies for project ec.gob.firmadigital:servicio:war:5.1.0
Could not find artifact ec.gob.firmadigital:libreria:jar:5.0.0-SNAPSHOT
```

### `02-api-pom-jakarta-provided-scope.patch`
Marca `jakarta.activation-api:2.1.4` y `org.jboss.resteasy:resteasy-jaxrs:3.15.6.Final`
con `<scope>provided</scope>` en `api/pom.xml`.

**Síntoma sin patch:** WAR despliega correctamente, pero los endpoints REST
(`/api/fecha-hora`, `/api/appfirmardocumento`, etc.) devuelven HTML 404 en lugar
de JSON. Esto se debe a que las clases shadeadas dentro del WAR colisionan con
las que provee WildFly 36 (Jakarta EE 11).

### `03-api-pom-netbeans-source-directory.patch`
Declara `<sourceDirectory>src/java</sourceDirectory>` y un `<resource><directory>`
para `src/resources` en `api/pom.xml`.

**Síntoma sin patch:** Maven no encuentra fuentes y produce un `api.war` vacío,
pues el módulo usa el layout NetBeans antiguo (`src/java/`, `src/resources/`)
mientras Maven default busca en `src/main/java/`.

### `04-libreria-pom-compiler-plugin-java17.patch`
Declara `maven-compiler-plugin:3.14.1` con `<release>${java.version}</release>`
en `libreria/pom.xml`.

**Síntoma sin patch:**
```
[ERROR] Source option 5 is no longer supported. Use 7 or later.
[ERROR] Target option 5 is no longer supported. Use 7 or later.
```

El parent transitivo `maven-plugins-22` (vía `maven-clean-plugin:2.5`) pinea
`maven-compiler-plugin:3.1`, una versión de 2014 que ignora la propiedad
`maven.compiler.release` y defaultea a Java 5. El plugin original aparece
comentado en el pom upstream — este patch lo declara con versión moderna.

### `05-servicio-pom-compiler-plugin-java17.patch`
Mismo problema que el patch 04, en `servicio/pom.xml`. Aquí el plugin no
aparecía siquiera comentado; se inserta nuevo entre los `wildfly-maven-plugin`
y `maven-war-plugin` existentes.

## Patches que NO se aplican

Otros proyectos comunitarios aplican patches adicionales (URLs localhost, bypass
JWT, fecha-hora local, estampado custom) para adaptar FirmaEC a entornos sin
acceso a la infraestructura MINTEL. **Este repositorio no incluye esos patches**
porque el objetivo es entregar FirmaEC 5.1 **PURO**, tal como nace en MINKA.

Si necesita esas adaptaciones, puede agregar sus propios patches en este
directorio (numerados a partir de `04-`) y serán aplicados automáticamente.

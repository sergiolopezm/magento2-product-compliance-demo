# Selopez_ProductCompliance

Demo personal de portafolio para Magento Open Source / Mage-OS 2.4.7+, PHP >= 8.2. Valida la ficha de cumplimiento de productos de electrónica y hogar antes de guardarlos desde el formulario de Admin o mediante `ProductRepositoryInterface::save`.

**Autor:** Sergio Eduardo López Melgarejo. **Licencia:** MIT.

## Problema y reglas

Un catálogo puede contener GTIN mal digitados y productos sin una garantía definida. El módulo aplica dos reglas independientes:

- `gtin`: opcional. Si está informado, debe ser una cadena de 8, 12, 13 o 14 dígitos ASCII y superar el control GS1 módulo 10. Conserva los ceros iniciales; no admite espacios. Comprueba formato e integridad, no la asignación comercial del número por GS1.
- `warranty_months`: obligatorio cuando el producto está asignado directamente a una categoría configurada. Cuando se informa, se valida también en otras categorías: entero dentro del intervalo inclusivo configurado. Ausente significa `null` o cadena vacía; cero es un valor y solo es válido si el mínimo es cero.
- `block`: una `LocalizedException` detiene el guardado cuando hay violaciones, tanto en el repositorio como en el evento previo del modelo.
- `warn`: permite continuar el guardado y registra un aviso PSR-3 con SKU, códigos, atributos y mensajes. El aviso indica validación fallida antes del intento de persistencia; no certifica que el guardado posterior termine correctamente.

Las reglas generan las mismas violaciones en ambos modos. Al deshabilitar el módulo se omite el pool y el resultado indica `enabled: false`, `valid: true`, `violations: []`.

## Arquitectura

```text
ProductRepositoryInterface::save → Plugin beforeSave ──┐
                                                      ├→ ComplianceEnforcer → ComplianceChecker → pool de reglas
catalog_product_save_before     → Observer ────────────┘                           ↑                ├─ GtinValidator
REST GET /V1/product-compliance/:sku ───────────────────────────────────────────────┘                └─ WarrantyValidator
                                                                                  │
                                                                      ProductRepositoryInterface::get
```

`catalog_product_save_after → ReleaseComplianceOnProductSave → ComplianceEnforcer::release`

Los contratos están en `src/Api/`; los resultados y violaciones son inmutables. DI registra sus implementaciones y el array de reglas. El plugin y el observer delegan en `ComplianceEnforcer`, que coordina el bloqueo o aviso. El observer se registra globalmente en `etc/events.xml` y cubre el guardado del formulario de Admin. No se reemplaza ninguna clase de Magento. La configuración utiliza `ScopeConfigInterface` y el registro utiliza `LoggerInterface`.

El enforcer marca los productos `DataObject` con `_selopez_compliance_checked` después de permitir el guardado. El repositorio copia los datos a la instancia que persiste; el flag evita repetir la validación y el aviso cuando se dispara también el evento del modelo. Es un dato transitorio, sin atributo EAV ni persistencia en la base de datos. Para implementaciones de `ProductInterface` que no sean `DataObject`, se usa un `WeakMap` por identidad. Si el bloqueo lanza una excepción, no se marca el producto y puede volver a validarse. `ReleaseComplianceOnProductSave`, registrado globalmente en `catalog_product_save_after`, llama a `ComplianceEnforcer::release()` para limpiar el flag y la entrada del `WeakMap`. Si el repositorio persiste una copia, también libera las instancias marcadas con el mismo SKU. Así se vuelve a validar cuando se modifica y guarda de nuevo el producto en la misma petición, sin liberar otros SKU.

`CategoryIdsResolver` prioriza el atributo `category_ids` recibido —incluido un array vacío para quitar asignaciones— y utiliza `extension_attributes.category_links` cuando no está presente. No llama métodos específicos de modelos ni carga categorías. No hereda requisitos de categorías padre.

## Instalación en una tienda

Se necesita una tienda ya instalada con Magento Open Source 2.4.7+ o una versión equivalente de Mage-OS y PHP >= 8.2 soportado por esa versión de la tienda. Este repositorio no contiene una tienda completa.

### Composer con repositorio path

Desde la raíz de la tienda, adapta la ruta absoluta al clon:

```bash
composer config repositories.selopez-product-compliance path /ruta/magento2-product-compliance-demo/src
composer require 'selopez/module-product-compliance:@dev'
bin/magento module:enable Selopez_ProductCompliance
bin/magento setup:upgrade
bin/magento cache:clean config
```

En producción, incluye el módulo en el proceso habitual de compilación y despliegue de la tienda.

### app/code

Copia **el contenido de `src/`** a `app/code/Selopez/ProductCompliance/` y ejecuta:

```bash
bin/magento module:enable Selopez_ProductCompliance
bin/magento setup:upgrade
bin/magento cache:clean config
```

El patch crea `gtin` (`varchar`) y `warranty_months` (`int`), globales, visibles en Product Details y sin obligatoriedad EAV estática. Si ya existe cualquiera de esos códigos, falla antes de crear atributos para evitar sobrescribir definiciones ajenas. Revisa ese conflicto antes de instalar. No implementa `PatchRevertableInterface`: eliminar atributos destruiría datos del catálogo; deshabilitar el módulo los conserva. No se proporcionan productos ni categorías de prueba en la base de datos.

## Configuración

En **Stores > Configuration > Catalog > Product Compliance**, con alcance **Default Config**:

| Campo | Ruta bajo `product_compliance/general/` | Predeterminado |
|---|---|---|
| Enabled | `enabled` | `1` |
| Enforcement mode | `mode` | `block` |
| Categories requiring warranty | `warranty_category_ids` | Vacío |
| Minimum warranty months | `warranty_min_months` | `1` |
| Maximum warranty months | `warranty_max_months` | `120` |

Las categorías se indican mediante IDs separados por comas, por ejemplo `12,34`. Los IDs pertenecen a la tienda; este ejemplo es ficticio. La configuración y los atributos son globales. Los límites deben ser enteros no negativos y mínimo <= máximo. Una configuración inválida genera una excepción cuando se consulta; tampoco se acepta un modo distinto de `block` o `warn`.

Asigna el permiso **Product Compliance Configuration** al rol administrativo que configure el módulo. La API exige el permiso separado **Check Product Compliance** (`Selopez_ProductCompliance::check`) en el rol o integración del token; no permite acceso anónimo.

## REST

Con una integración autorizada, sustituye el host y define `ACCESS_TOKEN` fuera del repositorio:

```bash
curl --fail-with-body \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H 'Accept: application/json' \
  'https://shop.example/rest/default/V1/product-compliance/DEMO-TV-55'
```

Ejemplo ficticio de producto: SKU `DEMO-TV-55`, `gtin = "4006381333931"`, `warranty_months = 24`, categoría `12`. Respuesta `200`:

```json
{
  "sku": "DEMO-TV-55",
  "enabled": true,
  "valid": true,
  "violations": []
}
```

Con `gtin = "4006381333932"` y sin garantía, categoría `12` configurada como obligatoria, en locale `en_US`:

```json
{
  "sku": "DEMO-TV-55",
  "enabled": true,
  "valid": false,
  "violations": [
    {
      "code": "gtin_checksum",
      "attribute": "gtin",
      "message": "GTIN check digit is invalid."
    },
    {
      "code": "warranty_required",
      "attribute": "warranty_months",
      "message": "Warranty months are required for this product category."
    }
  ]
}
```

La consulta no modifica el producto ni aplica bloqueo o logging; siempre devuelve el diagnóstico con `200` si la consulta se completa. Un SKU inexistente propaga `NoSuchEntityException` (`404` en REST). Magento gestiona los errores de autenticación/autorización. Los mensajes se traducen según el locale; los códigos de violación son estables. Los números y SKU del ejemplo son ficticios y no implican titularidad de un GTIN.

## Pruebas y estilo con Docker

No se necesita PHP ni Composer en el host. Ejecuta desde la raíz del clon, con Docker Desktop iniciado. `composer.json` en la raíz es solo para desarrollo; el paquete instalable es `src/composer.json`.

El repositorio público de Mage-OS resuelve los paquetes sin credenciales de Adobe. Se fijan los alias `magento/framework:103.0.7-p4` y `magento/module-catalog:104.0.7-p4`, correspondientes a Mage-OS `1.0.6`; no se requiere el metapaquete de una tienda. Catalog arrastra sus dependencias transitivas oficiales. Se solicita también el coding standard disponible en el mirror. Los plugins y scripts de dependencias están deshabilitados.

```bash
docker run --rm -e COMPOSER_NO_SECURITY_BLOCKING=1 -v "$PWD":/app -w /app composer:2 install \
  --no-interaction --prefer-dist --no-plugins --no-scripts \
  --ignore-platform-req='ext-*' --ignore-platform-req='lib-*'

docker build -t selopez-product-compliance-php:8.3 -f docker/php/Dockerfile .

docker run --rm -v "$PWD":/app -w /app selopez-product-compliance-php:8.3 \
  php vendor/bin/phpunit

docker run --rm -v "$PWD":/app -w /app selopez-product-compliance-php:8.3 \
  php vendor/bin/phpcs --standard=phpcs.xml
```

`COMPOSER_NO_SECURITY_BLOCKING=1` se limita al contenedor de instalación de dependencias del **entorno de desarrollo de pruebas**: permite resolver `league/flysystem` 2.x, dependencia transitiva de Catalog bloqueada por avisos de seguridad. No se configura en `composer.json` ni debe aplicarse a una tienda de producción. Las dependencias de Magento del workspace de pruebas no se distribuyen con el módulo; la tienda resuelve sus propias dependencias.

La imagen de pruebas deriva de `php:8.3-cli` y agrega las extensiones de PHPUnit y PCOV. Las extensiones de una tienda completa no se instalan en esta imagen de pruebas. Composer usa una plataforma PHP `8.3.0`; se omite únicamente la comprobación de extensiones y bibliotecas del entorno de resolución. `composer.lock` conserva la resolución del entorno de desarrollo y no se ignora en `.gitignore`; `composer install` lo utiliza para repetirla.

Para cobertura de las clases de ejecución (`Model/`, `Plugin/` y `Observer/`):

```bash
mkdir -p build
docker run --rm -v "$PWD":/app -w /app selopez-product-compliance-php:8.3 \
  php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit \
  --coverage-text --coverage-clover build/coverage.xml
```

Objetivo: >= 80 % de líneas en ese alcance. Las pruebas cubren GTIN, garantías, categorías, límites, independencia del modo, agregación del pool, configuración inválida y los caminos block/warn/deshabilitado del enforcer, la marca transitoria, la copia de datos entre instancias, la liberación tras guardar, la validación de una segunda modificación y la delegación de plugin y observers. Usan mocks de las interfaces de Magento; no requieren una base de datos ni inicializan una tienda.

### Estándares

La puerta de estilo es PSR-12 (`phpcs.xml`). Para una revisión adicional con el estándar completo de Magento, el alias del mirror instala su implementación bajo `mage-os`:

```bash
docker run --rm -v "$PWD":/app -w /app selopez-product-compliance-php:8.3 \
  php vendor/bin/phpcs --standard=vendor/mage-os/magento-coding-standard/Magento2 --extensions=php,xml src
```

Se aceptan deliberadamente los avisos de anotación `Method annotation structure no comment` y `Missing short description`: el estilo del proyecto prohíbe comentarios descriptivos. Los docblocks de `Api/` conservan los tipos necesarios para la reflexión de Webapi. Las anotaciones técnicas `@param` y `@var` pueden precisar tipos sin añadir descripciones. No se agregan exclusiones del estándar para ocultar esos avisos.

### Estado de verificación

- PHPUnit 10.5 sobre PHP 8.3: `OK (89 tests, 208 assertions)`.
- PHPCS PSR-12 (`phpcs.xml`): sin errores ni avisos.
- PHPCS Magento2 (`--extensions=php,xml src`): 0 errores; solo los avisos de anotación aceptados arriba.
- Dependencias de desarrollo instaladas con `COMPOSER_NO_SECURITY_BLOCKING=1` (ver sección de pruebas).
- Pendiente: instalación y prueba manual en una tienda Magento Open Source / Mage-OS.

Queda pendiente verificar en una tienda real: `setup:upgrade`, generación DI, atributos en Admin, ACL, serialización REST y guardados desde Admin y repositorio con categorías. Las pruebas unitarias no sustituyen esa integración. El evento global cubre los guardados del modelo de producto que lo despachan; importaciones masivas, operaciones directas de recursos, cambios de categorías por otros servicios y escrituras SQL que omitan ambos puntos de entrada pueden eludir la validación. `warn` no corrige datos: el backend EAV `int` de Magento puede convertir valores durante la persistencia.

## Extender el pool

Crea una clase que implemente `Model\Validator\ProductRuleInterface` y devuelva un array de `Api\Data\ViolationInterface`, vacío cuando cumple. Inyecta sus dependencias por constructor. Registra una entrada en el array `rules` de `etc/di.xml`, desde el módulo que aporta la nueva regla:

```xml
<item name="new_rule" xsi:type="object">Vendor\Module\Model\Validator\NewRule</item>
```

No cambies el checker, el enforcer, el plugin ni el observer. Agrega pruebas para la regla, usa códigos de violación estables y traduce sus mensajes. El checker acumula todas las violaciones respetando el orden del pool.

## Referencias

- [GS1: cálculo del dígito de control](https://www.gs1.org/services/check-digit-calculator).
- [Adobe: contratos de servicios para Web API](https://developer.adobe.com/commerce/php/development/components/web-api/services).
- [Mirror Composer de Mage-OS](https://repo.mage-os.org/).

## English summary

Personal portfolio module for Magento Open Source / Mage-OS 2.4.7+ and PHP 8.2+. It validates optional GTIN values with the GS1 checksum and requires warranty months for configured category assignments. A repository plugin and a global product-save observer delegate to a shared enforcer that blocks invalid saves or logs structured warnings. A transient flag prevents duplicate enforcement across both entry points. An ACL-protected REST endpoint returns an immutable compliance report. Rules are extended through a DI pool. The repository includes PHPUnit unit tests, Docker tooling, PSR-12 configuration and Spanish/English translations. The post-save observer releases transient flags so modified products are checked again on subsequent saves. Runtime verification of this revision remains pending; its test result placeholder appears above. Annotation warnings requiring descriptive docblocks are accepted by project style. MIT license. Author: Sergio Eduardo López Melgarejo.

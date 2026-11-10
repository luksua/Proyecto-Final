# Cursos y Matrículas con pagos e identidad institucional

Aplicación Laravel 12 existente, ampliada como monolito con servicios de negocio y adaptadores. Se conservan las 17 rutas académicas y el login JWT; se añaden pagos, identidad, auditoría, métricas y avisos. La interfaz sigue usando HTML, CSS y JavaScript sin framework adicional.

## Ejecutar la aplicación existente

Desde `taller4-api`, con PHP 8.2 o superior, dependencias de Composer y MySQL/MariaDB disponibles:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Abrir http://127.0.0.1:8000. La API conserva `/api/v1`. Las migraciones agregan tablas, índices y una restricción de matrícula activa única; no reinician ni vacían la base. Si hubiera matrículas activas duplicadas, la migración se detiene antes de alterar la tabla para que se revisen los datos. En esta actualización se comprobó que los registros académicos y usuarios existentes permanecieran sin cambios.

Para una instalación vacía: `composer install`, copiar `.env.example` a `.env`, configurar una base previamente creada, generar APP_KEY con `php artisan key:generate` y ejecutar `php artisan migrate`. No reemplazar el `.env` de una instalación que ya funciona ni regenerar su clave. `php artisan db:seed` agrega las cuentas de demostración; usarlo exclusivamente en una base de desarrollo vacía, pues actualiza sus contraseñas.

## Demostración funcional

Las cuentas heredadas de desarrollo usan `password123`:

| Rol | Correo |
|---|---|
| ADMIN | admin@ut.edu.co |
| DOCENTE | carlos.rojas@ut.edu.co |
| ESTUDIANTE | ana.martinez@ut.edu.co |

ADMIN puede registrar docentes, estudiantes y cursos. ESTUDIANTE puede matricularse, generar una orden desde Matrículas y aprobar o rechazar un **pago simulado** desde Pagos. ADMIN puede reembolsar una orden aprobada. Una matrícula pagada no puede cancelarse hasta registrar el reembolso; el historial financiero impide borrar sus estudiantes o cursos. DOCENTE conserva las consultas académicas y no recibe información financiera.

No se cobran fondos reales ni se reciben datos de tarjetas. La tarifa predeterminada es una demostración de 50 000 COP por crédito, calculada en el servidor y congelada al crear la orden. No constituye una tarifa institucional. Los estados son PENDIENTE, APROBADO, RECHAZADO y REEMBOLSADO. ACTIVA sigue siendo un estado académico independiente.

## Estructura del código

- `app/Http/Requests`: validación de entradas.
- `app/Http/Controllers` y `Resources`: contratos HTTP/JSON y presentación de recursos.
- `app/Services`: reglas académicas, pagos, emisión de JWT e identidad.
- `app/Models`: persistencia Eloquent y relaciones.
- `app/Integrations/Payments`: interfaz del proveedor, simulación y adaptador SOAP simulado.
- `app/Integrations/Identity`: simulación local y adaptador OpenID Connect.
- `app/Observers`, `app/Jobs`, `app/Support`: auditoría, avisos asíncronos e invalidación de caché.
- `public/app/index.html`, `styles.css`, `app.js`: presentación web original ampliada.

La matrícula, la cancelación y los ajustes de cupo bloquean el mismo curso en una transacción. La base impide dos matrículas ACTIVA del mismo estudiante, curso y periodo; permite varias canceladas. La cancelación vuelve a comprobar el estado bajo bloqueo. Los pagos tienen una orden única por matrícula, bloqueo de matrícula y pago y claves de idempotencia persistentes. Los reintentos idénticos no producen otra transacción.

## Endpoints añadidos

Todas las rutas siguientes llevan `/api/v1`. Las rutas académicas anteriores siguen disponibles.

| Método | Ruta | Acceso y resultado |
|---|---|---|
| GET | /matriculas/{id} | ADMIN, propietario o docente del curso; 200 |
| POST | /pagos | ADMIN o propietario; body `matriculaId`; 201 al crear, 200 si ya existe |
| GET | /pagos | ADMIN o ESTUDIANTE; paginación y filtros `estado`, `matriculaId` |
| GET | /pagos/{id} | ADMIN o propietario; incluye historial |
| POST | /pagos/{id}/procesar | ADMIN o propietario; simulación, `resultado`: APROBADO, RECHAZADO o ERROR |
| POST | /pagos/{id}/reembolsar | ADMIN; solo un pago APROBADO |
| POST | /auth/oidc/inicio | Público limitado; `codeChallenge` PKCE S256 |
| POST | /auth/oidc/simulada | Solo desarrollo; state, email y password de cuenta local para simular al proveedor |
| POST | /auth/oidc/intercambio | Público limitado; code, state y codeVerifier |
| GET | /health | Comprobación de base de datos; 200 o 503, sin detalles internos |
| GET | /configuracion | Metadatos públicos de los drivers y modo simulado, sin credenciales |
| GET | /metricas | ADMIN; solicitudes, errores, duración promedio y máxima por ruta y día |
| GET | /auditoria | ADMIN; eventos paginados |
| GET | /notificaciones | Avisos paginados del usuario autenticado |

Procesar y reembolsar requieren `Idempotency-Key` de 8 a 100 caracteres. La misma clave y solicitud devuelve la orden sin repetir el efecto; otra solicitud con esa clave devuelve 409. ERROR simula indisponibilidad con 503 y mantiene la orden pendiente, sin cobro. No se interpreta un fallo externo real como ausencia de cobro: un proveedor bancario real requerirá reserva, consulta y conciliación antes de conectarlo al servicio.

Los recursos individuales continúan sin envoltorio `data`; los listados conservan paginación. Los errores contienen timestamp, status, error, message, path y correlationId. Se preservan Retry-After y Allow. Las respuestas incluyen X-Correlation-ID y X-Response-Time-Ms. Las sesiones JWT anteriores a la actualización necesitan un nuevo login porque ahora se comprueban emisor y audiencia.

## Identidad externa

El valor predeterminado `IDENTITY_DRIVER=simulado` permite demostrar PKCE y estado de un solo uso, usando una cuenta local para representar al proveedor. La pantalla lo identifica como simulación. Las simulaciones se bloquean en producción y cuando `SIMULATED_INTEGRATIONS_ENABLED=false`.

El adaptador `IDENTITY_DRIVER=oidc` utiliza Authorization Code con PKCE, nonce y validación de ID Token firmado con RS256, issuer, audience, expiración y subject. Sus endpoints se configuran explícitamente y deben usar HTTPS; no acepta URLs del navegador. Se soporta cliente público con PKCE o confidencial con `client_secret_post`, según el registro del proveedor. No hay proveedor institucional real configurado ni se afirma una prueba contra él.

Variables en `.env.example`: `OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_REDIRECT_URI`, `OIDC_AUTHORIZATION_ENDPOINT`, `OIDC_TOKEN_ENDPOINT` y `OIDC_JWKS_URI`. Registrar como callback la URL exacta del cliente `https://tu-dominio/app/index.html`. Proporcionar secretos por configuración del servidor, nunca al JavaScript.

La identidad externa se vincula por `(issuer, subject)` con comparación sensible a mayúsculas; no se unen cuentas por correo. Aprovisionar desde una consola administrativa confiable:

```powershell
php artisan identidad:vincular ID_USUARIO SUBJECT --issuer=https://proveedor.example
```

La operación no cambia el rol local y rechaza reasignar una identidad vinculada a otra cuenta. El ID Token externo no es un Bearer de la API; tras validarlo se emite el mismo tipo de JWT local que usa el login existente.

## SOAP y proveedores de pago

`PAYMENTS_DRIVER=simulado` es el proveedor local predeterminado. `PAYMENTS_DRIVER=soap_simulado` ejercita la conversión de la solicitud a XML SOAP 1.2, usando la misma interfaz de negocio. No realiza llamadas de red ni necesita la extensión SOAP de PHP. No hay integración bancaria productiva habilitada.

`BANK_SOAP_WSDL` y `BANK_SOAP_CERTIFICATE` reservan configuración para un contrato futuro; no activan un banco. Para producción falta el WSDL real, credenciales/certificados, implementación y pruebas del transporte y del perfil WS-Security acordado, idempotencia del banco, estados desconocidos y conciliación. No sustituir el simulador por llamadas de red dentro de la transacción SQL actual. SOAP no garantiza atomicidad entre base de datos y banco.

## Colas caché y operación

Se utilizan los drivers de base de datos ya incluidos en Laravel. No se agregaron Redis, Docker ni servicios externos. Los eventos de aviso se guardan en `notification_outbox` junto con la transacción académica o financiera. El despachador envía trabajos a la cola y el job entrega avisos internos idempotentes; no envía correos ni mensajes externos.

Para despachar y procesar una tanda manualmente:

```powershell
php artisan notificaciones:despachar
php artisan queue:work --stop-when-empty --tries=3
```

Para operación continua, supervisar `php artisan queue:work --tries=3` y ejecutar el scheduler de Laravel cada minuto, o `php artisan schedule:work` durante desarrollo. El scheduler recupera avisos no entregados y elimina flujos temporales de identidad vencidos. No duplicar servicios de scheduler sin sus bloqueos compartidos.

El catálogo usa caché de 60 segundos y una versión compartida que cambia al crear, editar, eliminar o modificar cupos. La decisión de matrícula siempre consulta el primario bajo bloqueo. Los índices cubren filtros de cursos y matrículas. Los listados de la interfaz tienen Anterior/Siguiente y las consultas nuevas validan límites de paginación.

Los eventos JSON están en `storage/logs/events-YYYY-MM-DD.log`. No incluyen cuerpos de solicitudes, contraseñas, JWT ni datos de tarjetas. La auditoría persiste actor, recurso, cambio de estado y correlación; las métricas son contadores, promedio y máximo, no percentiles ni una demostración de capacidad.

## Producción y varias instancias

Antes de desplegar: `APP_ENV=production`, `APP_DEBUG=false`, APP_URL y callback HTTPS, certificado válido, claves seguras comunes a las instancias, cuentas sin contraseñas de demostración, simulaciones deshabilitadas y un proveedor real implementado/configurado. El middleware rechaza API sobre HTTP en producción (`REQUIRE_HTTPS=true`). `.htaccess` incorpora cabeceras de seguridad para Apache; un servidor diferente debe configurar sus equivalentes. TLS no se activa solo por cambiar una variable.

Si existe un proxy, declarar únicamente sus IP/CIDR confiables mediante `TRUSTED_PROXIES`; no confiar indiscriminadamente en X-Forwarded-Proto. Mantener iguales código, claves JWT, esquema y prefijo de caché, con `CACHE_STORE=database` y `QUEUE_CONNECTION=database` en todas las instancias. Las capas son lógicas dentro del mismo monolito. No se requiere afinidad de sesiones para Bearer JWT. Reiniciar workers después de desplegar código y respaldar la base con un procedimiento de restauración probado.

El navegador mantiene el JWT en memoria y vuelve al login al recargar; solo persiste temporalmente state/verificador PKCE. Las credenciales de demostración se muestran únicamente con simulaciones habilitadas en desarrollo. Balanceo, certificados, supervisión de workers, recuperación y pruebas de carga con varias instancias dependen del entorno de despliegue y no se presentan como realizados.

## Pruebas y resultados

```powershell
php vendor/bin/phpunit
php vendor/bin/pint --test
php tests/Support/mysql_concurrency.php
```

PHPUnit migra únicamente una base SQLite en memoria, sin reiniciar bases existentes. El ensayo MySQL crea una base nueva `integrador_test_*`, conserva sus resultados para inspección y no la elimina automáticamente. Se ejecutó con PHP 8.3.36 y MariaDB 10.11.16: ocho procesos por escenario validaron último cupo, matrícula duplicada, doble cancelación e idempotencia del pago. Esto verifica consistencia concurrente, no capacidad o disponibilidad bajo carga.

La colección original contiene **56 solicitudes** y se conserva sin modificaciones. Se ejecutaron sus scripts de estado y variables con `tests/Support/postman-runner.cjs`, sin agregar dependencias al proyecto. Este ejecutor no pretende implementar todas las funciones de Newman. También puede importarse la colección en Postman y ejecutarse en orden sobre una base de pruebas sembrada; contiene altas y bajas, por lo que no debe apuntarse a datos de uso real.

Para recrear un entorno HTTP aislado de demostración:

```powershell
php tests/Support/prepare_demo.php
# Consultar el nombre devuelto y usarlo solo en esta consola de pruebas.
$env:DB_DATABASE='integrador_test_NOMBRE_DEVUELTO'
$env:APP_ENV='testing'
php artisan serve --host=127.0.0.1 --port=8011 --no-reload
```

En otra consola, el ejecutor de la colección recibe `http://127.0.0.1:8011/api/v1`. `tests/Support/browser-smoke.cjs` verifica los tres roles, pagos, reembolso, OIDC simulado y móvil mediante Playwright y Chrome cuando estén disponibles en el entorno; son herramientas de verificación, no dependencias del backend. Las capturas y resultados se guardan en `storage/framework/testing/browser`. No se probaron banco ni proveedor institucional reales.

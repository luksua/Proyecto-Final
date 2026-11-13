  # Configurar Mercado Pago en Colombia para pruebas

El código integra **Checkout API mediante Card Payment Brick** (`POST /v1/payments`): el navegador tokeniza la tarjeta directamente con el SDK de Mercado Pago usando `MP_PUBLIC_KEY`, y Laravel crea el pago en el servidor con el importe real de la orden sin redireccionar fuera de la aplicación. No se reciben ni almacenan números de tarjeta ni CVV en la universidad.

La integración está implementada, pero la compra de extremo a extremo requiere credenciales y una dirección pública HTTPS. Las pruebas automatizadas usan `Http::fake`, no una cuenta real. No se han efectuado cobros ni validado una compra contra Mercado Pago en esta actualización.

## 1 Crear la aplicación y las cuentas de prueba

En [Tus integraciones](https://www.mercadopago.com.co/developers/panel/app), crea o selecciona una aplicación Checkout Pro. Configura el entorno de pruebas siguiendo la [guía oficial de compras de prueba](https://www.mercadopago.com.co/developers/es/docs/checkout-pro-preferences/integration-test/test-purchases).

Usa las credenciales del vendedor destinadas a ese entorno y una cuenta compradora de prueba distinta, ambas de Colombia. Comprueba en el panel qué credenciales corresponden al vendedor de prueba; no determines el entorno solo por el prefijo del token. Usa tarjetas de prueba y una ventana de incógnito para el comprador. No utilices cuentas ni tarjetas personales para esta demostración.

Necesitarás el **Access Token del vendedor de prueba**, su **ID de usuario vendedor** (collector_id) y la **clave secreta de Webhooks**. El ID del vendedor no es el Client ID/Application ID. Puedes obtenerlo de la cuenta vendedora o de `/users/me` autenticado con ese token desde un cliente privado. No publiques la respuesta ni el token en capturas.

## 2 Hacer accesible Laravel por HTTPS

Inicia MySQL en XAMPP y, desde `taller4-api`, ejecuta:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Necesitas una URL pública HTTPS de desarrollo que apunte a ese servidor. Si usas un túnel de desarrollo, configúralo por separado y abre la aplicación desde esa misma URL. No se instaló un túnel ni se publicó la aplicación automáticamente. El DocumentRoot de un alojamiento debe ser `public`, nunca la raíz del proyecto ni su `.env`.

`MP_PUBLIC_URL` debe contener el origen público de la aplicación, por ejemplo `https://tu-url-de-pruebas.example`, sin `/app/index.html`, sin `/api/v1`, sin query y sin barra final. Si cambia el dominio de tu túnel, actualiza las variables y la URL de Webhooks; las preferencias ya creadas conservan su callback anterior.

## 3 Configurar el webhook

En la aplicación de Mercado Pago, configura una notificación de **Pagos / payment** para el entorno de pruebas con esta dirección:

```text
https://TU-DOMINIO/api/v1/webhooks/mercadopago
```

Copia la clave secreta de esa configuración en `MP_WEBHOOK_SECRET`. No uses el Access Token como secreto de webhook. Esta ruta pública no necesita JWT, pero comprueba `x-signature` y `x-request-id` usando el identificador de la query `data.id`. Después consulta `/v1/payments/{id}` con el token privado. No aprueba pagos basándose en el body de la notificación.

## 4 Completar las variables locales

Completa estos valores **en `.env`, no en el chat ni en archivos públicos**:

```dotenv
PAYMENTS_DRIVER=mercadopago
PAYMENTS_CURRENCY=COP
MP_TEST_MODE=true
MP_PUBLIC_KEY=
MP_ACCESS_TOKEN=
MP_COLLECTOR_ID=
MP_WEBHOOK_SECRET=
MP_PUBLIC_URL=
```

Los campos vacíos deben tener los valores de los pasos anteriores. Nunca reemplaces APP_KEY ni borres tus variables de base de datos. Mantén `MP_TEST_MODE=true` durante esta integración académica; el backend exige `live_mode=false` al confirmar un pago. No actives producción sin una revisión separada.

Después de editar:

```powershell
php artisan config:clear
php artisan mercado-pago:estado
```

El segundo comando muestra únicamente qué valores faltan, sin imprimir secretos ni efectuar llamadas externas. Hasta completar estos datos, conserva `PAYMENTS_DRIVER=simulado` para utilizar las demostraciones anteriores.

## 5 Probar el recorrido completo

1. Abre el portal desde la URL pública e inicia sesión como ESTUDIANTE o ADMIN.
2. Matricula un curso y genera una orden nueva. El precio se calcula en Laravel con `PAYMENTS_CREDIT_PRICE_CENTS` y queda congelado en la orden; el valor heredado sigue siendo una tarifa de demostración de 50 000 COP por crédito.
3. Abre Pagos y pulsa **Pagar con Mercado Pago**. En modo de pruebas se usa `sandbox_init_point`.
4. Paga con la cuenta compradora y tarjeta de prueba según la documentación del proveedor.
5. Al volver, inicia sesión otra vez (el JWT local no se conserva tras una navegación completa). Abre Pagos y pulsa **Consultar estado**. El backend busca por la referencia local, sin confiar en `payment_id`, `status=approved` ni otros parámetros del navegador.
6. Verifica la orden, su historial y la recepción del webhook. Un webhook de simulación del panel con un ID inexistente solo verifica conectividad/firma: no acredita una compra.

Las órdenes antiguas de `simulado` conservan su proveedor y su historial. Para Mercado Pago usa una nueva matrícula y orden; no se convierten pagos históricos ni se manipulan importes al cambiar el driver. El driver es global: para volver a procesar las órdenes simuladas cambia a `simulado` de nuevo.

## Estados y protecciones

- Se reutiliza una sola preferencia por orden. Un timeout de creación deja la solicitud bloqueada para revisión, evitando crear otro checkout a ciegas. La preferencia dura 24 horas; vencimiento o pérdida de su URL requieren revisión y resolución manual del proveedor, sin recreación automática.
- APROBADO solo procede de la API autenticada de Mercado Pago, verificando referencia, importe, moneda COP, vendedor y modo de prueba.
- Los intentos rechazados/cancelados se guardan en el historial; la orden sigue PENDIENTE porque la preferencia puede contener un intento posterior. Los avisos no equivalen a un nuevo cobro.
- Las notificaciones duplicadas y antiguas no repiten efectos ni revierten una confirmación posterior del mismo pago. Cada ID remoto se conserva por separado; si Mercado Pago registra dos pagos aprobados, la orden queda marcada para revisión. Reutilizar una preferencia no garantiza que el proveedor impida todos los dobles pagos: la conciliación permite detectarlos.
- Contracargos, mediaciones y reembolsos parciales se marcan para revisión. Esta versión no automatiza su resolución financiera.
- La cancelación académica queda bloqueada después de solicitar un checkout externo. Un checkout puede pagarse fuera de la aplicación, de modo que un administrador debe resolverlo en Mercado Pago antes de cualquier cancelación. No hay desbloqueo automático.
- Los reembolsos externos se gestionan en el panel de Mercado Pago y se reflejan mediante webhook o sincronización. El endpoint de reembolso simulado nunca marca un pago externo como reembolsado por sí mismo.
- No se transmiten tarjetas, contraseñas ni JWT de la universidad a Mercado Pago. El token del vendedor permanece en Laravel. No hay llamadas bancarias dentro de una transacción SQL.

Para recuperar notificaciones perdidas manualmente:

```powershell
php artisan pagos:conciliar-mercadopago
```

El scheduler existente ejecuta esa consulta cada cinco minutos sobre hasta 20 órdenes pendientes/aprobadas. Requiere que el scheduler esté operando; no basta con abrir la página. El comando consulta estados, no inicia cobros ni reembolsos. Revisar periódicamente conciliación y alertas sigue siendo responsabilidad administrativa.

## Pruebas locales

```powershell
php vendor/bin/phpunit --filter MercadoPagoTest
php vendor/bin/phpunit
node --check public/app/app.js
```

Las pruebas cubren checkout con importe del servidor, preferencia reutilizada, firma válida/inválida, idempotencia, reembolso observado, eventos fuera de orden, propietario, importe/moneda/vendedor/entorno incorrectos, timeout ambiguo, sincronización y detección de dos aprobaciones. Usan SQLite en memoria y HTTP simulado. Una compra de prueba real y su webhook deben verificarse después de configurar las credenciales.

## Referencias oficiales

- [Preferencias de Checkout Pro](https://www.mercadopago.com.co/developers/es/docs/checkout-pro-preferences/create-payment-preference).
- [URLs de retorno](https://www.mercadopago.com.co/developers/es/docs/checkout-pro-preferences/configure-back-urls).
- [Webhooks y consulta del pago](https://www.mercadopago.com.co/developers/es/docs/checkout-pro-orders/resources/notifications/webhooks).
- [Validador HMAC del SDK oficial PHP](https://github.com/mercadopago/sdk-php/blob/master/src/MercadoPago/Webhook/WebhookSignatureValidator.php).
- [Compras de prueba en Colombia](https://www.mercadopago.com.co/developers/es/docs/checkout-pro-preferences/integration-test/test-purchases).

# PetIA App Bridge

Plugin de WordPress que expone endpoints REST para ser consumidos por la aplicación PetIA.

## Características
- Autenticación mediante tokens JWT con expiración de 24 h y revocación.
- Endpoints de registro, inicio de sesión, perfil, productos, pedidos y más bajo `/wp-json/petia-app-bridge/v1/`.
- Proxy genérico para WooCommerce.
- Manejo de CORS configurable mediante la constante `PETIA_ALLOWED_ORIGINS` (por defecto `*`).
- Panel de administración "PetIA Bridge" con pestañas para control de acceso y ejecución de pruebas Node.

## Instalación
1. Copia la carpeta `PetIA-app-bridge` en `wp-content/plugins/`.
2. Asegúrate de definir `AUTH_KEY` en `wp-config.php`.
3. (Opcional) Define `PETIA_ALLOWED_ORIGINS` para restringir los orígenes permitidos.
4. Activa el plugin desde el panel de WordPress.

Las dependencias PHP necesarias ya están incluidas, por lo que no es necesario ejecutar `composer install`.

## Endpoints disponibles

- `POST /wp-json/petia-app-bridge/v1/register`
- `POST /wp-json/petia-app-bridge/v1/login`
- `POST /wp-json/petia-app-bridge/v1/logout`
- `GET /wp-json/petia-app-bridge/v1/validate-token`
- `POST /wp-json/petia-app-bridge/v1/password-reset-request`
- `POST /wp-json/petia-app-bridge/v1/password-reset`
- `GET /wp-json/petia-app-bridge/v1/profile`
- `POST /wp-json/petia-app-bridge/v1/profile`
- `GET /wp-json/petia-app-bridge/v1/order/<id>/addresses`
- `POST /wp-json/petia-app-bridge/v1/order/<id>/addresses`
- `GET /wp-json/petia-app-bridge/v1/product-categories`
- `GET /wp-json/petia-app-bridge/v1/products`
- `GET /wp-json/petia-app-bridge/v1/brands`
- `* /wp-json/petia-app-bridge/v1/wc/<endpoint>` (proxy WooCommerce)

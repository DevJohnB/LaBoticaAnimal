# PetIA App Bridge

Plugin de WordPress que expone endpoints REST para que aplicaciones externas usen WordPress y WooCommerce como backend.

## Características
- Registro y autenticación de usuarios mediante tokens firmados con `AUTH_KEY`.
- Gestión de perfil, recuperación de contraseña y direcciones de pedidos.
- Catálogo de categorías, productos y marcas.
- Control de acceso por usuario con fechas de vigencia.
- Menú de administración **PetIA Bridge** con pestañas de acceso y ejecución de pruebas.

## Requisitos
- WordPress 5.0 o superior.
- WooCommerce.
- Definir `AUTH_KEY` en `wp-config.php`.
- Node.js si se desean correr pruebas desde el administrador.

## Instalación
1. Copia `PetIA-app-bridge` a `wp-content/plugins/`.
2. Activa el plugin en el panel de WordPress.
3. Gestiona las fechas de acceso desde **PetIA Bridge** en la pestaña *Access Control*.
4. (Opcional) Ejecuta las pruebas desde la pestaña *Run Tests* del mismo menú.

## Endpoints
### Autenticación
- `POST /wp-json/petia-app-bridge/v1/register` – crea usuario (`email`, `password`).
- `POST /wp-json/petia-app-bridge/v1/login` – devuelve token (`email`, `password`).
- `POST /wp-json/petia-app-bridge/v1/logout` – revoca token.
- `GET /wp-json/petia-app-bridge/v1/validate-token` – datos del usuario si el token es válido.

### Recuperación de contraseña
- `POST /wp-json/petia-app-bridge/v1/password-reset-request` – envía correo de recuperación.
- `POST /wp-json/petia-app-bridge/v1/password-reset` – restablece contraseña (`login`, `key`, `password`).

### Perfil
- `GET /wp-json/petia-app-bridge/v1/profile` – datos del usuario autenticado.
- `POST /wp-json/petia-app-bridge/v1/profile` – actualiza campos opcionales (`first_name`, `last_name`, `nickname`, `description`, `user_url`, `display_name`).

### Pedidos
- `GET /wp-json/petia-app-bridge/v1/order/<id>/addresses` – direcciones de un pedido.
- `POST /wp-json/petia-app-bridge/v1/order/<id>/addresses` – actualiza direcciones (`billing`, `shipping`).

### Catálogo
- `GET /wp-json/petia-app-bridge/v1/product-categories`.
- `GET /wp-json/petia-app-bridge/v1/products` (`per_page`, `page`).
- `GET /wp-json/petia-app-bridge/v1/brands`.

Todos los endpoints, salvo **register**, **login**, **password-reset-request** y **password-reset**, requieren el encabezado `Authorization: Bearer <token>`.

## Control de acceso
Al activarse se crea la tabla `wp_petia_app_bridge_access` con los campos `allowed`, `start_date` y `end_date`. Desde la pestaña *Access Control* del menú **PetIA Bridge** se administra la vigencia del acceso de cada usuario.

## Seguridad
- El token se firma con `AUTH_KEY`, por lo que debe ser único y secreto.
- El plugin elimina su tabla de acceso y transients de tokens revocados al desinstalarse.

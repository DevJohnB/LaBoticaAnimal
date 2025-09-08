# PetIA App Bridge

Este plugin de WordPress expone un conjunto de endpoints REST para que una aplicación externa pueda registrar y autenticar usuarios utilizando a WordPress como backend.

## Endpoints

- `POST /wp-json/petia-app-bridge/v1/register`
  - Crea un nuevo usuario en WordPress.
  - Parámetros: `email`, `password`.
  - El nombre de usuario se genera automáticamente y se devuelve en la respuesta.

- `POST /wp-json/petia-app-bridge/v1/login`
  - Autentica al usuario y devuelve un token.
  - Parámetros: `email` o `username`, `password`.

- `POST /wp-json/petia-app-bridge/v1/logout`
  - Revoca el token actual enviado en el encabezado `Authorization: Bearer <token>`.

- `GET /wp-json/petia-app-bridge/v1/validate-token`
  - Verifica el token enviado en el encabezado `Authorization: Bearer <token>` y devuelve los datos del usuario si es válido.

- `POST /wp-json/petia-app-bridge/v1/password-reset-request`
  - Envía un correo de recuperación de contraseña al usuario.
  - Parámetros: `username` o `email`.

- `POST /wp-json/petia-app-bridge/v1/password-reset`
  - Restablece la contraseña usando la clave recibida por correo.
  - Parámetros: `login`, `key`, `password`.

- `GET /wp-json/petia-app-bridge/v1/profile`
  - Devuelve la información opcional del perfil del usuario autenticado.

- `POST /wp-json/petia-app-bridge/v1/profile`
  - Actualiza los campos opcionales del perfil del usuario autenticado.
  - Parámetros (opcionales): `first_name`, `last_name`, `nickname`, `description`, `user_url`, `display_name`.

- `GET /wp-json/petia-app-bridge/v1/order/<id>/addresses`
  - Devuelve la información de facturación y envío del pedido del usuario autenticado.

- `POST /wp-json/petia-app-bridge/v1/order/<id>/addresses`
  - Actualiza la información de facturación y envío del pedido.
  - Parámetros (opcionales): `billing` (objeto), `shipping` (objeto).

- `GET /wp-json/petia-app-bridge/v1/product-categories`
  - Devuelve la lista de categorías de productos con nombre, descripción, slug e imagen.

- `GET /wp-json/petia-app-bridge/v1/products`
  - Devuelve un listado de productos con nombre, descripción, slug, precio e imagen.
  - Parámetros (opcionales): `per_page` (número de resultados por página), `page` (página).

- `GET /wp-json/petia-app-bridge/v1/brands`
  - Devuelve la lista de marcas de productos con nombre, descripción, slug e imagen.

- `GET /wp-json/petia-app-bridge/v1/brands`
  - Devuelve la lista de marcas de productos con nombre, descripción, slug e imagen.

Todos los endpoints, con excepción de **login**, **register**, **password-reset-request** y **password-reset**, requieren enviar el token en el encabezado `Authorization`.

## Control de acceso

Al activarse, el plugin crea la tabla `wp_petia_app_bridge_access` con los campos:

- `allowed` para habilitar o deshabilitar el acceso a los endpoints.
- `start_date` con la fecha de inicio del acceso (por defecto la fecha en que se crea el registro).
- `end_date` con la fecha de fin de acceso (por defecto `31/12/9999`).

Al iniciar sesión, si el usuario no tiene registro en esta tabla se crea uno automáticamente con las fechas anteriores y se verifica que `end_date` sea mayor a la fecha actual. Si la fecha fin ya pasó, el acceso es rechazado. Desde el menú de administración **Usuarios → PetIA App Bridge Access** es posible modificar estas fechas para cada usuario.

## Instalación

1. Copiar la carpeta `PetIA-app-bridge` en el directorio `wp-content/plugins/` de WordPress.
2. Activar el plugin desde el panel de administración.
3. Desde el menú **Usuarios → PetIA App Bridge Access** se puede habilitar o deshabilitar el acceso a los endpoints para cada usuario. Por defecto todos los usuarios tienen acceso.

## Notas de seguridad

El plugin limpia sus datos (tabla de acceso y transients de tokens revocados) al desinstalarse.

El token generado es similar a un JWT y se firma con `AUTH_KEY` de WordPress. Asegúrate de tener una clave única y secreta definida en `wp-config.php`.

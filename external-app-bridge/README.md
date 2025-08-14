# External App Bridge

Este plugin de WordPress expone un conjunto de endpoints REST para que una aplicación externa pueda registrar y autenticar usuarios utilizando a WordPress como backend.

## Endpoints

- `POST /wp-json/external-app-bridge/v1/register`
  - Crea un nuevo usuario en WordPress.
  - Parámetros: `email`, `password`.
  - El nombre de usuario se genera automáticamente y se devuelve en la respuesta.

- `POST /wp-json/external-app-bridge/v1/login`
  - Autentica al usuario y devuelve un token.
  - Parámetros: `email` o `username`, `password`.

- `POST /wp-json/external-app-bridge/v1/logout`
  - Revoca el token actual enviado en el encabezado `Authorization: Bearer <token>`.

- `GET /wp-json/external-app-bridge/v1/validate-token`
  - Verifica el token enviado en el encabezado `Authorization: Bearer <token>` y devuelve los datos del usuario si es válido.

- `POST /wp-json/external-app-bridge/v1/password-reset-request`
  - Envía un correo de recuperación de contraseña al usuario.
  - Parámetros: `username` o `email`.

- `POST /wp-json/external-app-bridge/v1/password-reset`
  - Restablece la contraseña usando la clave recibida por correo.
  - Parámetros: `login`, `key`, `password`.

- `GET /wp-json/external-app-bridge/v1/profile`
  - Devuelve la información opcional del perfil del usuario autenticado.

- `POST /wp-json/external-app-bridge/v1/profile`
  - Actualiza los campos opcionales del perfil del usuario autenticado.
  - Parámetros (opcionales): `first_name`, `last_name`, `nickname`, `description`, `user_url`, `display_name`.

- `GET /wp-json/external-app-bridge/v1/order/<id>/addresses`
  - Devuelve la información de facturación y envío del pedido del usuario autenticado.

- `POST /wp-json/external-app-bridge/v1/order/<id>/addresses`
  - Actualiza la información de facturación y envío del pedido.
  - Parámetros (opcionales): `billing` (objeto), `shipping` (objeto).

Todos los endpoints, con excepción de **login**, **register**, **password-reset-request** y **password-reset**, requieren enviar el token en el encabezado `Authorization`.

## Instalación

1. Copiar la carpeta `external-app-bridge` en el directorio `wp-content/plugins/` de WordPress.
2. Activar el plugin desde el panel de administración.

## Notas de seguridad

El token generado es similar a un JWT y se firma con `AUTH_KEY` de WordPress. Asegúrate de tener una clave única y secreta definida en `wp-config.php`.

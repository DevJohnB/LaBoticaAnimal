# La Botica App Bridge

Este plugin de WordPress expone un conjunto de endpoints REST para que una aplicación externa pueda registrar y autenticar usuarios utilizando a WordPress como backend.

## Endpoints

- `POST /wp-json/labotica/v1/register`
  - Crea un nuevo usuario en WordPress.
  - Parámetros: `username`, `email`, `password`.

- `POST /wp-json/labotica/v1/login`
  - Autentica al usuario y devuelve un token.
  - Parámetros: `username`, `password`.

- `POST /wp-json/labotica/v1/logout`
  - Revoca el token actual enviado en el encabezado `Authorization: Bearer <token>`.

- `GET /wp-json/labotica/v1/validate-token`
  - Verifica el token enviado en el encabezado `Authorization: Bearer <token>` y devuelve los datos del usuario si es válido.

Todos los endpoints, con excepción de **login** y **register**, requieren enviar el token en el encabezado `Authorization`.

## Instalación

1. Copiar la carpeta `labotica-app-bridge` en el directorio `wp-content/plugins/` de WordPress.
2. Activar el plugin desde el panel de administración.

## Notas de seguridad

El token generado es similar a un JWT y se firma con `AUTH_KEY` de WordPress. Asegúrate de tener una clave única y secreta definida en `wp-config.php`.

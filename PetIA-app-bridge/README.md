# PetIA App Bridge

Plugin de WordPress que provee endpoints REST para autenticar usuarios por email y contraseña y un panel de ajustes donde se puede configurar la URL del chat de n8n.

## Endpoints

- `POST /wp-json/petia-app-bridge/v1/login`  
  Parámetros: `email`, `password`. Devuelve un token temporal.
- `GET /wp-json/petia-app-bridge/v1/profile`  
  Requiere encabezado `Authorization: Bearer <token>`. Devuelve `id`, `name`, `email` del usuario autenticado.

## Ajustes
En el administrador de WordPress se agrega una página en **Ajustes → PetIA App Bridge** para registrar la URL del chat n8n que utilizará la app.

## Instalación
1. Copiar la carpeta `PetIA-app-bridge` en `wp-content/plugins/`.
2. Activar el plugin desde el panel de WordPress.
3. Configurar la URL del chat n8n desde la página de ajustes.

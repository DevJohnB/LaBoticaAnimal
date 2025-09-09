# PetIA App Bridge

Plugin de WordPress que expone endpoints REST para ser consumidos por la aplicación PetIA.

## Características
- Autenticación mediante tokens JWT con expiración de 24 h y revocación.
- Endpoints de registro, inicio de sesión, perfil, productos y más bajo `/wp-json/petia-app-bridge/v1/`.
- Proxy genérico para WooCommerce.
- Manejo de CORS configurable mediante la constante `PETIA_ALLOWED_ORIGINS` (por defecto `*`).
- Panel de administración "PetIA Bridge" con pestañas para control de acceso y ejecución de pruebas Node.

## Instalación
1. Copia la carpeta `PetIA-app-bridge` en `wp-content/plugins/`.
2. Asegúrate de definir `AUTH_KEY` en `wp-config.php`.
3. (Opcional) Define `PETIA_ALLOWED_ORIGINS` para restringir los orígenes permitidos.
4. Activa el plugin desde el panel de WordPress.

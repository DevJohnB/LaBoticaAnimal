# PetIA Bridge Repository

Este repositorio contiene el plugin de WordPress **PetIA App Bridge** y la aplicación web estática **PetIA**.

## Plugin
1. Copia `PetIA-app-bridge/` en `wp-content/plugins/`.
2. Define `AUTH_KEY` en `wp-config.php` y, opcionalmente, `PETIA_ALLOWED_ORIGINS`.
3. Activa el plugin desde el panel de WordPress.

## Aplicación
1. Sirve la carpeta `PetIA/` en cualquier servidor estático.
2. Ajusta `API_BASE_URL` si la API está en otra URL (ver `PetIA/config.js`).

## Pruebas
Instala dependencias y ejecuta las pruebas:
```bash
npm install
npm test
```

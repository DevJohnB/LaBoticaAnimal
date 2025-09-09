# La Botica Animal

Repositorio que combina la aplicación web **PetIA** y el plugin de WordPress **PetIA App Bridge**.

## Carpetas
- `PetIA-app-bridge`: plugin que expone una API REST para registrar usuarios, autenticarlos y acceder a información de WooCommerce.
- `PetIA`: aplicación estática que consume la API del plugin para mostrar productos y gestionar cuentas.
- `__tests__`: pruebas unitarias para los módulos JavaScript compartidos.

## Requisitos
- Node.js 18 o superior para ejecutar las pruebas.
- Una instalación de WordPress (con WooCommerce) para usar el plugin.

## Configuración rápida
1. **Plugin**: copia `PetIA-app-bridge` en `wp-content/plugins/`, actívalo y define `AUTH_KEY`. Consulta el [README del plugin](PetIA-app-bridge/README.md).
2. **Aplicación**: sirve el contenido de `PetIA/` en un servidor estático y ajusta `apiBaseUrl` en `PetIA/config.js`. Más detalles en el [README de la app](PetIA/README.md).

## Pruebas
Instala dependencias y ejecuta los tests de JavaScript:

```bash
npm ci
npm test
```

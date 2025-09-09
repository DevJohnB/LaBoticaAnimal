# PetIA Frontend

Aplicación web estática que consume el plugin **PetIA App Bridge** para autenticación y catálogo.

## Configuración
- Edita `config.js` y ajusta la variable `apiBaseUrl` con la URL de la instalación de WordPress.

## Uso
La carpeta contiene varias páginas HTML:
- `index.html` – formulario de inicio de sesión.
- `store.html` – listado de productos.
- `user.html` – edición de perfil.
- `chat.html`, `recover.html`, `policy.html` y otras utilidades.

Puedes servir la carpeta con cualquier servidor estático, por ejemplo:

```bash
npx http-server PetIA
```

## Desarrollo
El código JavaScript está en `js/` y se apoya en `api.js` y `token.js` para las llamadas al backend y la gestión del token, almacenado en una cookie.

## Pruebas
Desde la raíz del repositorio se pueden ejecutar las pruebas unitarias de los módulos reutilizados:

```bash
npm test
```

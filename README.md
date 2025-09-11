# PetIA Bridge Repository

Este repositorio contiene el plugin de WordPress **PetIA App Bridge** y la aplicación web estática **PetIA**.

## Plugin
1. Copia `PetIA-app-bridge/` en `wp-content/plugins/`.
2. Define `AUTH_KEY` en `wp-config.php` y, opcionalmente, `PETIA_ALLOWED_ORIGINS`.
3. Activa el plugin desde el panel de WordPress.

Las dependencias PHP vienen incluidas, por lo que no es necesario ejecutar `composer install`.

## Aplicación
1. Sirve la carpeta `PetIA/` en cualquier servidor estático.
2. Ajusta `API_BASE_URL` si la API está en otra URL (ver `PetIA/config.js`).

## Precios
Los endpoints que devuelven productos proporcionan ahora dos campos de precio:

- `price`: valor numérico del producto.
- `formatted_price`: versión formateada (por ejemplo, con moneda) que puede usarse como respaldo opcional.

El frontend utiliza `price` como número y, si está disponible, muestra `formatted_price`.

## Interacciones con botones
Al realizar operaciones que invoquen `apiRequest`, deshabilita el botón involucrado y agrega la clase `loading` hasta que la petición finalice. Esto evita múltiples envíos y permite mostrar un spinner interno.

```js
button.disabled = true;
button.classList.add('loading');
try {
  await apiRequest('/endpoint');
} finally {
  button.disabled = false;
  button.classList.remove('loading');
}
```

## Pruebas
Instala dependencias y ejecuta las pruebas:
```bash
npm install
npm test
```

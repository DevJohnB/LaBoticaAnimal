# PetIA Static App

Aplicación web estática que consume los endpoints del plugin PetIA App Bridge.

## Configuración
- El archivo `config.js` permite configurar la URL base de la API mediante `process.env.API_BASE_URL`, `window.API_BASE_URL` o el valor por defecto `https://laboticaanimal.com`.

## Uso
1. Sirve la carpeta `PetIA/` desde cualquier servidor estático.
2. Ajusta `API_BASE_URL` si tu WordPress corre en otra URL.
3. Abre `index.html` para iniciar sesión.

## Categorías de productos

El endpoint `/wp-json/petia-app-bridge/v1/product-categories` ahora devuelve un arreglo de objetos con la forma:

```json
[
  { "id": 1, "name": "Perros", "parent": 0 },
  { "id": 2, "name": "Pienso", "parent": 1 }
]
```

Con esta estructura `store.js` puede filtrar las categorías principales mediante `parent === 0` y obtener subcategorías dinámicamente.

## Pruebas
Ejecuta en la raíz del repositorio:
```bash
npm test
```

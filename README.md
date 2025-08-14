# PetIA

Este repositorio contiene dos componentes:

1. **Plugin de WordPress `PetIA-app-bridge`**: expone endpoints REST de login y perfil y un panel de ajustes para configurar la URL del chat n8n.
2. **Cliente web `petia-app`**: aplicación HTML/JS que realiza el inicio de sesión, muestra el perfil del usuario y embebe el chat de n8n. Incluye soporte básico de voz a texto mediante la Web Speech API.

## Configuración del cliente
Edite `petia-app/config.js` con la URL de su sitio WordPress y del chat n8n:
```javascript
window.CONFIG = {
  apiBase: "https://your-wordpress-site.com",
  n8nChatUrl: "https://your-n8n-instance/chat"
};
```

## Instalación del plugin
1. Copie `PetIA-app-bridge` en `wp-content/plugins/`.
2. Actívelo desde el panel de WordPress.
3. En **Ajustes → PetIA App Bridge** configure la URL del chat n8n.

## Desarrollo y empaquetado
La aplicación está preparada para empaquetarse en Android/iOS mediante [Capacitor](https://capacitorjs.com/).
```bash
npm install
npx cap doctor
```
Para añadir plataformas:
```bash
npx cap add android
npx cap add ios
```

## Licencia
MIT

# PetIA Web App

This repository contains the frontend code for the **PetIA** application. The app is a lightweight HTML/JavaScript client that authenticates against a WordPress site using the companion `PetIA-app-bridge` plugin and embeds an n8n chat interface.

## Features
- Email/password login against WordPress
- Embeds n8n chat in an iframe
- View personal profile information with logout option
- Optional voice-to-text support via the Web Speech API
- Prepared for packaging as a mobile application using [Capacitor](https://capacitorjs.com/)

## Configuration
Update `petia-app/config.js` with your own endpoints:
```
window.CONFIG = {
  apiBase: "https://your-wordpress-site.com",
  n8nChatUrl: "https://your-n8n-instance/chat"
};
```
The login endpoint `/wp-json/petia-app-bridge/v1/login` is provided by the WordPress plugin **PetIA-app-bridge** (not included in this repository).

## Development
Install dependencies and run the Capacitor CLI:
```bash
npm install
npx cap doctor
```
To add mobile platforms:
```bash
npx cap add android
npx cap add ios
```

## Building
Since the app is plain HTML/JS, no build step is required. When packaging with Capacitor, the `petia-app` directory is used as the web assets folder.

## License
MIT

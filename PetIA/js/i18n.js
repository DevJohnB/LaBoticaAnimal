import { apiFetch } from './apiFetch.js';

(async function() {
  const defaultLang = 'es';
  const lang = (navigator.language || defaultLang).split('-')[0];
  let res = await apiFetch(`locales/${lang}.json`, { skipAuthError: true });
  if (!res.ok) {
    res = await apiFetch(`locales/${defaultLang}.json`, { skipAuthError: true });
  }
  const translations = await res.json();
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (translations[key]) {
      el.textContent = translations[key];
    }
  });
})();

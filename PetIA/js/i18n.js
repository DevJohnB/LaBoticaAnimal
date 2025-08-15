(async function() {
  const defaultLang = 'es';
  const lang = (navigator.language || defaultLang).split('-')[0];
  const translations = await fetch(`locales/${lang}.json`).then(r => r.ok ? r.json() : fetch(`locales/${defaultLang}.json`).then(r=>r.json()));
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (translations[key]) {
      el.textContent = translations[key];
    }
  });
})();

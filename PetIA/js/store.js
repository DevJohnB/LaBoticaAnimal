import config from '../config.js';
import { logout, validateToken } from './auth.js';
import { fetchWithAuth } from './token.js';

async function init() {
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
  const valid = await validateToken();
  if (!valid) return;
  loadCategories();
  loadBrands();
  loadProducts();
}

async function loadProducts() {
  const url = config.apiBaseUrl + config.endpoints.products;
  try {
    const res = await fetchWithAuth(url);
    if (!res.ok) throw new Error('Failed to load products');
    const productsData = await res.json();
    renderProducts(productsData);
  } catch (err) {
    console.error(err);
  }
}

function renderProducts(products) {
  const container = document.getElementById('productList');
  container.innerHTML = '';
  products.forEach(p => {
    const card = document.createElement('div');
    card.className = 'product-card';
    const imgSrc = p.image || (p.images && p.images[0] ? p.images[0].src : '');
    card.innerHTML = `
      ${imgSrc ? `<img src="${imgSrc}" alt="${p.name}">` : ''}
      <h3>${p.name}</h3>
      <p>${p.price || p.price_html || ''}</p>
    `;
    container.appendChild(card);
  });
}

async function loadCategories() {
  const url = config.apiBaseUrl + config.endpoints.productCategories;
  try {
    const res = await fetchWithAuth(url);
    if (!res.ok) throw new Error('Failed to load categories');
    const data = await res.json();
    renderCategories(data);
  } catch (err) {
    console.error(err);
  }
}

function renderCategories(categories) {
  const container = document.getElementById('categoryList');
  if (!container) return;
  container.innerHTML = '';
  categories.forEach(c => {
    const card = document.createElement('div');
    card.className = 'product-card';
    const imgSrc = c.image || (c.image && c.image.src ? c.image.src : '');
    card.innerHTML = `
      ${imgSrc ? `<img src="${imgSrc}" alt="${c.name}">` : ''}
      <h3>${c.name}</h3>
    `;
    container.appendChild(card);
  });
}

async function loadBrands() {
  const url = config.apiBaseUrl + config.endpoints.brands;
  try {
    const res = await fetchWithAuth(url);
    if (!res.ok) throw new Error('Failed to load brands');
    const data = await res.json();
    renderBrands(data);
  } catch (err) {
    console.error(err);
  }
}

function renderBrands(brands) {
  const container = document.getElementById('brandList');
  if (!container) return;
  container.innerHTML = '';
  brands.forEach(b => {
    const card = document.createElement('div');
    card.className = 'product-card';
    const imgSrc = b.image || (b.image && b.image.src ? b.image.src : '');
    card.innerHTML = `
      ${imgSrc ? `<img src="${imgSrc}" alt="${b.name}">` : ''}
      <h3>${b.name}</h3>
    `;
    container.appendChild(card);
  });
}

document.addEventListener('DOMContentLoaded', init);

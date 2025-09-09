import config from '../config.js';
import { logout, validateToken } from './auth.js';
import { apiRequest } from './api.js';
import { handleError } from './error.js';

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
  try {
    const productsData = await apiRequest(config.endpoints.products);
    renderProducts(productsData);
  } catch (err) {
    handleError(err);
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
  try {
    const data = await apiRequest(config.endpoints.productCategories);
    renderCategories(data);
  } catch (err) {
    handleError(err);
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
  try {
    const data = await apiRequest(config.endpoints.brands);
    renderBrands(data);
  } catch (err) {
    handleError(err);
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

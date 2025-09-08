import config from '../config.js';
import { logout } from './auth.js';

document.getElementById('logoutBtn').addEventListener('click', logout);

async function loadProducts() {
  const { products, consumerKey, consumerSecret } = config.woocommerce;
  const url = `${config.apiBaseUrl}${products}?consumer_key=${consumerKey}&consumer_secret=${consumerSecret}`;
  try {
    const res = await fetch(url);
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
    const imgSrc = p.images && p.images[0] ? p.images[0].src : '';
    card.innerHTML = `
      ${imgSrc ? `<img src="${imgSrc}" alt="${p.name}">` : ''}
      <h3>${p.name}</h3>
      <p>${p.price_html || ''}</p>
    `;
    container.appendChild(card);
  });
}

loadProducts();

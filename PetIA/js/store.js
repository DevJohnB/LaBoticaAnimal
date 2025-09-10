import config from '../config.js';
import { apiRequest } from './api.js';

const productsCache = new Map();
let allCategories = [];

async function loadCategoryProducts(categoryId) {
  const panel = document.getElementById(`panel-${categoryId}`);
  if (!panel) return;
  if (productsCache.has(categoryId)) {
    renderProducts(productsCache.get(categoryId), panel);
    return;
  }
  panel.innerHTML = '<p>Cargando...</p>';
  const products = await apiRequest(`${config.endpoints.products}?category=${categoryId}`);
  productsCache.set(categoryId, products);
  renderProducts(products, panel);
}

function renderProducts(products, panel) {
  panel.innerHTML = '';
  const list = document.createElement('ul');
  panel.appendChild(list);
  products.forEach(p => {
    const li = document.createElement('li');
    li.className = 'product';
    li.innerHTML = `
      <img src="${p.image}" alt="${p.name}" />
      <div class="name">${p.name}</div>
      <div class="price">${p.price}</div>
    `;
    list.appendChild(li);
  });
}

function renderCategories(categories) {
  allCategories = categories;
  const tabs = document.getElementById('category-tabs');
  const content = document.getElementById('category-content');
  categories
    .filter(c => !c.parent)
    .forEach(cat => {
      const tab = document.createElement('div');
      tab.className = 'tab';
      tab.textContent = cat.name;
      tab.dataset.id = cat.id;
      tabs.appendChild(tab);

      const panel = document.createElement('div');
      panel.className = 'tab-panel';
      panel.id = `panel-${cat.id}`;
      content.appendChild(panel);

      tab.addEventListener('click', () => {
        tabs.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        content.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        panel.classList.add('active');
        renderSubcategories(cat.id);
      });
    });
  const first = tabs.querySelector('.tab');
  if (first) first.click();
}

function renderSubcategories(parentId) {
  const panel = document.getElementById(`panel-${parentId}`);
  if (!panel || panel.dataset.subRendered) return;
  const subs = allCategories.filter(c => c.parent === parentId);
  if (subs.length === 0) {
    if (!productsCache.has(parentId)) {
      loadCategoryProducts(parentId);
    }
    panel.dataset.subRendered = 'true';
    return;
  }
  const tabs = document.createElement('div');
  tabs.className = 'tabs';
  const content = document.createElement('div');
  panel.appendChild(tabs);
  panel.appendChild(content);
  subs.forEach(sub => {
    const tab = document.createElement('div');
    tab.className = 'tab';
    tab.textContent = sub.name;
    tab.dataset.id = sub.id;
    tabs.appendChild(tab);

    const subPanel = document.createElement('div');
    subPanel.className = 'tab-panel';
    subPanel.id = `panel-${sub.id}`;
    content.appendChild(subPanel);

    tab.addEventListener('click', () => {
      tabs.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      content.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      subPanel.classList.add('active');
      if (!productsCache.has(sub.id)) {
        loadCategoryProducts(sub.id);
      }
    });
  });
  const first = tabs.querySelector('.tab');
  if (first) first.click();
  panel.dataset.subRendered = 'true';
}

(async function init() {
  const categories = await apiRequest(config.endpoints.productCategories);
  renderCategories(categories);
})();

export { renderCategories, loadCategoryProducts, renderSubcategories };


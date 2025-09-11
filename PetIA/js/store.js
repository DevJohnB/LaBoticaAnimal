import config from '../config.js';
import { apiRequest } from './api.js';
import { addItem } from './cart.js';
import { ensureAuth } from './token.js';
import { showToast } from './notifications.js';

ensureAuth();

const loadedCategories = new Map();
let allCategories = [];
const navigationStack = [];

async function loadCategoryProducts(categoryId) {
  const panel = document.getElementById('category-content');
  if (loadedCategories.has(categoryId)) {
    renderProducts(loadedCategories.get(categoryId), panel);
    return;
  }
  panel.innerHTML = '<p>Cargando...</p>';
  const products = await apiRequest(
    `${config.endpoints.products}?category=${categoryId}`
  );
  loadedCategories.set(categoryId, products);
  renderProducts(products, panel);
}

function renderProducts(products, panel) {
  panel.innerHTML = '';
  const list = document.createElement('ul');
  list.className = 'product-list';
  panel.appendChild(list);
  products.forEach(p => {
    const li = document.createElement('li');
    li.className = 'product';
    let attrsHtml = '';
    if (p.type === 'variable' && p.attributes) {
      Object.entries(p.attributes).forEach(([attr, options]) => {
        const opts = options
          .map(o => `<option value="${o}">${o}</option>`) // simple label
          .join('');
        attrsHtml += `<label>${attr}<select data-attr="${attr}">${opts}</select></label>`;
      });
    }
    li.innerHTML = `
      <img src="${p.image}" alt="${p.name}" />
      <div class="name">${p.name}</div>
      <div class="price">${p.price}</div>
      ${attrsHtml}
      <button class="add-cart">Añadir</button>
    `;
    const btn = li.querySelector('.add-cart');
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      btn.classList.add('loading');
      try {
        let variationSeleccionada;
        if (p.type === 'variable' && p.attributes) {
          variationSeleccionada = {};
          li.querySelectorAll('select[data-attr]').forEach(sel => {
            variationSeleccionada[sel.dataset.attr] = sel.value;
          });
          const variationMatch = p.variations?.find(v => {
            const attrs = v.attributes || {};
            return Object.entries(variationSeleccionada).every(
              ([attr, val]) => attrs[attr] === val
            );
          });
          if (!variationMatch) {
            showToast('Variación no encontrada', 'error');
            return;
          }
          await addItem(variationMatch.id, 1, variationSeleccionada);
        } else {
          await addItem(p.id, 1);
        }
        showToast('Producto agregado', 'success');
      } finally {
        btn.disabled = false;
        btn.classList.remove('loading');
      }
    });
    list.appendChild(li);
  });
}

function hasProducts(cat) {
  if (typeof cat.count === 'number') return cat.count > 0;
  if (typeof cat.num_products === 'number') return cat.num_products > 0;
  if (typeof cat.hide_empty !== 'undefined') return !cat.hide_empty;
  return true;
}

function renderCategoryLevel(parentId) {
  const tabs = document.getElementById('category-tabs');
  const content = document.getElementById('category-content');
  tabs.innerHTML = '';
  content.innerHTML = '';

  if (navigationStack.length > 0) {
    const back = document.createElement('button');
    back.textContent = 'Atrás';
    back.addEventListener('click', () => {
      const prev = navigationStack.pop();
      renderCategoryLevel(prev ?? null);
    });
    tabs.appendChild(back);
  }

  const cats = allCategories.filter(c =>
    parentId === null ? !c.parent : c.parent === parentId
  ).filter(hasProducts);

  if (cats.length === 0) {
    loadCategoryProducts(parentId);
    return;
  }

  cats.forEach(cat => {
    const tab = document.createElement('div');
    tab.className = 'tab';
    tab.textContent = cat.name;
    tabs.appendChild(tab);
    tab.addEventListener('click', () => {
      navigationStack.push(parentId);
      renderCategoryLevel(cat.id);
    });
  });
}

(async function init() {
  const categories = await apiRequest(config.endpoints.productCategories);
  allCategories = categories.filter(hasProducts);
  renderCategoryLevel(null);
})();

export { renderCategoryLevel, loadCategoryProducts };


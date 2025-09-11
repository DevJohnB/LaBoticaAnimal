import config from '../config.js';
import { apiRequest } from './api.js';
import { addItem } from './cart.js';
import { ensureAuth } from './token.js';

ensureAuth();

const loadedCategories = new Map();
let allCategories = [];

async function loadCategoryProducts(categoryId) {
  const panel = document.getElementById(`panel-${categoryId}`);
  if (!panel) return;
  if (loadedCategories.has(categoryId)) {
    renderProducts(loadedCategories.get(categoryId), panel);
    return;
  }
  panel.innerHTML = '<p>Cargando...</p>';
  const products = await apiRequest(`${config.endpoints.products}?category=${categoryId}`);
  loadedCategories.set(categoryId, products);
  renderProducts(products, panel);
}

function renderProducts(products, panel) {
  panel.innerHTML = '';
  const list = document.createElement('ul');
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
            alert('Variación no encontrada');
            return;
          }
          await addItem(variationMatch.id, 1, variationSeleccionada);
        } else {
          await addItem(p.id, 1);
        }
        alert('Producto agregado');
      } finally {
        btn.disabled = false;
        btn.classList.remove('loading');
      }
    });
    list.appendChild(li);
  });
}

function setActiveTab(tab, panel, tabsContainer, contentContainer) {
  tabsContainer.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  contentContainer
    .querySelectorAll('.tab-panel')
    .forEach(p => p.classList.remove('active'));
  tab.classList.add('active');
  panel.classList.add('active');
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
        setActiveTab(tab, panel, tabs, content);
        // Remove active from any subcategory tab
        document
          .querySelectorAll('#category-content .tab')
          .forEach(t => t.classList.remove('active'));
        if (typeof localStorage !== 'undefined') {
          localStorage.setItem('activeCategory', cat.id);
        }
        renderSubcategories(cat.id);
      });
    });

  const saved =
    typeof localStorage !== 'undefined' && localStorage.getItem('activeCategory');
  let toActivate = null;
  if (saved) {
    const cat = allCategories.find(c => String(c.id) === saved);
    if (cat) {
      const rootId = cat.parent || cat.id;
      toActivate = tabs.querySelector(`.tab[data-id="${rootId}"]`);
    }
  }
  (toActivate || tabs.querySelector('.tab'))?.click();
}

function renderSubcategories(parentId) {
  const panel = document.getElementById(`panel-${parentId}`);
  if (!panel || panel.dataset.subRendered) return;
  const subs = allCategories.filter(c => c.parent === parentId);
  if (subs.length === 0) {
    loadCategoryProducts(parentId);
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
      setActiveTab(tab, subPanel, tabs, content);
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('activeCategory', sub.id);
      }
      loadCategoryProducts(sub.id);
    });
  });
  const saved =
    typeof localStorage !== 'undefined' && localStorage.getItem('activeCategory');
  const toActivate = saved
    ? tabs.querySelector(`.tab[data-id="${saved}"]`)
    : null;
  (toActivate || tabs.querySelector('.tab'))?.click();
  panel.dataset.subRendered = 'true';
}

(async function init() {
  const categories = await apiRequest(config.endpoints.productCategories);
  renderCategories(categories);
})();

export { renderCategories, loadCategoryProducts, renderSubcategories };


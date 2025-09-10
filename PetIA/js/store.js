import config from '../config.js';
import { apiRequest } from './api.js';

async function load() {
  const categories = await apiRequest(config.endpoints.productCategories);
  const container = document.getElementById('categories');
  for (const [id, name] of Object.entries(categories)) {
    const section = document.createElement('section');
    const heading = document.createElement('h2');
    heading.textContent = name;
    section.appendChild(heading);
    const list = document.createElement('ul');
    section.appendChild(list);
    container.appendChild(section);

    const products = await apiRequest(`${config.endpoints.products}?category=${id}`);
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
}

load();

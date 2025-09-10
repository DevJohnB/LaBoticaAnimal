import { getCart, updateItem, removeItem } from './cart.js';

async function renderCart() {
  try {
    const cart = await getCart();
    const tbody = document.getElementById('cart-items');
    const empty = document.getElementById('cart-empty');
    const table = document.getElementById('cart-table');
    const actions = document.querySelector('.cart-actions');
    tbody.innerHTML = '';
    if (!cart.items || cart.items.length === 0) {
      table.style.display = 'none';
      actions.style.display = 'none';
      empty.style.display = 'block';
      return;
    }
    table.style.display = '';
    actions.style.display = 'flex';
    empty.style.display = 'none';
    cart.items.forEach(item => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.name}</td>
        <td>${item.prices?.price ?? ''}</td>
        <td><input type="number" min="1" value="${item.quantity}" /></td>
        <td>${item.totals?.line_total ?? ''}</td>
        <td><button class="remove">Eliminar</button></td>
      `;
      const qtyInput = tr.querySelector('input');
      qtyInput.addEventListener('change', async () => {
        const qty = parseInt(qtyInput.value, 10);
        await updateItem(item.key, qty);
        renderCart();
      });
      tr.querySelector('.remove').addEventListener('click', async () => {
        await removeItem(item.key);
        renderCart();
      });
      tbody.appendChild(tr);
    });
  } catch (error) {
    console.error('Error al renderizar el carrito:', error);
  }
}

async function clearCart() {
  try {
    const cart = await getCart();
    await Promise.all((cart.items || []).map(i => removeItem(i.key)));
    renderCart();
  } catch (error) {
    console.error('Error al vaciar el carrito:', error);
  }
}

function checkout() {
  window.location.href = '/checkout';
}

document.addEventListener('DOMContentLoaded', () => {
  document
    .getElementById('clear-cart')
    .addEventListener('click', clearCart);
  document
    .getElementById('checkout-button')
    .addEventListener('click', checkout);
  renderCart();
});

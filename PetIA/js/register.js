import config from '../config.js';

function getFormElement() {
  if (typeof document === 'undefined') {
    return null;
  }
  return document.getElementById('registerForm');
}

function createMessageContainer(form) {
  let container = document.getElementById('registerMessage');
  if (!container) {
    container = document.createElement('div');
    container.id = 'registerMessage';
    container.setAttribute('role', 'alert');
    container.style.marginTop = '1rem';
    container.style.textAlign = 'center';
    form.insertAdjacentElement('afterend', container);
  }
  return container;
}

function showMessage(element, message, type = 'success') {
  if (!element) return;
  element.textContent = message;
  element.style.color = type === 'success' ? '#0a7a0a' : '#c0392b';
}

function extractMessages(data) {
  if (!data) return [];
  if (typeof data === 'string') return [data];

  const messages = [];

  const addMessage = (value) => {
    if (!value) return;
    if (typeof value === 'string') {
      messages.push(value);
    } else if (typeof value === 'number' || typeof value === 'boolean') {
      messages.push(String(value));
    } else if (Array.isArray(value)) {
      value.forEach(addMessage);
    } else if (typeof value === 'object' && value !== null) {
      if (value.message && typeof value.message === 'string') {
        messages.push(value.message);
      }
      if (value.error && typeof value.error === 'string') {
        messages.push(value.error);
      }
      if (value.errors) {
        addMessage(value.errors);
      }
      if (value.data && value.data !== value) {
        addMessage(value.data);
      }
    }
  };

  addMessage(data);

  return messages.filter((msg) => typeof msg === 'string' && msg.trim().length > 0);
}

async function readResponseBody(response) {
  if (!response) return null;
  const { status } = response;
  if (status === 204 || status === 205) {
    return null;
  }
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    try {
      return await response.json();
    } catch (error) {
      return null;
    }
  }
  try {
    const text = await response.text();
    return text ? { message: text } : null;
  } catch (error) {
    return null;
  }
}

function getTrimmedValue(value) {
  return typeof value === 'string' ? value.trim() : '';
}

document.addEventListener('DOMContentLoaded', () => {
  const form = getFormElement();
  if (!form) return;

  const messageContainer = createMessageContainer(form);
  const submitButton = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    showMessage(messageContainer, '');

    const formData = new FormData(form);
    const username = getTrimmedValue(formData.get('username'));
    const email = getTrimmedValue(formData.get('email'));
    const password = formData.get('password') || '';
    const confirmPassword = formData.get('confirm_password') || '';
    const firstName = getTrimmedValue(formData.get('first_name'));
    const lastName = getTrimmedValue(formData.get('last_name'));

    if (password !== confirmPassword) {
      showMessage(messageContainer, 'Las contraseñas no coinciden.', 'error');
      return;
    }

    const payload = { username, email, password };
    if (firstName) {
      payload.first_name = firstName;
    }
    if (lastName) {
      payload.last_name = lastName;
    }

    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const res = await fetch(config.apiBaseUrl + config.endpoints.register, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await readResponseBody(res);

      if (!res.ok) {
        const errorMessages = extractMessages(data);
        const errorMessage =
          errorMessages.length > 0
            ? errorMessages.join(' ')
            : 'No se pudo completar el registro. Inténtalo de nuevo más tarde.';
        showMessage(messageContainer, errorMessage, 'error');
        return;
      }

      const messages = extractMessages(data);
      const successMessage =
        messages.length > 0 ? messages[0] : 'Registro exitoso. Revisa tu correo para continuar.';
      showMessage(messageContainer, successMessage, 'success');
      form.reset();
    } catch (error) {
      showMessage(
        messageContainer,
        'Ocurrió un error al conectar con el servidor. Inténtalo nuevamente más tarde.',
        'error',
      );
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
});

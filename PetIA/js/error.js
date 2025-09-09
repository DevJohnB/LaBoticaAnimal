export function handleError(err, userMessage = 'Ocurrió un error') {
  console.error(err);
  if (typeof window !== 'undefined' && typeof window.alert === 'function') {
    alert(userMessage);
  }
}

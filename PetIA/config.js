const apiBaseUrl =
  (typeof process !== 'undefined' && process.env.API_BASE_URL) ||
  (typeof globalThis !== 'undefined' && globalThis.API_BASE_URL) ||
  'https://laboticaanimal.com';

export default {
  apiBaseUrl,
  endpoints: {
    login: '/wp-json/petia-app-bridge/v1/login',
    logout: '/wp-json/petia-app-bridge/v1/logout',
    validateToken: '/wp-json/petia-app-bridge/v1/validate-token',
    passwordResetRequest: '/wp-json/petia-app-bridge/v1/password-reset-request',
    passwordReset: '/wp-json/petia-app-bridge/v1/password-reset',
    profile: '/wp-json/petia-app-bridge/v1/profile',
    productCategories: '/wp-json/petia-app-bridge/v1/product-categories',
    products: '/wp-json/petia-app-bridge/v1/products',
    brands: '/wp-json/petia-app-bridge/v1/brands',
    cart: '/wp-json/petia-app-bridge/v1/wc-store/cart',
    cartAddItem: '/wp-json/petia-app-bridge/v1/wc-store/cart/add-item',
    cartUpdateItem: '/wp-json/petia-app-bridge/v1/wc-store/cart/update-item',
    cartRemoveItem: '/wp-json/petia-app-bridge/v1/wc-store/cart/remove-item',
    paymentMethods: '/wp-json/petia-app-bridge/v1/payment-methods',
    checkout: '/wp-json/petia-app-bridge/v1/checkout',
  },
};

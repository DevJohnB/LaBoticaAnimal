// Application configuration
export default {
  apiBaseUrl: "https://laboticaanimal.com",
  endpoints: {
    login: "/wp-json/petia-app-bridge/v1/login",
    logout: "/wp-json/petia-app-bridge/v1/logout",
    validateToken: "/wp-json/petia-app-bridge/v1/validate-token",
    passwordResetRequest: "/wp-json/petia-app-bridge/v1/password-reset-request",
    passwordReset: "/wp-json/petia-app-bridge/v1/password-reset",
    profile: "/wp-json/petia-app-bridge/v1/profile",
    productCategories: "/wp-json/petia-app-bridge/v1/product-categories",
    products: "/wp-json/petia-app-bridge/v1/products",
    brands: "/wp-json/petia-app-bridge/v1/brands"
  }
};

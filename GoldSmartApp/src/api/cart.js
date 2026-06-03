import client from './client';

export const getCart = () =>
  client.get('/cart');

export const getCartSummary = () =>
  client.get('/cart/summary');

export const getCheckoutStatus = () =>
  client.get('/cart/checkout-status');

export const addToCart = (product_id, quantity) =>
  client.post('/cart', { product_id, quantity });

export const updateCartItem = (id, quantity) =>
  client.put(`/cart/${id}`, { quantity });

export const removeCartItem = (id) =>
  client.delete(`/cart/${id}`);

export const clearCart = () =>
  client.delete('/cart');

export const checkout = (data) =>
  client.post('/cart/checkout', data);

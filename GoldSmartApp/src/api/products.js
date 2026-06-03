import client from './client';

export const getProducts = (paramsOrPage, category, search) => {
  const params = typeof paramsOrPage === 'object' ? paramsOrPage : { page: paramsOrPage, category, search };
  return client.get('/products', { params });
};

export const getFeaturedProducts = () =>
  client.get('/products/featured');

export const getProduct = (id) =>
  client.get(`/products/${id}`);

export const getCategories = () =>
  client.get('/categories');

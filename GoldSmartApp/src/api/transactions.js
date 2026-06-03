import client from './client';

export const getTransactions = (page, status) =>
  client.get('/transactions', { params: { page, status } });

export const createTransaction = (product_id, quantity) =>
  client.post('/transactions', { product_id, quantity });

export const getTransaction = (id) =>
  client.get(`/transactions/${id}`);

export const uploadPaymentProof = (id, base64Image) =>
  client.post(`/transactions/${id}/payment-proof`, { payment_proof: base64Image });

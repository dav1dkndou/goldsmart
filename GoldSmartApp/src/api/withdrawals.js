import client from './client';

export const getWithdrawals = (page) =>
  client.get('/withdrawals', { params: { page } });

export const createWithdrawal = (gc_amount, bank_name, account_number, account_holder) =>
  client.post('/withdrawals', { gc_amount, bank_name, account_number, account_holder });

export const getWithdrawal = (id) =>
  client.get(`/withdrawals/${id}`);

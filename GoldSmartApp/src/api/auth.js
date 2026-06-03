import client from './client';

export const login = (email, password) =>
  client.post('/auth/login', { email, password });

export const register = (name, email, phone, password, password_confirmation, referral_code) => {
  const data = { name, email, phone, password, password_confirmation };
  if (referral_code) data.referral_code = referral_code;
  return client.post('/auth/register', data);
};

export const getMe = () =>
  client.get('/auth/me');

export const logout = () =>
  client.post('/auth/logout');

export const refreshToken = () =>
  client.post('/auth/refresh');

export const updateReferral = (referral_code) =>
  client.post('/auth/update-referral', { referral_code });

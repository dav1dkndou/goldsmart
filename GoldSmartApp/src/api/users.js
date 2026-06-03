import client from './client';

export const getProfile = () =>
  client.get('/users/profile');

export const updateProfile = (name, phone) =>
  client.put('/users/profile', { name, phone });

export const uploadAvatar = (base64Image) =>
  client.post('/users/avatar', { avatar: base64Image });

export const changePassword = (current_password, new_password, new_password_confirmation) =>
  client.post('/users/change-password', { current_password, new_password, new_password_confirmation });

export const getBalance = () =>
  client.get('/users/balance');

export const getReferrals = () =>
  client.get('/users/referrals');

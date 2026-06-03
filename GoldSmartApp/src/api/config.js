import client from './client';

export const getConfig = () =>
  client.get('/config');

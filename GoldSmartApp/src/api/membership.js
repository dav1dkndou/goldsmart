import client from './client';

export const requestMembership = (type, reason = 'Ingin menjadi member') =>
  client.post('/membership/request', { type, reason });

export const getMembershipStatus = () =>
  client.get('/membership/status');

export const cancelMembershipRequest = () =>
  client.post('/membership/cancel');

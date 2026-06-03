import client from './client';

export const getMiningStats = () =>
  client.get('/mining/stats');

export const getMiningPlans = () =>
  client.get('/mining/plans');

export const getDailyBonus = () =>
  client.get('/mining/daily-bonus');

export const claimDailyBonus = () =>
  client.post('/mining/daily-bonus');

export const activateMiningPlan = (plan_id) =>
  client.post('/mining/activate', { plan_id });

export const claimMining = (plan_id) =>
  client.post('/mining/claim', { plan_id });

export const getMiningHistory = (page) =>
  client.get('/mining/history', { params: { page } });

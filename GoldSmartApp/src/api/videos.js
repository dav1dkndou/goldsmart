import client from './client';

export const getVideos = (page, category, search) =>
  client.get('/videos', { params: { page, category, search } });

export const getVideoCategories = () =>
  client.get('/videos/categories');

export const uploadVideo = (title, url, thumbnail, category_id) =>
  client.post('/videos', { title, url, thumbnail, category_id });

export const getVideo = (id) =>
  client.get(`/videos/${id}`);

export const recordView = (id) =>
  client.post(`/videos/${id}/view`);

export const toggleLike = (id) =>
  client.post(`/videos/${id}/like`);

export const getComments = (id, page) =>
  client.get(`/videos/${id}/comments`, { params: { page } });

export const addComment = (id, content) =>
  client.post(`/videos/${id}/comments`, { content });

export const deleteComment = (commentId) =>
  client.delete(`/videos/comments/${commentId}`);

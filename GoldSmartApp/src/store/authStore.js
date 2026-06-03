import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as authAPI from '../api/auth';

const useAuthStore = create((set, get) => ({
  user: null,
  token: null,
  isLoading: true,
  isLoggedIn: false,

  setUser: (user) => set({ user }),
  setToken: (token) => set({ token }),

  clearAuth: () => set({ user: null, token: null, isLoggedIn: false }),

  loadToken: async () => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      if (token) {
        set({ token });
        const res = await authAPI.getMe();
        set({ user: res.data.data || res.data, isLoggedIn: true, isLoading: false });
      } else {
        set({ isLoading: false });
      }
    } catch (error) {
      await AsyncStorage.removeItem('auth_token');
      set({ user: null, token: null, isLoggedIn: false, isLoading: false });
    }
  },

  login: async (email, password) => {
    const res = await authAPI.login(email, password);
    const { token, user } = res.data.data || res.data;
    await AsyncStorage.setItem('auth_token', token);
    set({ user, token, isLoggedIn: true });
    return res.data;
  },

  register: async (name, email, phone, password, password_confirmation, referral_code) => {
    const res = await authAPI.register(name, email, phone, password, password_confirmation, referral_code);
    const { token, user } = res.data.data || res.data;
    if (token) {
      await AsyncStorage.setItem('auth_token', token);
      set({ user, token, isLoggedIn: true });
    }
    return res.data;
  },

  logout: async () => {
    try {
      await authAPI.logout();
    } catch (e) {}
    await AsyncStorage.removeItem('auth_token');
    set({ user: null, token: null, isLoggedIn: false });
  },

  fetchUser: async () => {
    try {
      const res = await authAPI.getMe();
      set({ user: res.data.data || res.data });
    } catch (error) {
      console.warn('Failed to fetch user:', error);
    }
  },
}));

export { useAuthStore };
export default useAuthStore;

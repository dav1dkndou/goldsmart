import { create } from 'zustand';
import * as cartAPI from '../api/cart';

const useCartStore = create((set, get) => ({
  items: [],
  summary: null,
  isLoading: false,

  fetchCart: async () => {
    set({ isLoading: true });
    try {
      const res = await cartAPI.getCart();
      const data = res.data?.data || res.data;
      set({ 
        items: data?.items || data || [], 
        summary: data?.summary || null,
        isLoading: false 
      });
    } catch (error) {
      set({ isLoading: false });
      throw error;
    }
  },

  fetchSummary: async () => {
    try {
      const res = await cartAPI.getCartSummary();
      set({ summary: res.data.data || res.data });
    } catch (error) {
      throw error;
    }
  },

  addItem: async (product_id, quantity) => {
    const res = await cartAPI.addToCart(product_id, quantity);
    await get().fetchCart();
    return res.data;
  },

  updateItem: async (id, quantity) => {
    const res = await cartAPI.updateCartItem(id, quantity);
    await get().fetchCart();
    return res.data;
  },

  removeItem: async (id) => {
    const res = await cartAPI.removeCartItem(id);
    await get().fetchCart();
    return res.data;
  },

  clearCart: async () => {
    const res = await cartAPI.clearCart();
    set({ items: [], summary: null });
    return res.data;
  },

  checkout: async (data) => {
    const res = await cartAPI.checkout(data);
    if (!data) {
      set({ items: [], summary: null });
    }
    return res.data;
  },
}));

export { useCartStore };
export default useCartStore;

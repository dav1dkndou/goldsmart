import { create } from 'zustand';
import { getConfig } from '../api/config';

const useConfigStore = create((set) => ({
  config: null,
  isLoading: false,

  fetchConfig: async () => {
    set({ isLoading: true });
    try {
      const res = await getConfig();
      set({ config: res.data.data || res.data, isLoading: false });
    } catch (error) {
      set({ isLoading: false });
      throw error;
    }
  },
}));

export { useConfigStore };
export default useConfigStore;

import { create } from "zustand";
import { api, csrf } from "@/lib/api";
export const useAuthStore = create((set) => ({
    user: null,
    loading: false,
    checked: false,
    load: async () => {
        set({ loading: true });
        try {
            const response = await api.get("/admin/me");
            set({ user: response.data.user, checked: true });
        }
        catch {
            set({ user: null, checked: true });
        }
        finally {
            set({ loading: false });
        }
    },
    login: async (email, password, remember) => {
        set({ loading: true });
        try {
            await csrf();
            const response = await api.post("/admin/login", { email, password, remember });
            set({ user: response.data.user, checked: true });
        }
        finally {
            set({ loading: false });
        }
    },
    logout: async () => {
        await api.post("/admin/logout");
        set({ user: null, checked: true });
    },
}));

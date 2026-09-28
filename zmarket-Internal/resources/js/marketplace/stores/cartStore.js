import { create } from "zustand";
import { persist } from "zustand/middleware";

function maxQuantity(product) {
    if (!product) {
        return 0;
    }

    return product.track_stock ? Math.max(0, Number(product.stock) || 0) : 999;
}

function clampQuantity(product, quantity) {
    return Math.min(Math.max(1, Number(quantity) || 1), Math.max(1, maxQuantity(product)));
}

export const useCartStore = create()(persist((set) => ({
    items: [],
    add: (product, quantity = 1) => set((state) => {
        const max = maxQuantity(product);
        if (!product?.available || max < 1) {
            return state;
        }

        const existing = state.items.find((item) => item.product.id === product.id);
        const nextQuantity = clampQuantity(product, (existing?.quantity ?? 0) + quantity);

        if (existing) {
            return {
                items: state.items.map((item) => item.product.id === product.id
                    ? { product, quantity: nextQuantity }
                    : item),
            };
        }

        return {
            items: [...state.items, { product, quantity: clampQuantity(product, quantity) }],
        };
    }),
    update: (productId, quantity) => set((state) => ({
        items: state.items.flatMap((item) => {
            if (item.product.id !== productId) {
                return [item];
            }

            if (!item.product.available || maxQuantity(item.product) < 1) {
                return [];
            }

            return [{ ...item, quantity: clampQuantity(item.product, quantity) }];
        }),
    })),
    remove: (productId) => set((state) => ({
        items: state.items.filter((item) => item.product.id !== productId),
    })),
    clear: () => set({ items: [] }),
}), {
    name: "kishamarket-cart-single-product",
    version: 7,
    migrate: (persisted, version) => {
        if (Number(version) < 7) {
            return { items: [] };
        }

        const items = Array.isArray(persisted?.items)
            ? persisted.items.filter((item) => item?.product?.id)
            : [];

        return { items };
    },
}));

export const cartSummary = (items) => ({
    quantity: items.reduce((sum, item) => sum + item.quantity, 0),
    total: items.reduce((sum, item) => sum + (Number(item.product.price) || 0) * item.quantity, 0),
});

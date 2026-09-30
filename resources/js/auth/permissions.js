/**
 * Permission per halaman. Dipakai untuk menentukan route mana yang boleh
 * dibuka dan menu mana yang tampil di TopHeader.
 *
 * Ini murni lapisan kenyamanan: menyembunyikan halaman tidak membuat endpoint
 * aman, karena backend tetap memeriksa permission tiap request. Daftarnya
 * sengaja dibuat rata dengan katalog `PermissionCatalog` supaya tidak ada
 * halaman yang bisa dibuka tapi gagal di tengah transaksi.
 */

/** Satu permission sudah cukup untuk masuk ke panel admin. */
export const ADMIN_PERMISSIONS = [
    'settings.view',
    'category.view',
    'product.view',
    'customer.view',
    'payment_method.view',
    'cash.view',
    'role.view',
    'user.view',
];

/** Kasir butuh katalog, transaksi, pembayaran, dan pelanggan. */
export const CASHIER_PERMISSIONS = [
    'product.view',
    'transaction.view',
    'transaction.create',
    'pos.view',
    'pos.sell',
];

/** Pelayan membuat pesanan dine in tanpa menyentuh pembayaran. */
export const WAITER_PERMISSIONS = [
    'product.view',
    'transaction.view',
    'transaction.create',
];

/** Dapur hanya butuh antrean produksi. */
export const KITCHEN_PERMISSIONS = ['kitchen.view', 'kitchen.update'];

/**
 * Halaman yang bisa jadi tujuan setelah login, urut dari yang paling sering
 * dipakai. Dipakai `Login` untuk mengarahkan user ke tempat yang dia punya
 * akses, bukan ke halaman yang pasti ditolak.
 */
export const LANDING_PAGES = [
    { path: '/kasir', permissions: CASHIER_PERMISSIONS },
    { path: '/koki', permissions: KITCHEN_PERMISSIONS },
    { path: '/waiters', permissions: WAITER_PERMISSIONS },
    { path: '/admin', permissions: ADMIN_PERMISSIONS },
];

/**
 * Halaman pertama yang boleh dibuka user berdasarkan permission-nya, atau
 * `null` bila ia tidak punya akses ke halaman mana pun.
 *
 * @param {string[]} permissions
 * @returns {string|null}
 */
export function landingFor(permissions = []) {
    const owned = new Set(permissions);

    const match = LANDING_PAGES.find((page) =>
        page.permissions.some((permission) => owned.has(permission)),
    );

    return match?.path ?? null;
}

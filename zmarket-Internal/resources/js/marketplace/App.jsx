import { Home, ArrowLeft } from "lucide-react";
import { Link, Route, Routes } from "react-router-dom";
import { CartPage, CheckoutPage, HomePage, OrderEditPage, OrderSuccessPage, ProductDetailPage, ProductsPage, PublicLayout, TrackOrderPage, } from "@/pages/PublicPages";
import { AdminCategoriesPage, AdminDashboardPage, AdminLayout, AdminLoginPage, AdminOnlyRoute, AdminOrdersPage, AdminProductsPage, AdminUsersPage, ProtectedRoute, } from "@/pages/AdminPages";
function NotFound() {
    return (<main className="flex min-h-screen items-center justify-center bg-slate-100 px-5">
      <section className="max-w-lg rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-xl">
        <p className="text-sm font-black uppercase tracking-[0.24em] text-emerald-700">404</p>
        <h1 className="mt-3 text-4xl font-black text-slate-950">Halaman tidak ditemukan</h1>
        <p className="mt-4 text-slate-600">Alamat yang dibuka tidak tersedia pada marketplace internal.</p>
        <Link to="/" className="mt-7 inline-flex items-center gap-2 rounded-xl bg-slate-950 px-6 py-3 font-black text-white"><ArrowLeft size={18}/><Home size={18}/>Kembali ke beranda</Link>
      </section>
    </main>);
}
export function App() {
    return (<Routes>
      <Route element={<PublicLayout />}>
        <Route index element={<HomePage />}/>
        <Route path="products" element={<ProductsPage />}/>
        <Route path="products/:slug" element={<ProductDetailPage />}/>
        <Route path="product/:slug" element={<ProductDetailPage />}/>
        <Route path="cart" element={<CartPage />}/>
        <Route path="checkout" element={<CheckoutPage />}/>
        <Route path="orders/:orderNumber/edit" element={<OrderEditPage />}/>
        <Route path="order-success/:orderNumber" element={<OrderSuccessPage />}/>
        <Route path="track-order" element={<TrackOrderPage />}/>
        <Route path="orders/track" element={<TrackOrderPage />}/>
      </Route>

      <Route path="admin/login" element={<AdminLoginPage />}/>

      <Route element={<ProtectedRoute />}>
        <Route path="admin" element={<AdminLayout />}>
          <Route index element={<AdminDashboardPage />}/>
          <Route path="categories" element={<AdminCategoriesPage />}/>
          <Route path="products" element={<AdminProductsPage />}/>
          <Route path="orders" element={<AdminOrdersPage />}/>
          <Route element={<AdminOnlyRoute />}>
    <Route path="users" element={<AdminUsersPage />} />
</Route>
        </Route>
      </Route>

      <Route path="*" element={<NotFound />}/>
    </Routes>);
}

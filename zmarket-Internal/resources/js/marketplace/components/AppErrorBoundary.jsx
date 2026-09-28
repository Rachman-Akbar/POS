import { Component } from "react";
export class AppErrorBoundary extends Component {
    state = { error: null };
    static getDerivedStateFromError(error) {
        return { error };
    }
    componentDidCatch(error, info) {
        console.error("Marketplace render error", error, info);
    }
    render() {
        if (!this.state.error) {
            return this.props.children;
        }
        return (<main className="flex min-h-screen items-center justify-center bg-slate-100 px-5 py-16">
        <section className="w-full max-w-xl rounded-3xl border border-rose-200 bg-white p-8 text-center shadow-xl">
          <p className="text-sm font-black uppercase tracking-[0.2em] text-rose-600">Aplikasi gagal dimuat</p>
          <h1 className="mt-3 text-3xl font-black text-slate-950">Halaman tidak lagi tampil kosong</h1>
          <p className="mt-4 text-sm leading-6 text-slate-600">Muat ulang halaman setelah Vite dan Laravel berjalan. Pesan teknis tersedia di bawah untuk membantu pengecekan.</p>
          <pre className="mt-5 max-h-40 overflow-auto rounded-2xl bg-slate-950 p-4 text-left text-xs text-slate-200">{this.state.error.message}</pre>
          <button type="button" onClick={() => window.location.reload()} className="mt-6 rounded-xl bg-slate-950 px-6 py-3 font-black text-white">Muat ulang</button>
        </section>
      </main>);
    }
}

import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath, URL } from "node:url";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const backendUrl = env.VITE_BACKEND_URL || env.APP_URL || "http://127.0.0.1:8000";

    return {
        plugins: [
            laravel({
                input: [
                    "resources/css/app.css",
                    "resources/js/marketplace/main.jsx",
                ],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                "@": fileURLToPath(new URL("./resources/js/marketplace", import.meta.url)),
            },
        },
        server: {
            host: "127.0.0.1",
            port: 5173,
            strictPort: false,
            proxy: {
                "/api": {
                    target: backendUrl,
                    changeOrigin: true,
                },
                "/sanctum": {
                    target: backendUrl,
                    changeOrigin: true,
                },
                "/storage": {
                    target: backendUrl,
                    changeOrigin: true,
                },
            },
        },
    };
});

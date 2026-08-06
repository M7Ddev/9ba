import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],

  /*
   * Single-origin deployment: the build lands inside Laravel's public directory
   * and Laravel serves it. One host, one URL, and CORS stops being a concern
   * because the browser never crosses an origin in production.
   *
   * Output goes to public/app/ rather than public/ itself so it can never
   * collide with Laravel's own index.php front controller. `base` must match,
   * otherwise the emitted asset URLs point at / and 404.
   */
  base: '/app/',
  build: {
    outDir: '../backend/public/app',
    emptyOutDir: true, // only ever clears public/app, never the rest of public/
  },

  server: {
    // Bind IPv4 explicitly. Vite's default host is "localhost", which on Windows
    // resolves to ::1 first and binds IPv6-only — so http://127.0.0.1:5173 then
    // refuses connections. The backend has the same constraint (GEMINI_FORCE_IPV4),
    // and config/cors.php allows both localhost:5173 and 127.0.0.1:5173.
    host: '127.0.0.1',
    port: 5173,
    strictPort: true, // fail loudly instead of silently drifting to 5174 (CORS would reject it)
    open: true,
  },
});

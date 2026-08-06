import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
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

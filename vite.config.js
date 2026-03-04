import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig(({ mode }) => ({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/js'),
    },
  },

  build: {
    // Output to assets — WordPress loads via wp_enqueue_script/style.
    outDir:      'assets/dist',
    emptyOutDir: true,

    // Generate manifest.json so PHP can resolve hashed filenames.
    manifest: true,

    rollupOptions: {
      input: {
        'frontend': resolve(__dirname, 'assets/js/frontend/main.js'),
        'admin':    resolve(__dirname, 'assets/js/admin/main.js'),
      },
      output: {
        // Predictable names for wp_enqueue_script.
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (info) => {
          if (info.name?.endsWith('.css')) return 'css/[name][extname]';
          return 'assets/[name]-[hash][extname]';
        },
      },
    },

    // Target modern browsers — IE11 not supported.
    target: ['es2020', 'chrome80', 'firefox78', 'safari14'],

    // Show bundle size warnings above 500kb.
    chunkSizeWarningLimit: 500,
  },

  // Dev server: proxy REST calls to local WordPress.
  server: {
    port: 3000,
    proxy: {
      '/wp-json': {
        target:       'http://localhost:8080',
        changeOrigin: true,
      },
      '/wp-admin': {
        target:       'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },

  // Use .env.local for WP_URL override.
  // VITE_WP_URL=http://mysite.local npm run dev
}));

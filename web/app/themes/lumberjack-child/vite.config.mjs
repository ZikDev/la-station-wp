import { defineConfig } from "vite";
import path from "path";

export default defineConfig({
  root: "./assets",
  base: "/",
  build: {
    outDir: "../dist",
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, "assets/js/main.js"),
        style: path.resolve(__dirname, "assets/scss/main.scss"),
      },
      output: {
        entryFileNames: "js/[name].js",
        assetFileNames: "css/[name].[ext]",
      },
    },
  },
});

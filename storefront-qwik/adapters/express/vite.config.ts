import { nodeServerAdapter } from "@builder.io/qwik-city/adapters/node-server/vite";
import { extendConfig } from "@builder.io/qwik-city/vite";
import baseConfig from "../../vite.config";

export default extendConfig(baseConfig, () => {
  return {
    build: {
      ssr: true,
      rollupOptions: {
        input: ["src/entry.express.tsx", "@qwik-city-plan"],
      },
    },
    // ssg: null — every page is SSR; SSG would freeze /sitemap.xml at build time and shadow the dynamic route.
    plugins: [nodeServerAdapter({ name: "express", ssg: null })],
  };
});

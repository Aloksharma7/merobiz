import type { MetadataRoute } from "next";

// The default install identity — shown to anyone who isn't a single-business
// employee (owner, admin, or a visitor who hasn't signed in yet). An employee
// locked to one workspace gets that business's own name and icon instead —
// see AppShell, which swaps the <link rel="manifest"> tag client-side once
// the workspace is known, pointing it at /manifest-business/{id} instead.
export default function manifest(): MetadataRoute.Manifest {
  return {
    id: "/",
    name: "MeroBiz",
    short_name: "MeroBiz",
    description: "Sales, expenses and team management for your businesses.",
    start_url: "/",
    display: "standalone",
    background_color: "#f7f9f7",
    theme_color: "#0b3c31",
    icons: [
      { src: "/pwa/icon-192", sizes: "192x192", type: "image/png", purpose: "any" },
      { src: "/pwa/icon-192", sizes: "192x192", type: "image/png", purpose: "maskable" },
      { src: "/pwa/icon-512", sizes: "512x512", type: "image/png", purpose: "any" },
      { src: "/pwa/icon-512", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
  };
}

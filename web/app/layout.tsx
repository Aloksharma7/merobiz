import type { Metadata, Viewport } from "next";
import { Providers } from "@/components/providers";
import "./globals.css";

// The root title is deliberately neutral. Once authentication resolves, AppShell
// applies either the MeroBiz portfolio title (owner/admin) or only the assigned
// business name (employee), preventing brand flashes during refresh.
export const metadata: Metadata = {
  title: "Business Workspace",
  description: "Secure business sales and operations workspace.",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#0b3c31",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body><Providers>{children}</Providers></body>
    </html>
  );
}

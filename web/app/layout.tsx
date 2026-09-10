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
      <head>
        {/* No apple-mobile-web-app-title here on purpose — leaving it unset means
            iOS uses the current page's <title> when "Add to Home Screen" is tapped,
            and AppShell already keeps that title in sync with the signed-in
            workspace (MeroBiz for owner/admin, the business name for an employee). */}
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
      </head>
      <body><Providers>{children}</Providers></body>
    </html>
  );
}

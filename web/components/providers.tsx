"use client";

import { AuthProvider } from "@/lib/auth-context";
import { BusinessProvider } from "@/lib/business-context";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import { Toaster } from "sonner";

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(() => new QueryClient({
    defaultOptions: {
      queries: {
        refetchOnWindowFocus: false,
        retry: 1,
        staleTime: 30_000,
      },
      mutations: { retry: 0 },
    },
  }));

  // Registering this is what makes the browser offer "Install app" / "Add to
  // Home Screen" on Android — see public/sw.js for why it deliberately does
  // nothing beyond existing (no caching of live financial data).
  useEffect(() => {
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/sw.js").catch(() => {
        // Installability just won't be offered — never worth surfacing to the user.
      });
    }
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BusinessProvider>{children}</BusinessProvider>
      </AuthProvider>
      <Toaster richColors closeButton position="top-right" />
    </QueryClientProvider>
  );
}

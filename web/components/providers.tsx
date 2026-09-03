"use client";

import { AuthProvider } from "@/lib/auth-context";
import { BusinessProvider } from "@/lib/business-context";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
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

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BusinessProvider>{children}</BusinessProvider>
      </AuthProvider>
      <Toaster richColors closeButton position="top-right" />
    </QueryClientProvider>
  );
}

"use client";

import { api } from "@/lib/api";
import type { Business } from "@/lib/types";
import { useAuth } from "@/lib/auth-context";
import { useQuery } from "@tanstack/react-query";
import { createContext, useContext, useMemo, type ReactNode } from "react";

type BusinessContextValue = {
  businesses: Business[];
  isLoading: boolean;
  getBusiness: (id?: string | number | null) => Business | undefined;
  can: (business: Business | undefined, permission: string) => boolean;
};

const BusinessContext = createContext<BusinessContextValue | null>(null);

export function BusinessProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const query = useQuery({
    queryKey: ["businesses"],
    queryFn: async () => (await api.get<{ data: Business[] }>("/businesses")).data.data,
    enabled: Boolean(user),
    staleTime: 2 * 60 * 1000,
  });

  const value = useMemo<BusinessContextValue>(() => ({
    businesses: query.data ?? [],
    isLoading: query.isLoading,
    getBusiness: (id) => query.data?.find((business) => business.id === Number(id)),
    can: (business, permission) => Boolean(
      business && (business.permissions.includes("*") || business.permissions.includes(permission)),
    ),
  }), [query.data, query.isLoading]);

  return <BusinessContext.Provider value={value}>{children}</BusinessContext.Provider>;
}

export function useBusinesses() {
  const value = useContext(BusinessContext);
  if (!value) throw new Error("useBusinesses must be used inside BusinessProvider");
  return value;
}

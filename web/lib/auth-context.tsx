"use client";

import { api, apiError, getCsrfCookie } from "@/lib/api";
import type { ApiMessage, User } from "@/lib/types";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { createContext, useCallback, useContext, type ReactNode } from "react";
import { toast } from "sonner";

type LoginInput = { email: string; password: string; remember?: boolean };
type RegisterInput = { name: string; email: string; phone?: string; password: string; password_confirmation: string };

type AuthContextValue = {
  user: User | null;
  isLoading: boolean;
  login: (input: LoginInput) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const userQuery = useQuery({
    queryKey: ["auth", "me"],
    queryFn: async () => {
      const response = await api.get<User | { data: User }>("/auth/me");
      const payload = response.data;
      return "data" in payload ? payload.data : payload;
    },
    retry: false,
    staleTime: 5 * 60 * 1000,
  });

  const login = useCallback(async (input: LoginInput) => {
    try {
      await getCsrfCookie();
      const response = await api.post<ApiMessage<{ user: User }>>("/auth/login", input);
      queryClient.setQueryData(["auth", "me"], response.data.user);
      await queryClient.invalidateQueries({ queryKey: ["businesses"] });
      const workspace = response.data.user.workspace;
      toast.success("Welcome back", {
        description: workspace?.mode === "employee" && workspace.business_name
          ? `${workspace.business_name} is ready.`
          : "Your businesses are ready.",
      });
      router.replace(
        workspace?.mode === "employee" && workspace.business_id
          ? `/b/${workspace.business_id}`
          : "/",
      );
    } catch (error) {
      toast.error("Could not sign in", { description: apiError(error, "Check your email and password.") });
      throw error;
    }
  }, [queryClient, router]);

  const register = useCallback(async (input: RegisterInput) => {
    try {
      await getCsrfCookie();
      const response = await api.post<ApiMessage<{ user: User }>>("/auth/register", input);
      queryClient.setQueryData(["auth", "me"], response.data.user);
      toast.success("Account created", { description: "Add your first business to begin." });
      router.replace("/businesses");
    } catch (error) {
      toast.error("Could not create account", { description: apiError(error) });
      throw error;
    }
  }, [queryClient, router]);

  const logout = useCallback(async () => {
    try {
      await getCsrfCookie();
      await api.post("/auth/logout");
    } finally {
      queryClient.clear();
      router.replace("/login");
    }
  }, [queryClient, router]);

  const refreshUser = useCallback(async () => {
    await userQuery.refetch();
  }, [userQuery]);

  return (
    <AuthContext.Provider
      value={{
        user: userQuery.data ?? null,
        isLoading: userQuery.isLoading,
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("useAuth must be used inside AuthProvider");
  return value;
}

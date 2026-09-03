import axios, { AxiosError } from "axios";

const apiOrigin = process.env.NEXT_PUBLIC_API_ORIGIN ?? "http://localhost:8000";

export const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? `${apiOrigin}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

export async function getCsrfCookie() {
  await axios.get(`${apiOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
}

export type ApiValidationErrors = Record<string, string[]>;

export function apiError(error: unknown, fallback = "Something went wrong.") {
  if (!axios.isAxiosError(error)) return fallback;
  const response = (error as AxiosError<{ message?: string; errors?: ApiValidationErrors }>).response;
  const errors = response?.data?.errors;
  if (errors) {
    const first = Object.values(errors).flat()[0];
    if (first) return first;
  }
  return response?.data?.message ?? fallback;
}

export function fieldErrors(error: unknown): ApiValidationErrors {
  if (!axios.isAxiosError(error)) return {};
  return (error as AxiosError<{ errors?: ApiValidationErrors }>).response?.data?.errors ?? {};
}

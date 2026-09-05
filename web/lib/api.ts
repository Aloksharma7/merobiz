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

export async function downloadFile(url: string, params: Record<string, string | number | boolean | undefined>, fallbackFilename: string) {
  const response = await api.get<Blob>(url, { params, responseType: "blob" });
  const disposition = response.headers["content-disposition"] as string | undefined;
  const match = disposition?.match(/filename="?([^"]+)"?/);
  const filename = match?.[1] ?? fallbackFilename;
  const blobUrl = window.URL.createObjectURL(response.data);
  const link = document.createElement("a");
  link.href = blobUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(blobUrl);
}

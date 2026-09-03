import type { Business } from "@/lib/types";
import type { CSSProperties } from "react";

export const DEFAULT_BRAND = {
  primary: "#135f48",
  nav: "#0b3c31",
  accent: "#eaa737",
} as const;

function validHex(value?: string | null) {
  if (!value) return null;
  const normalized = value.trim();
  return /^#[0-9a-f]{6}$/i.test(normalized) ? normalized : null;
}

export function brandingFor(business?: Business | null) {
  const settings = business?.settings?.branding ?? {};
  return {
    logoUrl: settings.logo_url ?? null,
    tagline: settings.tagline?.trim() || null,
    primary: validHex(settings.primary_color) ?? DEFAULT_BRAND.primary,
    nav: validHex(settings.nav_color) ?? DEFAULT_BRAND.nav,
    accent: validHex(settings.accent_color) ?? DEFAULT_BRAND.accent,
    showLogoWorkspace: settings.show_logo_workspace ?? true,
    showLogoInvoice: settings.show_logo_invoice ?? true,
  };
}

export function businessThemeStyle(business?: Business | null): CSSProperties | undefined {
  if (!business) return undefined;
  const brand = brandingFor(business);
  return {
    ["--brand" as string]: brand.primary,
    ["--brand-deep" as string]: brand.nav,
    ["--accent" as string]: brand.accent,
    ["--brand-soft" as string]: `color-mix(in srgb, ${brand.primary} 13%, white)`,
    ["--accent-soft" as string]: `color-mix(in srgb, ${brand.accent} 18%, white)`,
  };
}

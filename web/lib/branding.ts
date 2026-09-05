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

const DARK_INK = "#101915";

// WCAG relative-luminance formula: picks readable text for an arbitrary,
// owner-chosen background color instead of assuming it is always dark.
export function readableTextColor(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;
  const linearize = (channel: number) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
  const luminance = 0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b);
  return luminance > 0.179 ? DARK_INK : "#ffffff";
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
    ["--on-brand" as string]: readableTextColor(brand.primary),
    ["--on-brand-deep" as string]: readableTextColor(brand.nav),
    ["--on-accent" as string]: readableTextColor(brand.accent),
  };
}

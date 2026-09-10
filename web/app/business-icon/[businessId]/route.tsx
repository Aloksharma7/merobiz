import { iconMarkup, readableOn } from "@/lib/pwa-icon";
import { ImageResponse } from "next/og";
import type { NextRequest } from "next/server";

const apiOrigin = process.env.NEXT_PUBLIC_API_ORIGIN ?? "http://localhost:8000";

type Brand = { name: string; code: string; logo_url: string | null; primary_color: string | null; nav_color: string | null };

export async function GET(request: NextRequest, { params }: { params: Promise<{ businessId: string }> }) {
  const { businessId } = await params;
  const size = Number(request.nextUrl.searchParams.get("size") ?? 512) || 512;

  let brand: Brand | null = null;
  try {
    const response = await fetch(`${apiOrigin}/api/public/businesses/${businessId}/brand`, { next: { revalidate: 3600 } });
    if (response.ok) brand = await response.json();
  } catch {
    // Falls through to the generic mark below — a broken icon must never
    // block installing or opening the app.
  }

  if (!brand) {
    return new ImageResponse(iconMarkup({ size, background: "#0b3c31", foreground: "#ffffff", text: "M" }), { width: size, height: size });
  }

  const background = brand.logo_url ? "#ffffff" : (brand.nav_color ?? brand.primary_color ?? "#0b3c31");
  const initials = (brand.code || brand.name).replace(/[^A-Za-z0-9]/g, "").slice(0, 3).toUpperCase() || "BIZ";

  return new ImageResponse(
    iconMarkup({ size, background, foreground: readableOn(background), text: initials, logoUrl: brand.logo_url }),
    { width: size, height: size },
  );
}

const apiOrigin = process.env.NEXT_PUBLIC_API_ORIGIN ?? "http://localhost:8000";

export async function GET(_request: Request, { params }: { params: Promise<{ businessId: string }> }) {
  const { businessId } = await params;

  let name = "MeroBiz";
  let themeColor = "#0b3c31";
  try {
    const response = await fetch(`${apiOrigin}/api/public/businesses/${businessId}/brand`, { next: { revalidate: 3600 } });
    if (response.ok) {
      const brand = await response.json();
      if (brand.name) name = brand.name;
      if (brand.nav_color) themeColor = brand.nav_color;
    }
  } catch {
    // Falls through to the MeroBiz default below — a lookup failure should
    // never block the install prompt from working at all.
  }

  const manifest = {
    id: `/b/${businessId}`,
    name,
    short_name: name.length > 12 ? `${name.slice(0, 11)}…` : name,
    description: `${name} — sales, expenses and team management.`,
    start_url: `/b/${businessId}`,
    display: "standalone",
    background_color: "#f7f9f7",
    theme_color: themeColor,
    icons: [
      { src: `/business-icon/${businessId}?size=192`, sizes: "192x192", type: "image/png", purpose: "any" },
      { src: `/business-icon/${businessId}?size=192`, sizes: "192x192", type: "image/png", purpose: "maskable" },
      { src: `/business-icon/${businessId}?size=512`, sizes: "512x512", type: "image/png", purpose: "any" },
      { src: `/business-icon/${businessId}?size=512`, sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
  };

  return Response.json(manifest, { headers: { "Content-Type": "application/manifest+json" } });
}

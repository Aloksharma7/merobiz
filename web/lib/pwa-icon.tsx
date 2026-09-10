import { readableTextColor } from "@/lib/branding";

/**
 * Shared icon markup for every PWA install-icon route (default MeroBiz mark,
 * or a specific business's mark when an employee installs from their one
 * workspace) — rendered through next/og's ImageResponse (Satori), so this
 * has to stay plain flexbox/text, no arbitrary CSS.
 */
export function iconMarkup({ size, background, foreground, text, logoUrl }: { size: number; background: string; foreground: string; text: string; logoUrl?: string | null }) {
  // A little inset on every side keeps the mark clear of Android's maskable
  // "safe zone" crop, so it isn't clipped when the OS applies its own shape.
  const inset = Math.round(size * 0.08);

  return (
    <div
      style={{
        width: size,
        height: size,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        background,
        borderRadius: size * 0.22,
      }}
    >
      {logoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={logoUrl}
          width={size - inset * 2}
          height={size - inset * 2}
          style={{ objectFit: "contain", borderRadius: size * 0.12 }}
        />
      ) : (
        <div
          style={{
            display: "flex",
            fontSize: size * 0.42,
            fontWeight: 800,
            color: foreground,
            letterSpacing: -1,
          }}
        >
          {text}
        </div>
      )}
    </div>
  );
}

export function readableOn(background: string): string {
  return /^#[0-9a-f]{6}$/i.test(background) ? readableTextColor(background) : "#ffffff";
}

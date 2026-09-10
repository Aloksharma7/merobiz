import { iconMarkup } from "@/lib/pwa-icon";
import { ImageResponse } from "next/og";

export function GET() {
  return new ImageResponse(iconMarkup({ size: 192, background: "#0b3c31", foreground: "#ffffff", text: "M" }), { width: 192, height: 192 });
}

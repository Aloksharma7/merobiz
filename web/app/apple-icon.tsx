import { iconMarkup } from "@/lib/pwa-icon";
import { ImageResponse } from "next/og";

export const size = { width: 180, height: 180 };
export const contentType = "image/png";

export default function AppleIcon() {
  return new ImageResponse(iconMarkup({ size: 180, background: "#0b3c31", foreground: "#ffffff", text: "M" }), size);
}

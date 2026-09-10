import { iconMarkup } from "@/lib/pwa-icon";
import { ImageResponse } from "next/og";

export const size = { width: 32, height: 32 };
export const contentType = "image/png";

export default function Icon() {
  return new ImageResponse(iconMarkup({ size: 32, background: "#0b3c31", foreground: "#ffffff", text: "M" }), size);
}

import { LinkButton } from "@/components/ui/button";
import { Compass } from "lucide-react";

export default function NotFoundPage() {
  return (
    <div className="grid min-h-screen place-items-center bg-[var(--surface-soft)] p-6">
      <div className="w-full max-w-md rounded-[var(--radius)] border border-[var(--line)] bg-white p-8 text-center shadow-[var(--shadow-sm)]">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand)]"><Compass size={22} /></div>
        <h1 className="mt-4 text-lg font-extrabold tracking-[-0.02em] text-[var(--ink)]">Page not found</h1>
        <p className="mx-auto mt-1.5 max-w-sm text-sm leading-6 text-[var(--ink-soft)]">The page you are looking for does not exist or may have moved.</p>
        <div className="mt-6 flex justify-center">
          <LinkButton href="/">Go home</LinkButton>
        </div>
      </div>
    </div>
  );
}

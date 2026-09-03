"use client";

import { cn } from "@/lib/utils";
import { X } from "lucide-react";
import { useEffect, useId, useRef, type ReactNode } from "react";

export function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  size = "md",
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  size?: "sm" | "md" | "lg" | "xl";
}) {
  const titleId = useId();
  const descriptionId = useId();
  const dialogRef = useRef<HTMLElement>(null);

  useEffect(() => {
    if (!open) return;
    const previous = document.body.style.overflow;
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    document.body.style.overflow = "hidden";
    window.requestAnimationFrame(() => dialogRef.current?.focus());
    const listener = (event: KeyboardEvent) => event.key === "Escape" && onClose();
    window.addEventListener("keydown", listener);
    return () => {
      document.body.style.overflow = previous;
      window.removeEventListener("keydown", listener);
      previouslyFocused?.focus();
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="modal-backdrop-enter fixed inset-0 z-[80] flex items-end justify-center bg-[#071c15]/45 p-0 backdrop-blur-[3px] sm:items-center sm:p-5" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={cn(
          "modal-panel-enter max-h-[94dvh] w-full overflow-hidden rounded-t-[26px] border border-white/70 bg-white shadow-[var(--shadow-md)] sm:max-h-[94vh] sm:rounded-[24px]",
          size === "sm" && "sm:max-w-md",
          size === "md" && "sm:max-w-xl",
          size === "lg" && "sm:max-w-3xl",
          size === "xl" && "sm:max-w-5xl",
        )}
      >
        <header className="flex items-start justify-between gap-4 border-b border-[var(--line)] px-5 py-4 sm:px-6 sm:py-5">
          <div>
            <h2 id={titleId} className="text-lg font-extrabold tracking-[-0.02em] text-[var(--ink)]">{title}</h2>
            {description ? <p id={descriptionId} className="mt-1 text-sm leading-5 text-[var(--ink-soft)]">{description}</p> : null}
          </div>
          <button type="button" onClick={onClose} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[var(--ink-soft)] transition hover:bg-[#eef1ee] hover:text-[var(--ink)]" aria-label="Close dialog">
            <X size={19} />
          </button>
        </header>
        <div className="max-h-[calc(94dvh-9rem)] overflow-y-auto overscroll-contain px-5 py-5 scrollbar-thin sm:max-h-[calc(94vh-9rem)] sm:px-6">{children}</div>
        {footer ? <footer className="safe-bottom flex flex-col-reverse gap-2 border-t border-[var(--line)] bg-[#fbfcfa] px-5 py-4 sm:flex-row sm:justify-end sm:px-6">{footer}</footer> : null}
      </section>
    </div>
  );
}

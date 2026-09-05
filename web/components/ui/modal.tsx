"use client";

import { cn } from "@/lib/utils";
import { X } from "lucide-react";
import { useEffect, useId, useRef, type ReactNode } from "react";
import { createPortal } from "react-dom";

// Body scroll-lock uses a shared reference count, not a naive save/restore per
// modal. With a naive approach, closing one modal while another opens in the
// same instant (e.g. the sale form closing as the invoice-detail modal opens
// right after) races: the closing modal's cleanup can restore `overflow` to
// visible AFTER the new modal already locked it, leaving the page scrollable
// but also leaving stale focus/backdrop state that makes it feel unresponsive
// until a manual reload. A count only unlocks when the last modal closes.
let openModalCount = 0;
let previousBodyOverflow = "";

function lockBodyScroll() {
  if (openModalCount === 0) previousBodyOverflow = document.body.style.overflow;
  openModalCount += 1;
  document.body.style.overflow = "hidden";
}

function unlockBodyScroll() {
  openModalCount = Math.max(0, openModalCount - 1);
  if (openModalCount === 0) document.body.style.overflow = previousBodyOverflow;
}

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
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    lockBodyScroll();
    window.requestAnimationFrame(() => dialogRef.current?.focus());
    const listener = (event: KeyboardEvent) => event.key === "Escape" && onClose();
    window.addEventListener("keydown", listener);
    return () => {
      unlockBodyScroll();
      window.removeEventListener("keydown", listener);
      // Don't steal focus back if another modal already took over (e.g. this
      // one closing as a follow-up modal opens in the same action) — only
      // restore focus when nothing else is currently open.
      if (openModalCount === 0) previouslyFocused?.focus();
    };
  }, [open, onClose]);

  if (!open) return null;

  return createPortal(
    <div className="modal-backdrop-enter fixed inset-0 z-[80] flex items-end justify-center bg-[#071c15]/45 p-0 backdrop-blur-[3px] sm:items-center sm:p-5" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={cn(
          "modal-panel-enter flex max-h-[94dvh] w-full flex-col overflow-hidden rounded-t-[26px] border border-white/70 bg-white shadow-[var(--shadow-md)] sm:max-h-[94vh] sm:rounded-[24px]",
          size === "sm" && "sm:max-w-md",
          size === "md" && "sm:max-w-xl",
          size === "lg" && "sm:max-w-3xl",
          size === "xl" && "sm:max-w-5xl",
        )}
      >
        <header className="flex shrink-0 items-start justify-between gap-4 border-b border-[var(--line)] px-5 py-4 sm:px-6 sm:py-5">
          <div>
            <h2 id={titleId} className="text-lg font-extrabold tracking-[-0.02em] text-[var(--ink)]">{title}</h2>
            {description ? <p id={descriptionId} className="mt-1 text-sm leading-5 text-[var(--ink-soft)]">{description}</p> : null}
          </div>
          <button type="button" onClick={onClose} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[var(--ink-soft)] transition hover:bg-[#eef1ee] hover:text-[var(--ink)]" aria-label="Close dialog">
            <X size={19} />
          </button>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 scrollbar-thin sm:px-6">{children}</div>
        {footer ? <footer className="safe-bottom flex shrink-0 flex-col-reverse gap-2 border-t border-[var(--line)] bg-[#fbfcfa] px-5 py-4 sm:flex-row sm:justify-end sm:px-6">{footer}</footer> : null}
      </section>
    </div>,
    document.body,
  );
}

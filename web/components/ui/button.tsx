import { cn } from "@/lib/utils";
import Link, { type LinkProps } from "next/link";
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, ReactNode } from "react";

type Variant = "primary" | "secondary" | "ghost" | "danger" | "quiet";
type Size = "sm" | "md" | "lg" | "icon";

type SharedProps = {
  variant?: Variant;
  size?: Size;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
};

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & SharedProps & {
  loading?: boolean;
};

type LinkButtonProps = LinkProps & Omit<AnchorHTMLAttributes<HTMLAnchorElement>, "href"> & SharedProps;

function styles(variant: Variant, size: Size, className?: string) {
  return cn(
    "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl font-semibold transition-[transform,background-color,border-color,color,box-shadow,opacity] duration-150 ease-out active:translate-y-px active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:translate-y-0 disabled:active:scale-100",
    variant === "primary" && "bg-[var(--brand)] text-[var(--on-brand)] shadow-[0_8px_20px_rgb(19_95_72/0.2)] hover:bg-[var(--brand-deep)] hover:text-[var(--on-brand-deep)]",
    variant === "secondary" && "border border-[var(--line-strong)] bg-white text-[var(--ink)] shadow-sm hover:border-[var(--brand)] hover:text-[var(--brand)]",
    variant === "ghost" && "bg-transparent text-[var(--ink-soft)] hover:bg-black/5 hover:text-[var(--ink)]",
    variant === "quiet" && "bg-[var(--brand-soft)] text-[var(--brand-deep)] hover:bg-[#d2e9df]",
    variant === "danger" && "bg-[var(--danger)] text-white hover:bg-[#993434]",
    size === "sm" && "min-h-9 rounded-lg px-3 text-sm",
    size === "md" && "px-4 py-2.5 text-sm",
    size === "lg" && "min-h-12 px-5 text-base",
    size === "icon" && "h-11 w-11 min-h-11 p-0",
    className,
  );
}

export function Button({ className, variant = "primary", size = "md", loading, leftIcon, rightIcon, children, disabled, ...props }: ButtonProps) {
  return (
    <button className={styles(variant, size, className)} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
      {loading ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true" /> : leftIcon}
      {children}
      {!loading && rightIcon}
    </button>
  );
}

export function LinkButton({ className, variant = "primary", size = "md", leftIcon, rightIcon, children, ...props }: LinkButtonProps) {
  return (
    <Link className={styles(variant, size, className)} {...props}>
      {leftIcon}
      {children}
      {rightIcon}
    </Link>
  );
}

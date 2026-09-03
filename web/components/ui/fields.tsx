import { cn } from "@/lib/utils";
import { forwardRef, type InputHTMLAttributes, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";

type FieldShellProps = {
  label: string;
  htmlFor?: string;
  hint?: string;
  error?: string;
  required?: boolean;
  className?: string;
  children: React.ReactNode;
};

export function FieldShell({ label, htmlFor, hint, error, required, className, children }: FieldShellProps) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={htmlFor} className="text-sm font-semibold text-[var(--ink)]">
          {label}{required ? <span className="ml-0.5 text-[var(--danger)]" aria-hidden="true">*</span> : null}
        </label>
        {hint ? <span className="text-xs text-[var(--ink-soft)]">{hint}</span> : null}
      </div>
      {children}
      {error ? <p className="text-xs font-medium text-[var(--danger)]" role="alert">{error}</p> : null}
    </div>
  );
}

const fieldClasses = "h-11 w-full rounded-xl border border-[var(--line-strong)] bg-white px-3.5 text-sm text-[var(--ink)] shadow-[0_1px_2px_rgb(16_32_25/0.02)] placeholder:text-[#8b9690] transition-[border-color,box-shadow,background-color] duration-150 ease-out hover:border-[#aab7ad] focus:border-[var(--brand)] focus:outline-none focus:ring-3 focus:ring-[var(--brand-soft)] disabled:bg-[#f0f2ef] disabled:text-[#77827c]";

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
  return <input ref={ref} className={cn(fieldClasses, className)} {...props} />;
});

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select({ className, ...props }, ref) {
  return <select ref={ref} className={cn(fieldClasses, "pr-9", className)} {...props} />;
});

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...props }, ref) {
  return <textarea ref={ref} className={cn(fieldClasses, "h-auto min-h-24 resize-y py-3", className)} {...props} />;
});

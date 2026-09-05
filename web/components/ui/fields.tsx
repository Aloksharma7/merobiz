import { cn } from "@/lib/utils";
import { Eye, EyeOff } from "lucide-react";
import { forwardRef, useState, type InputHTMLAttributes, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";

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

export const PasswordInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function PasswordInput({ className, ...props }, ref) {
  const [visible, setVisible] = useState(false);
  return (
    <div className="relative">
      <input ref={ref} type={visible ? "text" : "password"} className={cn(fieldClasses, "pr-11", className)} {...props} />
      <button
        type="button"
        onClick={() => setVisible((current) => !current)}
        className="absolute right-3 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[#eef1ee] hover:text-[var(--ink)]"
        aria-label={visible ? "Hide password" : "Show password"}
      >
        {visible ? <EyeOff size={16} /> : <Eye size={16} />}
      </button>
    </div>
  );
});

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select({ className, ...props }, ref) {
  return <select ref={ref} className={cn(fieldClasses, "pr-9", className)} {...props} />;
});

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...props }, ref) {
  return <textarea ref={ref} className={cn(fieldClasses, "h-auto min-h-24 resize-y py-3", className)} {...props} />;
});

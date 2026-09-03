import { ArrowUpRight, CheckCircle2, Layers3, ReceiptText, ShieldCheck } from "lucide-react";

function WorkspaceBrand() {
  return (
    <div className="flex items-center gap-3">
      <span className="grid h-10 w-10 place-items-center rounded-[13px] bg-white text-[var(--brand-deep)] shadow-[0_8px_24px_rgb(5_41_31/0.16)]">
        <Layers3 size={21} />
      </span>
      <div className="leading-none">
        <div className="text-[1.05rem] font-black tracking-[-0.035em] text-white">Business Workspace</div>
        <div className="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/60">Secure sign in</div>
      </div>
    </div>
  );
}

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <main className="min-h-screen p-3 sm:p-5 lg:grid lg:grid-cols-[minmax(420px,0.92fr)_minmax(520px,1.08fr)]">
      <section className="relative hidden min-h-[calc(100vh-2.5rem)] overflow-hidden rounded-[30px] bg-[var(--brand-deep)] p-9 text-white lg:flex lg:flex-col">
        <div className="surface-grid absolute inset-0 opacity-75" />
        <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[var(--accent)]/20 blur-2xl" />
        <div className="absolute -bottom-40 left-20 h-96 w-96 rounded-full bg-[#36a27d]/15 blur-3xl" />
        <div className="relative"><WorkspaceBrand /></div>
        <div className="relative my-auto max-w-xl py-16">
          <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-semibold text-white/75">
            <ShieldCheck size={14} className="text-[var(--accent)]" /> Access only the workspace assigned to you
          </div>
          <h1 className="mt-6 text-balance text-[clamp(2.6rem,4.3vw,4.8rem)] font-black leading-[0.98] tracking-[-0.055em]">
            Daily business work, <span className="text-[#95d7bf]">kept clear.</span>
          </h1>
          <p className="mt-6 max-w-lg text-base leading-7 text-white/62">
            Create customer invoices, manage sales and work inside the business access granted to your account.
          </p>
          <div className="mt-10 grid max-w-lg gap-3 sm:grid-cols-2">
            {[
              [ReceiptText, "Professional customer invoices"],
              [ShieldCheck, "Business-scoped access"],
              [CheckCircle2, "Simple daily sales workflow"],
              [ArrowUpRight, "Fast customer follow-up"],
            ].map(([Icon, label]) => {
              const Component = Icon as typeof ReceiptText;
              return <div key={String(label)} className="flex items-center gap-3 rounded-2xl border border-white/8 bg-white/5 p-3.5 text-sm font-semibold text-white/78"><Component size={17} className="text-[var(--accent)]" />{String(label)}</div>;
            })}
          </div>
        </div>
        <p className="relative text-xs text-white/35">Your view is determined by the business access assigned to your account.</p>
      </section>
      <section className="flex min-h-[calc(100vh-1.5rem)] items-center justify-center px-4 py-10 sm:px-8 lg:min-h-[calc(100vh-2.5rem)] lg:px-12">
        <div className="w-full max-w-md">
          <div className="mb-10 flex items-center gap-3 lg:hidden">
            <span className="grid h-10 w-10 place-items-center rounded-[13px] bg-[var(--brand-deep)] text-white"><Layers3 size={20} /></span>
            <div><p className="font-black tracking-[-0.02em]">Business Workspace</p><p className="text-[10px] font-bold uppercase tracking-[0.15em] text-[var(--ink-soft)]">Secure sign in</p></div>
          </div>
          {children}
        </div>
      </section>
    </main>
  );
}

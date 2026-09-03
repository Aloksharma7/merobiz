"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input } from "@/components/ui/fields";
import { useAuth } from "@/lib/auth-context";
import { LockKeyhole, Mail } from "lucide-react";
import Link from "next/link";
import { useState, type FormEvent } from "react";

export default function LoginPage() {
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("password");
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setLoading(true);
    try {
      await login({ email, password, remember });
    } catch {
      // The auth provider presents the server message in a toast.
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">Welcome back</p>
      <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] text-[var(--ink)]">Sign in to your workspace</h1>
      <p className="mt-3 text-sm leading-6 text-[var(--ink-soft)]">Use the account provided for your business. Owners are taken to their portfolio; staff go directly to their assigned company.</p>

      <form onSubmit={submit} className="mt-8 space-y-5">
        <FieldShell label="Email address" htmlFor="email" required>
          <div className="relative"><Mail size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input id="email" type="email" autoComplete="email" required value={email} onChange={(event) => setEmail(event.target.value)} className="pl-10" /></div>
        </FieldShell>
        <FieldShell label="Password" htmlFor="password" required>
          <div className="relative"><LockKeyhole size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input id="password" type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} className="pl-10" /></div>
        </FieldShell>
        <label className="flex items-center gap-2.5 text-sm font-medium text-[var(--ink-soft)]">
          <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} className="h-4 w-4 rounded border-[var(--line-strong)] accent-[var(--brand)]" />
          Keep me signed in on this device
        </label>
        <Button type="submit" size="lg" className="w-full" loading={loading}>Sign in</Button>
      </form>

      <p className="mt-7 text-center text-sm text-[var(--ink-soft)]">Setting up a new owner workspace? <Link href="/register" className="font-bold text-[var(--brand)] hover:underline">Create an owner account</Link></p>
    </div>
  );
}

"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input } from "@/components/ui/fields";
import { fieldErrors } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import Link from "next/link";
import { useState, type FormEvent } from "react";

const initial = { name: "", email: "", phone: "", password: "", password_confirmation: "" };

export default function RegisterPage() {
  const { register } = useAuth();
  const [form, setForm] = useState(initial);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setLoading(true);
    setErrors({});
    try {
      await register(form);
    } catch (error) {
      setErrors(fieldErrors(error));
    } finally {
      setLoading(false);
    }
  }

  function update(key: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.16em] text-[var(--brand)]">Start simply</p>
      <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] text-[var(--ink)]">Create your owner account</h1>
      <p className="mt-3 text-sm leading-6 text-[var(--ink-soft)]">You can add businesses, partners and employees after signing in.</p>
      <form onSubmit={submit} className="mt-7 grid gap-4">
        <FieldShell label="Full name" htmlFor="name" error={errors.name?.[0]} required><Input id="name" autoComplete="name" value={form.name} onChange={(event) => update("name", event.target.value)} required /></FieldShell>
        <FieldShell label="Email address" htmlFor="register-email" error={errors.email?.[0]} required><Input id="register-email" type="email" autoComplete="email" value={form.email} onChange={(event) => update("email", event.target.value)} required /></FieldShell>
        <FieldShell label="Phone number" htmlFor="phone" error={errors.phone?.[0]} hint="Optional"><Input id="phone" type="tel" autoComplete="tel" value={form.phone} onChange={(event) => update("phone", event.target.value)} /></FieldShell>
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Password" htmlFor="new-password" error={errors.password?.[0]} required><Input id="new-password" type="password" autoComplete="new-password" minLength={8} value={form.password} onChange={(event) => update("password", event.target.value)} required /></FieldShell>
          <FieldShell label="Confirm" htmlFor="confirm-password" required><Input id="confirm-password" type="password" autoComplete="new-password" minLength={8} value={form.password_confirmation} onChange={(event) => update("password_confirmation", event.target.value)} required /></FieldShell>
        </div>
        <Button type="submit" size="lg" className="mt-2 w-full" loading={loading}>Create account</Button>
      </form>
      <p className="mt-7 text-center text-sm text-[var(--ink-soft)]">Already have an account? <Link href="/login" className="font-bold text-[var(--brand)] hover:underline">Sign in</Link></p>
    </div>
  );
}

import { format, parseISO } from "date-fns";
import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function money(value: number | string | null | undefined, currency = "NPR") {
  const amount = Number(value ?? 0);
  try {
    return new Intl.NumberFormat("en-NP", {
      style: "currency",
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
  } catch {
    return `${currency} ${(Number.isFinite(amount) ? amount : 0).toLocaleString("en-NP")}`;
  }
}

export function compactMoney(value: number, currency = "NPR") {
  try {
    return new Intl.NumberFormat("en-NP", {
      style: "currency",
      currency,
      notation: "compact",
      maximumFractionDigits: 1,
    }).format(value);
  } catch {
    return money(value, currency);
  }
}

export function number(value: number | string | null | undefined, digits = 0) {
  return Number(value ?? 0).toLocaleString("en-NP", {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}

export function prettyDate(value?: string | null, pattern = "dd MMM yyyy") {
  if (!value) return "—";
  try {
    return format(parseISO(value), pattern);
  } catch {
    return value;
  }
}

export function today() {
  return format(new Date(), "yyyy-MM-dd");
}

/**
 * Convert enum/API values such as `bank_transfer` into a readable label.
 * API fields are allowed to be absent so a partially-loaded resource can never
 * crash an invoice, table, or shell while rendering.
 */
export function humanize(value?: string | null, fallback = "—") {
  if (!value || typeof value !== "string") return fallback;
  return value
    .replace(/_/g, " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function initials(value: string) {
  return value
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

export function percentChangeLabel(value?: number) {
  if (value === undefined || Number.isNaN(value)) return "No comparison";
  if (value === 0) return "No change";
  return `${value > 0 ? "+" : ""}${value.toFixed(1)}% vs previous`;
}

const belowTwenty = [
  "Zero", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine",
  "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen",
];
const tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];

function belowThousand(value: number): string {
  const parts: string[] = [];
  let remaining = Math.floor(value);
  if (remaining >= 100) {
    parts.push(`${belowTwenty[Math.floor(remaining / 100)]} Hundred`);
    remaining %= 100;
  }
  if (remaining >= 20) {
    parts.push(tens[Math.floor(remaining / 10)]);
    remaining %= 10;
  }
  if (remaining > 0) parts.push(belowTwenty[remaining]);
  return parts.join(" ");
}

/** Uses the lakh/crore grouping commonly used in Nepalese business documents. */
function integerToNepalWords(value: number): string {
  if (value === 0) return "Zero";
  const groups = [
    { value: 10_000_000, label: "Crore" },
    { value: 100_000, label: "Lakh" },
    { value: 1_000, label: "Thousand" },
  ];
  let remaining = Math.floor(Math.abs(value));
  const parts: string[] = [];

  for (const group of groups) {
    if (remaining >= group.value) {
      const count = Math.floor(remaining / group.value);
      parts.push(`${integerToNepalWords(count)} ${group.label}`);
      remaining %= group.value;
    }
  }
  if (remaining > 0) parts.push(belowThousand(remaining));
  return parts.join(" ");
}

export function amountInWords(value: number | string | null | undefined, currency = "NPR") {
  const amount = Number(value ?? 0);
  const safe = Number.isFinite(amount) ? Math.max(0, amount) : 0;
  const totalMinor = Math.round(safe * 100);
  const major = Math.floor(totalMinor / 100);
  const minor = totalMinor % 100;
  const currencyLabels: Record<string, { major: string; minor: string }> = {
    NPR: { major: "Nepalese Rupees", minor: "Paisa" },
    INR: { major: "Indian Rupees", minor: "Paise" },
    USD: { major: "US Dollars", minor: "Cents" },
  };
  const label = currencyLabels[currency.toUpperCase()] ?? { major: currency.toUpperCase(), minor: "Cents" };
  const fraction = minor > 0 ? ` and ${integerToNepalWords(minor)} ${label.minor}` : "";
  return `${label.major} ${integerToNepalWords(major)}${fraction} Only`;
}

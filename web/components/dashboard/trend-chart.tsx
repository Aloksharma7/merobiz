"use client";

import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { compactMoney } from "@/lib/utils";
import type { TrendPoint } from "@/lib/types";
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

export function TrendChart({ data, currency = "NPR", portfolio = false, salesOnly = false }: { data: TrendPoint[]; currency?: string; portfolio?: boolean; salesOnly?: boolean }) {
  return (
    <Card className="min-w-0 overflow-hidden">
      <CardHeader title="Performance trend" description="Six-month view of sales and profit" action={<div className="flex items-center gap-3 text-[11px] font-semibold text-[var(--ink-soft)]"><span className="flex items-center gap-1.5"><i className="h-2 w-2 rounded-full bg-[var(--brand)]" />Net sales</span>{salesOnly ? null : <span className="flex items-center gap-1.5"><i className="h-2 w-2 rounded-full bg-[var(--accent)]" />{portfolio ? "Your profit" : "Net profit"}</span>}</div>} />
      <CardBody className="h-[320px] px-2 pb-3 pt-5 sm:px-5">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={data} margin={{ top: 8, right: 10, left: 0, bottom: 0 }}>
            <defs>
              <linearGradient id="salesGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#135f48" stopOpacity={0.22} /><stop offset="100%" stopColor="#135f48" stopOpacity={0} /></linearGradient>
              <linearGradient id="profitGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#eaa737" stopOpacity={0.26} /><stop offset="100%" stopColor="#eaa737" stopOpacity={0} /></linearGradient>
            </defs>
            <CartesianGrid stroke="#e4e9e3" strokeDasharray="4 5" vertical={false} />
            <XAxis dataKey="label" axisLine={false} tickLine={false} tick={{ fill: "#718078", fontSize: 11, fontWeight: 600 }} dy={8} />
            <YAxis axisLine={false} tickLine={false} width={62} tick={{ fill: "#718078", fontSize: 10 }} tickFormatter={(value) => compactMoney(Number(value), currency).replace(currency, "").trim()} />
            <Tooltip cursor={{ stroke: "#b8c3ba", strokeDasharray: "4 4" }} content={({ active, payload, label }) => {
              if (!active || !payload?.length) return null;
              return <div className="rounded-xl border border-[var(--line)] bg-white p-3 shadow-[var(--shadow-md)]"><p className="text-xs font-bold text-[var(--ink-soft)]">{label}</p>{payload.filter((item) => !salesOnly || item.dataKey === "net_sales").map((item) => <div key={String(item.dataKey)} className="mt-1.5 flex min-w-44 items-center justify-between gap-5 text-sm"><span className="font-medium text-[var(--ink-soft)]">{item.dataKey === "net_sales" ? "Net sales" : portfolio ? "Your profit" : "Net profit"}</span><span className="font-bold">{compactMoney(Number(item.value), currency)}</span></div>)}</div>;
            }} />
            <Area type="monotone" dataKey="net_sales" stroke="#135f48" strokeWidth={2.5} fill="url(#salesGradient)" dot={false} activeDot={{ r: 4, strokeWidth: 2, fill: "white", stroke: "#135f48" }} />
            {salesOnly ? null : <Area type="monotone" dataKey={portfolio ? "attributable_profit" : "net_profit"} stroke="#eaa737" strokeWidth={2.5} fill="url(#profitGradient)" dot={false} activeDot={{ r: 4, strokeWidth: 2, fill: "white", stroke: "#eaa737" }} />}
          </AreaChart>
        </ResponsiveContainer>
      </CardBody>
    </Card>
  );
}

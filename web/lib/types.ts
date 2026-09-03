export type User = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  initials: string;
  preferred_currency: string;
  workspace?: {
    mode: "portfolio" | "employee";
    business_id?: number | null;
    business_name?: string | null;
    business_code?: string | null;
  };
};

export type BusinessType = "product" | "service" | "digital_subscription" | "mixed";
export type BusinessRole = "owner" | "admin" | "employee";

export type InvoiceBusinessSettings = {
  website?: string | null;
  invoice_terms?: string | null;
  invoice_note?: string | null;
  bank_name?: string | null;
  bank_account_name?: string | null;
  bank_account_number?: string | null;
  bank_branch?: string | null;
  authorized_name?: string | null;
  authorized_title?: string | null;
  show_seller_address?: boolean;
  show_seller_phone?: boolean;
  show_seller_email?: boolean;
  show_seller_website?: boolean;
  show_seller_pan?: boolean;
  show_customer_address?: boolean;
  show_customer_phone?: boolean;
  show_customer_email?: boolean;
  show_customer_pan?: boolean;
  show_due_date?: boolean;
  show_payment_mode?: boolean;
  show_prepared_by?: boolean;
  show_status?: boolean;
  show_item_discount?: boolean;
  show_item_tax?: boolean;
  show_discount_summary?: boolean;
  show_tax_summary?: boolean;
  show_amount_in_words?: boolean;
  show_bank_details?: boolean;
  show_payment_record?: boolean;
  show_notes?: boolean;
  show_terms?: boolean;
  show_signature?: boolean;
};

export type BusinessBrandingSettings = {
  logo_path?: string | null;
  logo_url?: string | null;
  tagline?: string | null;
  primary_color?: string | null;
  nav_color?: string | null;
  accent_color?: string | null;
  show_logo_workspace?: boolean;
  show_logo_invoice?: boolean;
};

export type BusinessSettings = {
  country?: string;
  number_format?: string;
  branding?: BusinessBrandingSettings;
  invoice?: InvoiceBusinessSettings;
  [key: string]: unknown;
};

export type Business = {
  id: number;
  name: string;
  slug: string;
  code: string;
  business_type: BusinessType;
  currency: string;
  pan_number?: string | null;
  vat_number?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  invoice_prefix: string;
  default_tax_rate: number;
  status: string;
  settings: BusinessSettings;
  my_role: BusinessRole;
  permissions: string[];
  ownership_percent: number;
  profit_share_percent: number;
  created_at: string;
};

export type Customer = {
  id: number;
  business_id: number;
  name: string;
  phone?: string | null;
  email?: string | null;
  pan_number?: string | null;
  address?: string | null;
  opening_balance: number;
  outstanding_balance: number;
  notes?: string | null;
  active: boolean;
  created_at: string;
};

export type ProductType = "product" | "service" | "digital_subscription";
export type Product = {
  id: number;
  business_id: number;
  sku?: string | null;
  name: string;
  type: ProductType;
  unit: string;
  sale_price: number;
  cost_price: number | null;
  tax_rate: number;
  track_inventory: boolean;
  stock_quantity: number;
  reorder_level: number;
  active: boolean;
  metadata: Record<string, unknown>;
  created_at: string;
};

export type Payment = {
  id: number;
  payment_number: string;
  payment_date: string;
  amount: number;
  method: PaymentMethod;
  reference?: string | null;
  notes?: string | null;
  recorded_by?: { id: number; name: string };
  created_at: string;
};

export type InvoiceItem = {
  id: number;
  product_id?: number | null;
  product_name?: string | null;
  description: string;
  quantity: number;
  unit_price: number;
  cost_price: number | null;
  discount_amount: number;
  tax_rate: number;
  tax_amount: number;
  line_total: number;
};

export type InvoiceStatus = "draft" | "issued" | "partial" | "paid" | "overdue" | "cancelled" | "refunded";
export type Invoice = {
  id: number;
  business_id: number;
  invoice_number: string;
  invoice_date: string;
  due_date?: string | null;
  status: InvoiceStatus;
  customer_name: string;
  customer_phone?: string | null;
  customer_email?: string | null;
  customer_address?: string | null;
  customer_pan_number?: string | null;
  customer?: Pick<Customer, "id" | "name" | "phone" | "email" | "pan_number" | "address"> | null;
  creator?: { id: number; name: string; initials: string };
  subtotal: number;
  discount_amount: number;
  tax_amount: number;
  total_amount: number;
  cost_amount: number | null;
  commission_amount: number | null;
  paid_amount: number;
  balance_amount: number;
  notes?: string | null;
  items: InvoiceItem[];
  payments: Payment[];
  finalized_at?: string | null;
  created_at: string;
};

export type PaymentMethod = "cash" | "bank_transfer" | "qr" | "card" | "wallet" | "cheque" | "other";
export type ExpenseStatus = "pending" | "approved" | "rejected";
export type Expense = {
  id: number;
  business_id: number;
  category: string;
  vendor?: string | null;
  expense_date: string;
  amount: number;
  tax_amount: number;
  payment_method: PaymentMethod;
  status: ExpenseStatus;
  reference?: string | null;
  notes?: string | null;
  submitter?: { id: number; name: string };
  approver?: { id: number; name: string } | null;
  approved_at?: string | null;
  created_at: string;
};

export type Member = {
  id: number;
  business_id: number;
  user_id: number;
  name: string;
  email: string;
  phone?: string | null;
  initials: string;
  role: BusinessRole;
  title?: string | null;
  commission_rate: number;
  active: boolean;
  joined_at?: string | null;
  sales: number;
  invoice_count: number;
  commission_earned: number;
  ownership_percent: number;
  profit_share_percent: number;
};

export type ProfitAllocation = {
  id: number;
  user_id: number;
  name: string;
  effective_profit_share_percent: number;
  allocated_amount: number;
  distributed_amount: number;
  remaining_amount: number;
};

export type ProfitPeriod = {
  id: number;
  business_id: number;
  start_date: string;
  end_date: string;
  status: "closed" | "reopened";
  net_sales: number;
  tax_collected: number;
  cost_of_sales: number;
  gross_profit: number;
  expenses: number;
  commissions: number;
  net_profit: number;
  notes?: string | null;
  closed_at?: string | null;
  closed_by?: { id: number; name: string };
  allocations: ProfitAllocation[];
};

export type ProfitDistribution = {
  id: number;
  profit_allocation_id: number;
  user_id: number;
  user_name?: string;
  distribution_date: string;
  amount: number;
  method: PaymentMethod;
  reference?: string | null;
  notes?: string | null;
  recorded_by?: string;
  created_at: string;
};

export type MetricSummary = {
  net_sales: number;
  invoiced_total: number;
  tax_collected: number;
  cost_of_sales: number;
  gross_profit: number;
  expenses: number;
  commissions: number;
  net_profit: number;
  cash_collected: number;
  receivables: number;
  invoice_count: number;
  average_invoice: number;
  attributable_profit?: number;
  distributed_profit?: number;
  outstanding_profit?: number;
  net_sales_change?: number;
  net_profit_change?: number;
};

export type TrendPoint = {
  label: string;
  month: string;
  net_sales: number;
  net_profit: number;
  cash_collected?: number;
  attributable_profit?: number;
};

export type SellerRow = {
  user_id: number;
  name: string;
  initials: string;
  sales: number;
  commission: number;
  invoice_count: number;
};

export type PortfolioDashboard = {
  period: { start: string; end: string; label: string };
  mode: "owner" | "admin" | "employee";
  can_create_business: boolean;
  reporting_currency: string;
  currencies: string[];
  mixed_currencies: boolean;
  summary: MetricSummary;
  businesses: Array<{
    id: number;
    name: string;
    code: string;
    business_type: string;
    currency: string;
    status: string;
    my_role: BusinessRole;
    ownership_percent: number;
    profit_share_percent: number;
    can_view_financials: boolean;
    metrics: MetricSummary;
    change: { net_sales: number; net_profit: number };
  }>;
  trend: TrendPoint[];
  top_sellers: SellerRow[];
  recent_activity: Array<{ id: number; action: string; label: string; user_name?: string | null; business_name?: string | null; business_code?: string | null; created_at: string }>;
};

export type BusinessDashboard = {
  period: { start: string; end: string; label: string };
  mode: "financial" | "personal";
  business: {
    id: number;
    name: string;
    code: string;
    currency: string;
    business_type: string;
    my_role: BusinessRole;
    ownership_percent: number;
    profit_share_percent: number;
  };
  permissions: Record<string, boolean>;
  summary: MetricSummary;
  trend: TrendPoint[];
  top_sellers: SellerRow[];
  top_products: Array<{ name: string; quantity: number; sales: number }>;
  expense_categories: Array<{ category: string; total: number }>;
  recent_invoices: Array<{ id: number; invoice_number: string; invoice_date: string; customer_name: string; seller_name: string; status: InvoiceStatus; total_amount: number; balance_amount: number }>;
};

export type Paginated<T> = {
  data: T[];
  links: { first?: string | null; last?: string | null; prev?: string | null; next?: string | null };
  meta: {
    current_page: number;
    from?: number | null;
    last_page: number;
    path?: string;
    per_page: number;
    to?: number | null;
    total: number;
  };
};

export type ApiMessage<T extends Record<string, unknown> = Record<string, never>> = {
  message: string;
} & T;

export type OwnershipRecord = {
  id: number;
  user_id: number;
  name: string;
  email: string;
  ownership_percent: number;
  profit_share_percent: number;
  effective_from: string;
  effective_to?: string | null;
  notes?: string | null;
  current: boolean;
};

export type ProfitLossReport = {
  period: { start: string; end: string; label: string };
  business: { id: number; name: string; currency: string };
  report: {
    net_sales: number;
    cost_of_sales: number;
    gross_profit: number;
    expenses: number;
    commissions: number;
    net_profit: number;
    tax_collected: number;
    cash_collected: number;
    receivables: number;
  };
};

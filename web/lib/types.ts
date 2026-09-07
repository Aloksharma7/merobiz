export type User = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  initials: string;
  preferred_currency: string;
  has_signature: boolean;
  signature_url?: string | null;
  workspace?: {
    mode: "portfolio" | "employee" | "writer";
    business_id?: number | null;
    business_name?: string | null;
    business_code?: string | null;
    writer_id?: number | null;
  };
  // Bundled onto /auth/me, /auth/login and /auth/register so the app's initial
  // load (and every login/register) gets the user and their businesses in one
  // round trip — see BusinessProvider, which seeds its own cache from this.
  businesses: Business[];
};

export type BusinessType = "product" | "service" | "digital_subscription" | "mixed";
export type BusinessRole = "owner" | "admin" | "employee";

export type InvoiceBusinessSettings = {
  company_name?: string | null;
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

export type BusinessFeatureSettings = {
  installments?: boolean;
};

export type BusinessSettings = {
  country?: string;
  number_format?: string;
  features?: BusinessFeatureSettings;
  branding?: BusinessBrandingSettings;
  invoice?: InvoiceBusinessSettings;
  dashboard?: Partial<DashboardLayoutSettings>;
  [key: string]: unknown;
};

export type Business = {
  id: number;
  name: string;
  slug: string;
  code: string;
  business_type: BusinessType;
  category: "standard" | "installment";
  product_type: "digital" | "physical" | null;
  is_installment: boolean;
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
  full_control: boolean;
  is_founder: boolean;
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

export type CustomerProjectRow = {
  id: number;
  topic: string;
  course: string;
  work: string;
  work_status: ProjectWorkStatus;
  deal_amount: number;
  collected_amount: number;
  refunded_amount: number;
  due_amount: number;
  writer: { id: number; name: string } | null;
};

export type CustomerInvoiceRow = {
  id: number;
  invoice_number: string;
  invoice_date: string;
  status: InvoiceStatus;
  total_amount: number;
  paid_amount: number;
  balance_amount: number;
};

export type CustomerPaymentRow = {
  id: number;
  project_id: number | null;
  project_topic: string | null;
  amount: number;
  payment_date: string;
  method: string;
  notes: string | null;
};

export type CustomerProjectProfile = {
  customer: Customer;
  stats: {
    total_projects: number;
    completed_projects: number;
    cancelled_projects: number;
    in_progress_projects: number;
    total_deal_amount: number;
    total_collected: number;
    total_refunded: number;
    total_due: number;
  };
  projects: CustomerProjectRow[];
  payments: CustomerPaymentRow[];
};

export type CustomerInvoiceProfile = {
  customer: Customer;
  stats: {
    total_invoices: number;
    total_invoiced: number;
    total_paid: number;
    outstanding: number;
  };
  invoices: CustomerInvoiceRow[];
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
  allows_multiple_writers: boolean;
  created_at: string;
};

export type Writer = {
  id: number;
  business_id: number;
  name: string;
  phone?: string | null;
  email?: string | null;
  notes?: string | null;
  active: boolean;
  has_login: boolean;
  created_at: string;
};

export type WriterProjectRow = {
  id: number;
  client_name: string;
  topic: string;
  course: string;
  work: string;
  work_status: ProjectWorkStatus;
  writer_payment_amount: number;
  writer_paid_amount: number;
  writer_due_amount: number | null;
  is_current: boolean;
  assigned_from?: string | null;
  assigned_to?: string | null;
};

export type WriterPaymentRow = {
  id: number;
  project_id: number;
  client_name?: string | null;
  paid_on: string;
  amount: number;
  notes?: string | null;
};

export type WriterProfile = {
  writer: Writer;
  business: { name: string; currency: string };
  period: { start: string; end: string; label: string };
  stats: {
    total_projects: number;
    current_projects: number;
    total_agreed: number;
    total_paid: number;
    total_due: number;
    period_paid: number;
  };
  payments: WriterPaymentRow[];
  projects: WriterProjectRow[];
};

export type WriterAssignment = {
  id: number;
  writer: { id: number; name: string };
  assigned_from: string;
  assigned_to?: string | null;
  current: boolean;
  notes?: string | null;
};

export type ProjectWorkStatus =
  | "started"
  | "first_draft"
  | "send_draft"
  | "correction_ongoing"
  | "waiting_for_feedback"
  | "submitted"
  | "approved"
  | "cancelled";

export type ProjectInvoiceSummary = {
  id: number;
  invoice_number: string;
  invoice_date: string;
  status: InvoiceStatus;
  total_amount: number;
  paid_amount: number;
  balance_amount: number;
};

export type ProjectProfitApproval = {
  id: number;
  approved_on: string;
  amount: number;
  notes?: string | null;
};

export type ProjectRefund = {
  id: number;
  refunded_on: string;
  amount: number;
  notes?: string | null;
};

export type Project = {
  id: number;
  business_id: number;
  customer_id?: number | null;
  client_name: string;
  client_phone?: string | null;
  client_email?: string | null;
  started_on?: string | null;
  topic: string;
  course: string;
  work: string;
  work_status: ProjectWorkStatus;
  deadline?: string | null;
  deal_amount: number;
  writer_payment_amount: number;
  collected_amount: number;
  due_amount: number;
  refunded_amount: number;
  net_collected_amount: number;
  writer_paid_amount: number;
  writer_due_amount: number;
  approved_profit_total: number | null;
  current_writer: { id: number; name: string; assigned_from: string } | null;
  created_by: number;
  created_at: string;
  invoices?: ProjectInvoiceSummary[];
  profit_approvals?: ProjectProfitApproval[] | null;
  refunds?: ProjectRefund[];
  writer_history?: WriterAssignment[];
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

export type InstallmentStatus = "paid" | "partial" | "overdue" | "pending";
export type InvoiceInstallment = {
  id: number;
  sequence: number;
  due_date: string;
  amount: number;
  paid_amount: number;
  remaining_amount: number;
  status: InstallmentStatus;
  notes?: string | null;
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
  project_id?: number | null;
  project?: { id: number; client_name: string } | null;
  creator?: { id: number; name: string; initials: string; signature_url?: string | null };
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

export type PayType = "commission" | "fixed_salary";
export type Member = {
  id: number;
  business_id: number;
  user_id: number;
  name: string;
  email: string;
  phone?: string | null;
  initials: string;
  role: BusinessRole;
  full_control: boolean;
  is_founder: boolean;
  title?: string | null;
  commission_rate: number;
  active: boolean;
  joined_at?: string | null;
  sales: number;
  invoice_count: number;
  commission_earned: number;
  ownership_percent: number;
  profit_share_percent: number;
  pay_type: PayType;
  salary_amount: number;
  salary_visible_to_staff: boolean;
  salary_paid_total: number;
  salary_pending: number;
  outstanding_loan: number;
};

export type SalaryEntryType = "payment" | "advance" | "loan" | "write_off";

export type SalaryPaymentRecord = {
  id: number;
  payment_date: string;
  amount: number;
  entry_type: SalaryEntryType;
  method: PaymentMethod;
  reference?: string | null;
  notes?: string | null;
  recorded_by?: string | null;
};

export type SalarySummary = {
  pay_type: PayType;
  salary_amount: number;
  salary_visible_to_staff?: boolean;
  months_elapsed: number;
  accrued: number;
  paid_total: number;
  pending: number;
  outstanding_loan: number;
};

export type MySalary = { visible: false; outstanding_loan: number } | ({ visible: true; payments: SalaryPaymentRecord[] } & SalarySummary);

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
  payroll_cost: number;
  writer_cost: number;
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
  payroll_cost: number;
  writer_cost: number;
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

export type AvailableBalance = {
  available_balance: number;
  collected: number;
  refunded: number;
  expenses_paid: number;
  payroll_paid: number;
  writer_paid: number;
  owner_withdrawn: number;
};

export type PortfolioDashboard = {
  period: { start: string; end: string; label: string };
  mode: "owner" | "admin" | "employee";
  can_create_business: boolean;
  reporting_currency: string;
  currencies: string[];
  mixed_currencies: boolean;
  summary: MetricSummary;
  total_available_balance: number;
  month_to_date: { sales: number; profit: number };
  profit_collected_this_month: number;
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
    available_balance: AvailableBalance;
    collected_this_month: number;
    change: { net_sales: number; net_profit: number };
  }>;
  trend: TrendPoint[];
  top_sellers: SellerRow[];
  recent_activity: Array<{ id: number; action: string; label: string; user_name?: string | null; business_name?: string | null; business_code?: string | null; created_at: string }>;
};

export type DashboardLayoutSettings = {
  show_overview_cards: boolean;
  show_profit_breakdown: boolean;
  show_quick_actions: boolean;
  show_performance_trend: boolean;
  show_top_products: boolean;
  show_recent_invoices: boolean;
  show_expense_mix: boolean;
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
    category?: string;
    is_installment: boolean;
    my_role: BusinessRole;
    full_control: boolean;
    is_founder: boolean;
    ownership_percent: number;
    profit_share_percent: number;
    dashboard_settings: DashboardLayoutSettings;
  };
  owners: Array<{ user_id: number; name: string; initials: string; title?: string | null; ownership_percent: number | null; profit_share_percent: number | null }>;
  month_to_date: { sales: number; profit: number };
  profit_collected_this_month: number;
  permissions: Record<string, boolean>;
  summary: MetricSummary;
  available_balance: AvailableBalance;
  trend: TrendPoint[];
  top_sellers: SellerRow[];
  top_products: Array<{ name: string; quantity: number; sales: number }>;
  expense_categories: Array<{ category: string; total: number }>;
  recent_invoices: Array<{ id: number; invoice_number: string; invoice_date: string; customer_name: string; seller_name: string; status: InvoiceStatus; total_amount: number; balance_amount: number }>;
};

export type IncomeSourceType = "bank" | "salary" | "investment" | "other";

export type PersonalIncomeSource = {
  id: number;
  name: string;
  type: IncomeSourceType;
  notes?: string | null;
  active: boolean;
  created_at: string;
};

export type PersonalIncomeEntry = {
  id: number;
  source: { id: number; name: string; type: IncomeSourceType } | null;
  entry_date: string;
  amount: number;
  notes?: string | null;
  created_at: string;
};

export type PersonalExpense = {
  id: number;
  category: string;
  vendor?: string | null;
  expense_date: string;
  amount: number;
  payment_method: PaymentMethod;
  notes?: string | null;
  created_at: string;
};

export type PersonalOverview = {
  period: { start: string; end: string; label: string };
  reporting_currency: string;
  summary: {
    business_profit_received: number;
    other_income: number;
    total_income: number;
    total_expenses: number;
    net_balance: number;
  };
  recent_profit: Array<{ id: number; business_name?: string | null; currency?: string | null; amount: number; withdrawn_on: string }>;
  recent_income: Array<{ id: number; source_name?: string | null; amount: number; entry_date: string }>;
  recent_expenses: Array<{ id: number; category: string; vendor?: string | null; amount: number; expense_date: string }>;
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
    payroll_cost: number;
    writer_cost: number;
    net_profit: number;
    tax_collected: number;
    cash_collected: number;
    receivables: number;
  };
};

"use client";

import { Logo } from "@/components/logo";
import { BusinessMark } from "@/components/business-mark";
import { Button, LinkButton } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useAuth } from "@/lib/auth-context";
import { useBusinesses } from "@/lib/business-context";
import { brandingFor, businessThemeStyle } from "@/lib/branding";
import { cn, humanize } from "@/lib/utils";
import {
  BarChart3,
  Boxes,
  Building2,
  ChevronDown,
  ContactRound,
  FolderKanban,
  Gauge,
  LayoutDashboard,
  LogOut,
  Menu,
  PenTool,
  Plus,
  ReceiptText,
  Settings,
  Sparkles,
  UsersRound,
  Wallet2,
  WalletCards,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { useParams, usePathname, useRouter } from "next/navigation";
import { useEffect, useMemo, useState, type ReactNode } from "react";

type NavItem = { label: string; href: string; icon: LucideIcon; permission?: string; exact?: boolean };

const EMPLOYEE_ALLOWED_PATH_SEGMENTS = ["/sales", "/expenses", "/customers", "/catalog", "/projects", "/writers"];

function isEmployeePathAllowed(pathname: string, base: string) {
  return pathname === base || EMPLOYEE_ALLOWED_PATH_SEGMENTS.some((segment) => pathname.startsWith(`${base}${segment}`));
}

function isActive(pathname: string, item: NavItem) {
  return item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(`${item.href}/`);
}

function NavLink({ item, pathname, onClick }: { item: NavItem; pathname: string; onClick?: () => void }) {
  const active = isActive(pathname, item);
  const Icon = item.icon;
  return (
    <Link
      href={item.href}
      onClick={onClick}
      className={cn(
        "group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition",
        active
          ? "bg-white text-[var(--ink)] shadow-[0_8px_24px_rgb(4_31_23/0.14)]"
          : "text-[var(--on-brand-deep)]/68 hover:bg-white/8 hover:text-[var(--on-brand-deep)]",
      )}
      aria-current={active ? "page" : undefined}
    >
      <Icon size={18} strokeWidth={active ? 2.3 : 1.9} />
      <span>{item.label}</span>
      {active ? <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[var(--accent)]" /> : null}
    </Link>
  );
}

function AuthGate({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !user) router.replace("/login");
  }, [isLoading, router, user]);

  if (isLoading || !user) {
    // Keep the authentication transition completely neutral. We do not know
    // whether this session belongs to a portfolio owner or to a single-business
    // staff member until /auth/me resolves, so no MeroBiz brand is rendered here.
    return (
      <div className="grid min-h-screen place-items-center bg-[var(--canvas)]">
        <div className="flex items-center gap-3 text-sm font-semibold text-[var(--ink-soft)]" role="status" aria-live="polite">
          <span className="h-5 w-5 animate-spin rounded-full border-2 border-[var(--line-strong)] border-t-[var(--brand)]" />
          Opening workspace…
        </div>
      </div>
    );
  }

  return children;
}

export function AppShell({ children }: { children: ReactNode }) {
  return <AuthGate><Shell>{children}</Shell></AuthGate>;
}

function Shell({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { businesses, isLoading: businessesLoading, getBusiness, can } = useBusinesses();
  const pathname = usePathname();
  const params = useParams<{ businessId?: string }>();
  const router = useRouter();
  const [mobileMenu, setMobileMenu] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const businessId = params?.businessId;
  const firstBusiness = businesses[0];
  const employeeWorkspace = user?.workspace?.mode === "employee";
  const assignedBusinessId = user?.workspace?.business_id ?? null;
  const assignedBusiness = assignedBusinessId ? getBusiness(assignedBusinessId) ?? firstBusiness : firstBusiness;
  const activeBusiness = employeeWorkspace ? assignedBusiness : getBusiness(businessId);
  const activeBranding = brandingFor(activeBusiness);
  const businessNavId = employeeWorkspace ? assignedBusinessId?.toString() : businessId;

  useEffect(() => {
    if (businessesLoading || !employeeWorkspace || !assignedBusinessId) return;

    const base = `/b/${assignedBusinessId}`;
    if (!isEmployeePathAllowed(pathname, base)) router.replace(base);
  }, [assignedBusinessId, businessesLoading, employeeWorkspace, pathname, router]);

  useEffect(() => {
    // A writer login has no BusinessMembership at all, so none of this shell's
    // business-scoped pages apply to them — they only ever see their own
    // dedicated read-only dashboard, not the staff/owner app shell.
    if (user?.workspace?.mode === "writer") router.replace("/writer");
  }, [router, user]);

  const portfolioNav: NavItem[] = employeeWorkspace ? [] : [
    { label: "Overview", href: "/", icon: LayoutDashboard, exact: true },
    { label: "Businesses", href: "/businesses", icon: Building2 },
    { label: "Personal", href: "/personal", icon: Wallet2 },
  ];

  const businessNav = useMemo<NavItem[]>(() => {
    if (!businessNavId) return [];
    if (employeeWorkspace) {
      return [
        { label: "Home", href: `/b/${businessNavId}`, icon: Gauge, exact: true },
        { label: "Sales & invoices", href: `/b/${businessNavId}/sales`, icon: ReceiptText, permission: "sales.create" },
        { label: "Expenses", href: `/b/${businessNavId}/expenses`, icon: WalletCards, permission: "expenses.manage" },
        { label: "Customers", href: `/b/${businessNavId}/customers`, icon: ContactRound, permission: "customers.view" },
        { label: "Products & services", href: `/b/${businessNavId}/catalog`, icon: Boxes, permission: "products.view" },
        { label: "Projects", href: `/b/${businessNavId}/projects`, icon: FolderKanban, permission: "writers.manage" },
        { label: "Writers", href: `/b/${businessNavId}/writers`, icon: PenTool, permission: "writers.manage" },
      ];
    }

    return [
      { label: "Dashboard", href: `/b/${businessNavId}`, icon: Gauge, exact: true },
      { label: "Sales & invoices", href: `/b/${businessNavId}/sales`, icon: ReceiptText, permission: "sales.create" },
      { label: "Expenses", href: `/b/${businessNavId}/expenses`, icon: WalletCards, permission: "expenses.manage" },
      { label: "Customers", href: `/b/${businessNavId}/customers`, icon: ContactRound, permission: "customers.view" },
      { label: "Products & services", href: `/b/${businessNavId}/catalog`, icon: Boxes, permission: "products.view" },
      { label: "Projects", href: `/b/${businessNavId}/projects`, icon: FolderKanban, permission: "writers.manage" },
      { label: "Writers", href: `/b/${businessNavId}/writers`, icon: PenTool, permission: "writers.manage" },
      { label: "Team", href: `/b/${businessNavId}/team`, icon: UsersRound, permission: "team.view" },
      { label: "Reports & profit", href: `/b/${businessNavId}/reports`, icon: BarChart3, permission: "reports.view" },
      { label: "Settings", href: `/b/${businessNavId}/settings`, icon: Settings, permission: "business.update" },
    ];
  }, [businessNavId, employeeWorkspace]);

  const visibleBusinessNav = businessNav.filter((item) => {
    if (!item.permission || !activeBusiness) return true;
    if (item.permission === "sales.create") return can(activeBusiness, "sales.create") || can(activeBusiness, "sales.manage") || can(activeBusiness, "sales.view");
    if (item.permission === "expenses.manage") return can(activeBusiness, "expenses.manage") || can(activeBusiness, "expenses.create");
    if (item.permission === "customers.view") return can(activeBusiness, "customers.view") || can(activeBusiness, "customers.manage");
    if (item.permission === "products.view") return (can(activeBusiness, "products.view") || can(activeBusiness, "products.manage")) && !activeBusiness.is_installment;
    if (item.permission === "team.view") return can(activeBusiness, "team.view") || can(activeBusiness, "team.manage");
    if (item.permission === "writers.manage") return can(activeBusiness, "writers.manage") && Boolean(activeBusiness.is_installment);
    return can(activeBusiness, item.permission);
  });

  const pageTitle = (() => {
    if (!employeeWorkspace && pathname === "/") return "Portfolio overview";
    if (!employeeWorkspace && pathname === "/businesses") return "Your businesses";
    if (employeeWorkspace && activeBusiness && pathname === `/b/${activeBusiness.id}`) return activeBusiness.name;
    const current = visibleBusinessNav.find((item) => isActive(pathname, item));
    return current?.label ?? activeBusiness?.name ?? (employeeWorkspace ? "Workspace" : "MeroBiz");
  })();

  useEffect(() => {
    if (employeeWorkspace && activeBusiness) {
      document.title = pathname === `/b/${activeBusiness.id}`
        ? activeBusiness.name
        : `${pageTitle} · ${activeBusiness.name}`;
      return;
    }
    document.title = `${pageTitle} · MeroBiz`;
  }, [activeBusiness, employeeWorkspace, pageTitle, pathname]);

  function switchBusiness(value: string) {
    if (!value) {
      if (!employeeWorkspace) router.push("/");
      return;
    }
    router.push(`/b/${value}`);
  }

  const canCreateSale = Boolean(activeBusiness && (can(activeBusiness, "sales.create") || can(activeBusiness, "sales.manage")));
  const salesHref = canCreateSale && activeBusiness ? `/b/${activeBusiness.id}/sales?new=1` : employeeWorkspace && assignedBusiness ? `/b/${assignedBusiness.id}/sales` : "/businesses";
  const canSeeExpenses = Boolean(activeBusiness && (can(activeBusiness, "expenses.manage") || can(activeBusiness, "expenses.create")));
  const fourthMobileHref = canSeeExpenses && activeBusiness
    ? `/b/${activeBusiness.id}/expenses`
    : activeBusiness
      ? `/b/${activeBusiness.id}/customers`
      : employeeWorkspace && assignedBusiness
        ? `/b/${assignedBusiness.id}/customers`
        : "/businesses";

  const mobileItems = [...portfolioNav, ...visibleBusinessNav];
  const shouldShowSwitcher = !employeeWorkspace && (businesses.length > 1 || Boolean(activeBusiness));

  const employeeBase = assignedBusinessId ? `/b/${assignedBusinessId}` : null;
  const employeePathAllowed = !employeeWorkspace || Boolean(employeeBase && isEmployeePathAllowed(pathname, employeeBase));

  if (businessesLoading || (employeeWorkspace && (!assignedBusiness || !employeePathAllowed))) {
    return <div className="grid min-h-screen place-items-center text-sm font-semibold text-[var(--ink-soft)]">Opening your assigned business…</div>;
  }

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[264px_minmax(0,1fr)]" style={businessThemeStyle(activeBusiness)}>
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[264px] flex-col overflow-hidden bg-[var(--brand-deep)] lg:flex">
        <div className="surface-grid flex h-full flex-col px-4 py-5">
          {employeeWorkspace && activeBusiness ? (
            <div className="flex items-center gap-3 px-2">
              <BusinessMark business={activeBusiness} compact />
              <div className="min-w-0"><p className="truncate text-sm font-black text-[var(--on-brand-deep)]">{activeBusiness.name}</p><p className="truncate text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--on-brand-deep)]/45">{activeBranding.tagline || "Sales workspace"}</p></div>
            </div>
          ) : <Logo inverse className="px-2" />}

          {portfolioNav.length ? (
            <nav className="mt-8 space-y-1" aria-label="Portfolio navigation">
              <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--on-brand-deep)]/35">Portfolio</p>
              {portfolioNav.map((item) => <NavLink key={item.href} item={item} pathname={pathname} />)}
            </nav>
          ) : null}

          {activeBusiness ? (
            <nav className={`${portfolioNav.length ? "mt-7" : "mt-8"} min-h-0 flex-1 overflow-y-auto pb-4 scrollbar-thin`} aria-label={`${activeBusiness.name} navigation`}>
              <div className="mb-2 flex items-center justify-between px-3">
                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--on-brand-deep)]/35">{employeeWorkspace ? "Workspace" : "Current business"}</p>
                <span className="rounded-md bg-white/8 px-1.5 py-0.5 text-[9px] font-bold text-[var(--on-brand-deep)]/50">{activeBusiness.code}</span>
              </div>
              <div className="space-y-1">{visibleBusinessNav.map((item) => <NavLink key={item.href} item={item} pathname={pathname} />)}</div>
            </nav>
          ) : (
            <div className="mt-7 flex-1 rounded-2xl border border-white/10 bg-white/5 p-4">
              <Sparkles className="text-[var(--accent)]" size={20} />
              <p className="mt-3 text-sm font-semibold text-[var(--on-brand-deep)]">Everything in one place</p>
              <p className="mt-1 text-xs leading-5 text-[var(--on-brand-deep)]/55">Switch between businesses without mixing their records.</p>
            </div>
          )}

          <button type="button" onClick={() => setProfileOpen(true)} className="mt-auto flex w-full items-center gap-3 rounded-2xl border border-white/10 bg-white/6 p-3 text-left transition hover:bg-white/10">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--accent)] text-sm font-black text-[var(--on-accent)]">{user?.initials}</span>
            <span className="min-w-0 flex-1"><span className="block truncate text-sm font-bold text-[var(--on-brand-deep)]">{user?.name}</span><span className="block truncate text-xs text-[var(--on-brand-deep)]/50">{employeeWorkspace ? activeBusiness?.name : user?.email}</span></span>
            <ChevronDown size={16} className="text-[var(--on-brand-deep)]/45" />
          </button>
        </div>
      </aside>

      <div className="min-w-0 lg:col-start-2">
        <header className="sticky top-0 z-30 border-b border-[rgb(213_222_214/0.75)] bg-[rgb(247_248_244/0.86)] backdrop-blur-xl">
          <div className="flex h-[72px] items-center gap-3 px-4 sm:px-6 lg:px-8">
            <div className="lg:hidden">
              {employeeWorkspace && activeBusiness ? <BusinessMark business={activeBusiness} compact /> : <Logo compact />}
            </div>
            <div className="min-w-0 flex-1">
              <p className="hidden text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--ink-soft)] sm:block">{activeBusiness ? humanize(activeBusiness.business_type) : "All businesses"}</p>
              <h2 className="truncate text-sm font-extrabold tracking-[-0.01em] sm:text-base">{pageTitle}</h2>
            </div>

            {shouldShowSwitcher ? (
              <label className="relative hidden sm:block">
                <span className="sr-only">Switch business</span>
                <Building2 size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" />
                <select value={activeBusiness?.id ?? ""} onChange={(event) => switchBusiness(event.target.value)} className="h-10 max-w-[230px] appearance-none rounded-xl border border-[var(--line)] bg-white py-0 pl-9 pr-9 text-sm font-semibold text-[var(--ink)] shadow-sm focus:border-[var(--brand)] focus:outline-none">
                  <option value="">All businesses</option>
                  {businesses.map((business) => <option value={business.id} key={business.id}>{business.name}</option>)}
                </select>
                <ChevronDown size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" />
              </label>
            ) : null}

            {canCreateSale ? <LinkButton href={salesHref} size="sm" leftIcon={<Plus size={17} />} className="hidden sm:inline-flex">New sale</LinkButton> : null}
            <button type="button" onClick={() => setMobileMenu(true)} className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--line)] bg-white text-[var(--ink)] shadow-sm lg:hidden" aria-label="Open navigation"><Menu size={20} /></button>
          </div>
        </header>

        <main className="app-content-enter mx-auto w-full max-w-[1600px] px-4 pb-28 pt-6 sm:px-6 sm:pt-8 lg:px-8 lg:pb-10">{children}</main>
      </div>

      <nav className="safe-bottom fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-[var(--line)] bg-white/96 px-1 pt-2 shadow-[0_-12px_30px_rgb(17_39_29/0.08)] backdrop-blur-xl lg:hidden" aria-label="Mobile navigation">
        {employeeWorkspace && activeBusiness ? <>
          <MobileNavLink href={`/b/${activeBusiness.id}`} icon={LayoutDashboard} label="Home" pathname={pathname} exact />
          <MobileNavLink href={`/b/${activeBusiness.id}/sales`} icon={ReceiptText} label="My sales" pathname={pathname} />
          <Link href={salesHref} className="relative -mt-6 flex flex-col items-center gap-1 text-[10px] font-bold text-[var(--brand)]" aria-label="Create new sale"><span className="grid h-14 w-14 place-items-center rounded-[18px] border-4 border-[var(--canvas)] bg-[var(--brand)] text-[var(--on-brand)] shadow-[0_12px_24px_rgb(19_95_72/0.3)]"><Plus size={25} /></span><span>New</span></Link>
          <MobileNavLink href={`/b/${activeBusiness.id}/customers`} icon={ContactRound} label="Customers" pathname={pathname} />
          <MobileNavLink href={`/b/${activeBusiness.id}/catalog`} icon={Boxes} label="Products" pathname={pathname} />
        </> : <>
          <MobileNavLink href={activeBusiness ? `/b/${activeBusiness.id}` : "/"} icon={LayoutDashboard} label="Home" pathname={pathname} exact />
          <MobileNavLink href={activeBusiness ? `/b/${activeBusiness.id}/sales` : salesHref} icon={ReceiptText} label="Sales" pathname={pathname} />
          {canCreateSale ? <Link href={salesHref} className="relative -mt-6 flex flex-col items-center gap-1 text-[10px] font-bold text-[var(--brand)]" aria-label="Create new sale"><span className="grid h-14 w-14 place-items-center rounded-[18px] border-4 border-[var(--canvas)] bg-[var(--brand)] text-[var(--on-brand)] shadow-[0_12px_24px_rgb(19_95_72/0.3)]"><Plus size={25} /></span><span>New</span></Link> : <MobileNavLink href="/businesses" icon={Building2} label="Business" pathname={pathname} />}
          <MobileNavLink href={fourthMobileHref} icon={canSeeExpenses ? WalletCards : ContactRound} label={canSeeExpenses ? "Expenses" : "Customers"} pathname={pathname} />
          <button type="button" onClick={() => setMobileMenu(true)} className="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[10px] font-semibold text-[var(--ink-soft)]"><Menu size={20} /><span>More</span></button>
        </>}
      </nav>

      <Modal open={mobileMenu} onClose={() => setMobileMenu(false)} title="Navigate" description={activeBusiness ? activeBusiness.name : "Your business workspace"} size="sm">
        <div className="space-y-6">
          {shouldShowSwitcher ? (
            <div>
              <p className="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--ink-soft)]">Switch business</p>
              <select value={activeBusiness?.id ?? ""} onChange={(event) => { switchBusiness(event.target.value); setMobileMenu(false); }} className="h-12 w-full rounded-xl border border-[var(--line-strong)] bg-white px-3 font-semibold">
                <option value="">All businesses</option>
                {businesses.map((business) => <option value={business.id} key={business.id}>{business.name}</option>)}
              </select>
            </div>
          ) : null}
          <div className="grid gap-2">
            {mobileItems.map((item) => { const Icon = item.icon; return <Link key={item.href} href={item.href} onClick={() => setMobileMenu(false)} className={cn("flex min-h-12 items-center gap-3 rounded-xl border px-3.5 text-sm font-semibold", isActive(pathname, item) ? "border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--brand-deep)]" : "border-[var(--line)] bg-white")}><Icon size={18} />{item.label}</Link>; })}
          </div>
          <div className="border-t border-[var(--line)] pt-4">
            <div className="flex items-center gap-3 rounded-2xl bg-[var(--surface-soft)] p-3.5"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--brand)] text-sm font-black text-[var(--on-brand)]">{user?.initials}</span><div className="min-w-0"><p className="truncate text-sm font-bold">{user?.name}</p><p className="truncate text-xs text-[var(--ink-soft)]">{user?.email}</p></div></div>
            <Button className="mt-3 w-full" variant="secondary" leftIcon={<LogOut size={17} />} onClick={() => { setMobileMenu(false); void logout(); }}>Sign out</Button>
          </div>
        </div>
      </Modal>

      <Modal open={profileOpen} onClose={() => setProfileOpen(false)} title="Your account" description={employeeWorkspace && activeBusiness ? `Assigned to ${activeBusiness.name}` : "Signed in to MeroBiz"} size="sm">
        <div className="flex items-center gap-4 rounded-2xl bg-[var(--surface-soft)] p-4"><span className="grid h-12 w-12 place-items-center rounded-2xl bg-[var(--brand)] font-black text-[var(--on-brand)]">{user?.initials}</span><div className="min-w-0"><p className="truncate font-bold">{user?.name}</p><p className="truncate text-sm text-[var(--ink-soft)]">{user?.email}</p></div></div>
        <Button className="mt-4 w-full" variant="secondary" leftIcon={<LogOut size={17} />} onClick={() => void logout()}>Sign out</Button>
      </Modal>
    </div>
  );
}

function MobileNavLink({ href, icon: Icon, label, pathname, exact = false }: { href: string; icon: LucideIcon; label: string; pathname: string; exact?: boolean }) {
  const active = exact ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
  return <Link href={href} className={cn("flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[10px] font-semibold", active ? "text-[var(--brand)]" : "text-[var(--ink-soft)]")} aria-current={active ? "page" : undefined}><Icon size={20} strokeWidth={active ? 2.5 : 2} /><span>{label}</span></Link>;
}

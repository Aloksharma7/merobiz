export function PageLoading() {
  return (
    <div className="space-y-6" aria-label="Loading page">
      <div className="space-y-3">
        <div className="skeleton h-4 w-24 rounded-full" />
        <div className="skeleton h-9 w-72 max-w-full rounded-xl" />
      </div>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {[0, 1, 2, 3].map((key) => <div key={key} className="skeleton h-32 rounded-[var(--radius)]" />)}
      </div>
      <div className="skeleton h-96 rounded-[var(--radius)]" />
    </div>
  );
}

export function TableLoading({ rows = 6 }: { rows?: number }) {
  return (
    <div className="space-y-3 p-5">
      {Array.from({ length: rows }).map((_, index) => <div key={index} className="skeleton h-12 rounded-xl" />)}
    </div>
  );
}

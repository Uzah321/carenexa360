export function Logo({ className = "", compact = false }: { className?: string; compact?: boolean }) {
  return (
    <div className={`flex items-center gap-2 ${className}`}>
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-teal text-base font-bold text-white">
        C
      </span>
      {!compact && (
        <span className="font-display text-lg font-bold tracking-tight text-ink">
          Care<span className="text-teal">Nexa</span>
          <span className="text-inksoft">360</span>
        </span>
      )}
    </div>
  );
}

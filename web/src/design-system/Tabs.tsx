export interface TabItem {
  key: string;
  label: string;
}

interface TabsProps {
  items: TabItem[];
  activeKey: string;
  onChange: (key: string) => void;
}

export function Tabs({ items, activeKey, onChange }: TabsProps) {
  return (
    <div className="rounded-2xl border border-line bg-white px-2">
      <nav className="flex gap-2 overflow-x-auto" aria-label="Tabs">
        {items.map((item) => {
          const isActive = item.key === activeKey;
          return (
            <button
              key={item.key}
              type="button"
              onClick={() => onChange(item.key)}
              className={`shrink-0 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 ${
                isActive
                  ? "border-teal text-teal"
                  : "border-transparent text-inksoft hover:text-ink"
              }`}
            >
              {item.label}
            </button>
          );
        })}
      </nav>
    </div>
  );
}

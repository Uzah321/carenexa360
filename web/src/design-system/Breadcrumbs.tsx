import { Link } from "react-router-dom";

export interface Crumb {
  label: string;
  to?: string;
}

export function Breadcrumbs({ items }: { items: Crumb[] }) {
  return (
    <nav className="flex text-sm text-inksoft" aria-label="Breadcrumb">
      {items.map((item, index) => (
        <span key={`${item.label}-${index}`} className="flex items-center">
          {index > 0 && <span className="mx-1.5 text-line">/</span>}
          {item.to ? (
            <Link to={item.to} className="transition-colors duration-150 hover:text-teal">
              {item.label}
            </Link>
          ) : (
            <span>{item.label}</span>
          )}
        </span>
      ))}
    </nav>
  );
}

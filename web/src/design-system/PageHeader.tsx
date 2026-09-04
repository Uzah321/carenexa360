import type { ReactNode } from "react";
import { Breadcrumbs, type Crumb } from "./Breadcrumbs";

interface PageHeaderProps {
  title: string;
  description?: string;
  breadcrumbs?: Crumb[];
  actions?: ReactNode;
}

export function PageHeader({ title, description, breadcrumbs, actions }: PageHeaderProps) {
  return (
    <div className="mb-6 rounded-2xl border border-line bg-white px-4 py-4 sm:px-5">
      {breadcrumbs && <Breadcrumbs items={breadcrumbs} />}
      <div className="mt-1 flex flex-col items-start gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div>
          <h1 className="font-display text-xl font-bold tracking-tight text-ink sm:text-2xl">{title}</h1>
          {description && <p className="mt-1 text-sm text-inksoft">{description}</p>}
        </div>
        {actions && <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0">{actions}</div>}
      </div>
    </div>
  );
}

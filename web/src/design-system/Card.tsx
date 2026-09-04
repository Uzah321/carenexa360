import type { HTMLAttributes, ReactNode } from "react";

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
}

export function Card({ children, className = "", ...rest }: CardProps) {
  return (
    <div
      className={`overflow-hidden rounded-2xl border border-line bg-white shadow-sm ${className}`}
      {...rest}
    >
      {children}
    </div>
  );
}

export function CardHeader({ children }: { children: ReactNode }) {
  return <div className="border-b border-line px-4 py-3">{children}</div>;
}

export function CardBody({ children }: { children: ReactNode }) {
  return <div className="px-4 py-4">{children}</div>;
}

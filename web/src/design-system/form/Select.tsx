import { forwardRef, type SelectHTMLAttributes } from "react";

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
  function Select({ className = "", children, ...rest }, ref) {
    return (
      <select
        ref={ref}
        className={`block w-full rounded-lg border border-line px-3 py-2 text-sm shadow-sm transition-colors duration-150 focus:border-teal focus:outline-none focus:ring-1 focus:ring-teal ${className}`}
        {...rest}
      >
        {children}
      </select>
    );
  },
);

import { forwardRef, type InputHTMLAttributes } from "react";

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  function Input({ className = "", ...rest }, ref) {
    return (
      <input
        ref={ref}
        className={`block w-full rounded-lg border border-line px-3 py-2 text-sm shadow-sm transition-colors duration-150 focus:border-teal focus:outline-none focus:ring-1 focus:ring-teal ${className}`}
        {...rest}
      />
    );
  },
);

import { forwardRef, type TextareaHTMLAttributes } from "react";

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(
  function Textarea({ className = "", ...rest }, ref) {
    return (
      <textarea
        ref={ref}
        className={`block w-full rounded-lg border border-line px-3 py-2 text-sm shadow-sm transition-colors duration-150 focus:border-teal focus:outline-none focus:ring-1 focus:ring-teal ${className}`}
        {...rest}
      />
    );
  },
);

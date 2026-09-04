import { forwardRef, type InputHTMLAttributes } from "react";

interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  function Checkbox({ label, className = "", id, ...rest }, ref) {
    return (
      <label htmlFor={id} className="flex items-center gap-2 text-sm text-ink">
        <input
          ref={ref}
          id={id}
          type="checkbox"
          className={`h-4 w-4 rounded border-line text-teal transition-colors duration-150 focus:ring-teal ${className}`}
          {...rest}
        />
        {label}
      </label>
    );
  },
);

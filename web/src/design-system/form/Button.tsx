import { forwardRef, type ButtonHTMLAttributes } from "react";

type Variant = "primary" | "secondary" | "danger" | "ghost";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  isLoading?: boolean;
}

const VARIANT_CLASSES: Record<Variant, string> = {
  primary: "bg-teal text-white hover:bg-teal/90 focus-visible:outline-teal",
  secondary:
    "bg-white text-ink ring-1 ring-inset ring-line hover:bg-paper focus-visible:outline-teal",
  danger: "bg-coral text-white hover:bg-coral/90 focus-visible:outline-coral",
  ghost: "text-inksoft hover:bg-paper focus-visible:outline-teal",
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = "primary", isLoading, className = "", children, disabled, ...rest },
  ref,
) {
  return (
    <button
      ref={ref}
      disabled={disabled || isLoading}
      // whitespace-nowrap + shrink-0: a button label is a single action, so it
      // should never wrap onto two lines when the row it sits in gets tight —
      // the row wraps instead.
      className={`inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold shadow-sm transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.98] ${VARIANT_CLASSES[variant]} ${className}`}
      {...rest}
    >
      {isLoading ? "…" : children}
    </button>
  );
});

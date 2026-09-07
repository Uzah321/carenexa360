import { useState, type FormEvent } from "react";
import { Building2, Eye, EyeOff, Lock, Mail, Plus, ShieldCheck, User as UserIcon } from "lucide-react";
import { Link, Navigate } from "react-router-dom";
import { getDefaultRouteFor, useAuth, type RegisterInput } from "../../../lib/auth-context";
import { Button } from "../../../design-system";
import { Alert } from "../../../design-system/Alert";
import { Logo } from "../../../design-system/Logo";
import { CareTeamIllustration } from "../components/CareTeamIllustration";

function errorMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? "Something went wrong. Please try again.";
}

const INITIAL_FORM: RegisterInput = {
  organization_name: "",
  country: "",
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
};

export function RegisterPage() {
  const { user, isLoading, register } = useAuth();
  const [form, setForm] = useState<RegisterInput>(INITIAL_FORM);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isLoading && user) {
    return <Navigate to={getDefaultRouteFor(user)} replace />;
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await register(form);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="relative flex min-h-screen items-center overflow-hidden bg-paper py-12">
      {/* Decorative background: corner patterns + bottom wave, all clipped to the viewport */}
      <div aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden">
        <div
          className="absolute -top-6 -left-6 h-40 w-40 opacity-40"
          style={{
            backgroundImage: "radial-gradient(var(--teal) 1.5px, transparent 1.5px)",
            backgroundSize: "16px 16px",
            maskImage: "radial-gradient(circle at top left, black, transparent 75%)",
          }}
        />
        <Plus className="absolute top-16 left-10 h-8 w-8 text-teal/25" strokeWidth={2.5} />

        <div
          className="absolute -top-6 -right-6 h-56 w-56"
          style={{
            backgroundImage:
              "linear-gradient(var(--teal) 1px, transparent 1px), linear-gradient(90deg, var(--teal) 1px, transparent 1px)",
            backgroundSize: "20px 20px",
            opacity: 0.18,
            maskImage: "radial-gradient(circle at top right, black, transparent 70%)",
          }}
        />

        <div
          className="absolute top-1/3 -left-24 h-[28rem] w-[28rem] rounded-full opacity-60 blur-3xl"
          style={{ background: "radial-gradient(circle, var(--tealtint), transparent 70%)" }}
        />

        <svg
          className="absolute bottom-0 left-0 h-40 w-full text-teal/90"
          viewBox="0 0 1200 200"
          preserveAspectRatio="none"
          fill="currentColor"
        >
          <path
            opacity="0.15"
            d="M0,120 C200,180 400,40 650,90 C850,130 1000,60 1200,100 L1200,200 L0,200 Z"
          />
          <path
            opacity="0.9"
            d="M0,150 C220,210 380,90 620,130 C840,165 1020,100 1200,140 L1200,200 L0,200 Z"
          />
        </svg>
      </div>

      <div className="relative mx-auto grid w-full max-w-6xl gap-16 px-6 lg:grid-cols-2 lg:items-center">
        <div className="hidden lg:block">
          <Link to="/" className="block w-fit">
            <Logo />
          </Link>

          <span className="mt-8 inline-flex w-fit items-center gap-1.5 rounded-full border border-teal/30 bg-tealtint px-3 py-1 text-xs font-semibold uppercase tracking-wide text-teal">
            <span className="h-1.5 w-1.5 rounded-full bg-teal" />
            Multi-Tenant Care Management Platform
          </span>

          <h1 className="mt-6 max-w-md font-display text-4xl font-bold leading-[1.15] tracking-tight text-ink">
            Set up your agency,
            <br />
            in minutes
          </h1>
          <p className="mt-4 max-w-sm text-inksoft">
            No IT project, no lengthy rollout — create your organization and start scheduling
            care today.
          </p>

          <CareTeamIllustration className="mt-10 w-full max-w-md" />
        </div>

        <div className="mx-auto w-full max-w-md animate-fade-in">
          <div className="mb-6 flex justify-center lg:hidden">
            <Link to="/">
              <Logo />
            </Link>
          </div>

          <div className="rounded-3xl border border-line bg-white p-8 shadow-xl animate-panel-in">
            <div className="hidden justify-center lg:flex">
              <Logo />
            </div>

            <div className="mt-6 text-center">
              <h2 className="font-display text-2xl font-bold tracking-tight text-ink">
                Create your account
              </h2>
              <p className="mt-1.5 text-sm text-inksoft">
                Get started with CareNexa360 — free trial, no card required
              </p>
            </div>

            <form onSubmit={handleSubmit} className="mt-7">
              {error && (
                <div className="mb-4">
                  <Alert tone="danger">{error}</Alert>
                </div>
              )}

              <label htmlFor="organization_name" className="mb-1.5 block text-sm font-medium text-ink">
                Organization name
              </label>
              <div className="relative mb-4">
                <Building2 className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="organization_name"
                  type="text"
                  autoComplete="organization"
                  required
                  placeholder="e.g. Riverside Home Care"
                  value={form.organization_name}
                  onChange={(e) => setForm({ ...form, organization_name: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 pr-3.5 pl-10 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
              </div>

              <label htmlFor="country" className="mb-1.5 block text-sm font-medium text-ink">
                Country
              </label>
              <div className="relative mb-4">
                <input
                  id="country"
                  type="text"
                  autoComplete="country-name"
                  required
                  placeholder="e.g. United Kingdom"
                  value={form.country}
                  onChange={(e) => setForm({ ...form, country: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 px-3.5 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
              </div>

              <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-ink">
                Your name
              </label>
              <div className="relative mb-4">
                <UserIcon className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="name"
                  type="text"
                  autoComplete="name"
                  required
                  placeholder="Enter your full name"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 pr-3.5 pl-10 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
              </div>

              <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-ink">
                Work email
              </label>
              <div className="relative mb-4">
                <Mail className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="email"
                  type="email"
                  autoComplete="username"
                  required
                  placeholder="Enter your work email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 pr-3.5 pl-10 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
              </div>

              <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-ink">
                Password
              </label>
              <div className="relative mb-4">
                <Lock className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  required
                  minLength={8}
                  placeholder="At least 8 characters"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 pr-10 pl-10 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  className="absolute top-1/2 right-3 -translate-y-1/2 text-inksoft hover:text-ink"
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>

              <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-ink">
                Confirm password
              </label>
              <div className="relative mb-5">
                <Lock className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="password_confirmation"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  required
                  minLength={8}
                  placeholder="Re-enter your password"
                  value={form.password_confirmation}
                  onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                  className="w-full rounded-xl border border-line bg-white py-2.5 pr-3.5 pl-10 text-sm text-ink placeholder:text-inksoft/70 focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/30"
                />
              </div>

              <Button type="submit" className="w-full" isLoading={isSubmitting}>
                Create account
              </Button>

              <p className="mt-4 text-center text-sm text-inksoft">
                Already have an account?{" "}
                <Link to="/login" className="font-medium text-teal hover:text-teal/80">
                  Sign in
                </Link>
              </p>
            </form>

            <div className="mt-6 flex items-center gap-3 text-xs text-inksoft">
              <span className="h-px flex-1 bg-line" />
              <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
                <ShieldCheck className="h-3.5 w-3.5 text-teal" />
                Your data stays isolated to your organization
              </span>
              <span className="h-px flex-1 bg-line" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

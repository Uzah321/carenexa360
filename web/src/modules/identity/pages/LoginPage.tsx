import { useState, type FormEvent } from "react";
import { Eye, EyeOff, Lock, Mail, Plus, ShieldCheck } from "lucide-react";
import { Link, Navigate } from "react-router-dom";
import { getDefaultRouteFor, useAuth } from "../../../lib/auth-context";
import { Button } from "../../../design-system";
import { Alert } from "../../../design-system/Alert";
import { Logo } from "../../../design-system/Logo";
import { CareTeamIllustration } from "../components/CareTeamIllustration";

export function LoginPage() {
  const { user, isLoading, login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [forgotPasswordHint, setForgotPasswordHint] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isLoading && user) {
    return <Navigate to={getDefaultRouteFor(user)} replace />;
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await login(email, password);
    } catch {
      setError("Invalid email or password.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="relative flex min-h-screen items-center overflow-hidden bg-paper">
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

      <div className="relative mx-auto grid w-full max-w-6xl gap-16 px-6 py-12 lg:grid-cols-2 lg:items-center">
        <div className="hidden lg:block">
          <Link to="/" className="block w-fit">
            <Logo />
          </Link>

          <span className="mt-8 inline-flex w-fit items-center gap-1.5 rounded-full border border-teal/30 bg-tealtint px-3 py-1 text-xs font-semibold uppercase tracking-wide text-teal">
            <span className="h-1.5 w-1.5 rounded-full bg-teal" />
            Care Management Platform
          </span>

          <h1 className="mt-6 max-w-md font-display text-4xl font-bold leading-[1.15] tracking-tight text-ink">
            Connected care,
            <br />
            simplified
          </h1>
          <p className="mt-4 max-w-sm text-inksoft">
            CareNexa360 empowers care teams to collaborate, coordinate, and deliver better
            outcomes—every day.
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
                Welcome back
              </h2>
              <p className="mt-1.5 text-sm text-inksoft">
                Sign in to access your care management dashboard
              </p>
            </div>

            <form onSubmit={handleSubmit} className="mt-7">
              {error && (
                <div className="mb-4">
                  <Alert tone="danger">{error}</Alert>
                </div>
              )}

              <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-ink">
                Email
              </label>
              <div className="relative mb-4">
                <Mail className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-inksoft" />
                <input
                  id="email"
                  type="email"
                  autoComplete="username"
                  required
                  placeholder="Enter your email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
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
                  autoComplete="current-password"
                  required
                  placeholder="Enter your password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
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

              <div className="mb-5 flex items-center justify-between text-sm">
                <label className="flex items-center gap-2 text-ink">
                  <input
                    type="checkbox"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    className="h-4 w-4 rounded border-line text-teal focus:ring-2 focus:ring-teal/30"
                  />
                  Remember me
                </label>
                <button
                  type="button"
                  onClick={() => setForgotPasswordHint(true)}
                  className="font-medium text-teal hover:text-teal/80"
                >
                  Forgot password?
                </button>
              </div>

              {forgotPasswordHint && (
                <p className="mb-4 -mt-2 text-xs text-inksoft">
                  Contact your organization administrator to reset your password.
                </p>
              )}

              <Button type="submit" className="w-full" isLoading={isSubmitting}>
                Sign in
              </Button>
            </form>

            <div className="mt-6 flex items-center gap-3 text-xs text-inksoft">
              <span className="h-px flex-1 bg-line" />
              <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
                <ShieldCheck className="h-3.5 w-3.5 text-teal" />
                Secure access for authorized staff only
              </span>
              <span className="h-px flex-1 bg-line" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

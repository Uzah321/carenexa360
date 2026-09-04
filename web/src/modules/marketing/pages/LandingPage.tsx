import {
  Calendar,
  ClipboardList,
  FileBarChart,
  HeartPulse,
  MapPin,
  Pill,
  ShieldCheck,
  Users,
} from "lucide-react";
import { Link } from "react-router-dom";
import { Logo } from "../../../design-system/Logo";

const NAV_LINKS = [
  { href: "#modules", label: "Modules" },
  { href: "#how-it-works", label: "How It Works" },
  { href: "#why-us", label: "Why Us" },
];

const MODULES = [
  {
    icon: Calendar,
    tone: "teal" as const,
    title: "Scheduling & Rostering",
    description: "Drag-and-drop visit planning with live conflict and skills-matching checks.",
  },
  {
    icon: HeartPulse,
    tone: "coral" as const,
    title: "Care Plans",
    description: "Versioned plans with risk assessments, goals, and full review history.",
  },
  {
    icon: Pill,
    tone: "sky" as const,
    title: "Medications",
    description: "MAR tracking with controlled-drug witnessing and out-of-range alerts.",
  },
  {
    icon: MapPin,
    tone: "lime" as const,
    title: "Live Carer Tracking",
    description: "Real-time GPS visibility into every visit, branch by branch.",
  },
  {
    icon: ShieldCheck,
    tone: "amber" as const,
    title: "Compliance & Safeguarding",
    description: "Requirements, cases, and audit trails your regulator will thank you for.",
  },
  {
    icon: Users,
    tone: "plum" as const,
    title: "Family Portal",
    description: "A dedicated, restricted view for relatives to follow their loved one's care.",
  },
  {
    icon: ClipboardList,
    tone: "teal" as const,
    title: "HR, Payroll & Training",
    description: "Leave, pay periods, and mandatory training in one staff record.",
  },
  {
    icon: FileBarChart,
    tone: "coral" as const,
    title: "Reporting",
    description: "An 80-report library across care, workforce, finance, and compliance.",
  },
];

const STEPS = [
  {
    number: "01",
    title: "Set up your organization",
    description: "Branches, staff roles, and service users — imported or added in minutes, not a lengthy IT project.",
  },
  {
    number: "02",
    title: "Schedule and deliver care",
    description: "Carers check in and out, tick off tasks and medications, and log observations from the visit itself.",
  },
  {
    number: "03",
    title: "Track, report, and improve",
    description: "Owners get live trends and risk posture; auditors and funders get the reports they need, on demand.",
  },
];

const TONE_CLASSES: Record<string, string> = {
  teal: "bg-tealtint text-teal",
  coral: "bg-coraltint text-coral",
  sky: "bg-skytint text-sky",
  lime: "bg-limetint text-lime",
  amber: "bg-ambertint text-amber",
  plum: "bg-plum/10 text-plum",
};

const PRIMARY_LINK =
  "inline-flex items-center justify-center gap-1.5 rounded-full bg-teal px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-teal/90 active:scale-[0.98]";
const SECONDARY_LINK =
  "inline-flex items-center justify-center gap-1.5 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink shadow-sm ring-1 ring-inset ring-line transition-colors duration-150 hover:bg-paper active:scale-[0.98]";

export function LandingPage() {
  return (
    <div className="min-h-screen bg-paper">
      <header className="sticky top-0 z-20 border-b border-line bg-white/80 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <Logo />
          <nav className="hidden items-center gap-8 md:flex">
            {NAV_LINKS.map((link) => (
              <a
                key={link.href}
                href={link.href}
                className="text-sm font-medium text-inksoft transition-colors hover:text-ink"
              >
                {link.label}
              </a>
            ))}
          </nav>
          <div className="flex items-center gap-3">
            <Link to="/login" className="text-sm font-semibold text-ink hover:text-teal">
              Log In
            </Link>
            <Link to="/login" className={PRIMARY_LINK}>
              Get Started
            </Link>
          </div>
        </div>
      </header>

      <main>
        <section className="relative overflow-hidden">
          <div
            aria-hidden
            className="pointer-events-none absolute -top-24 right-0 h-[32rem] w-[32rem] rounded-full bg-tealtint opacity-60 blur-3xl"
          />
          <div
            aria-hidden
            className="pointer-events-none absolute -bottom-32 left-0 h-96 w-96 rounded-full bg-skytint opacity-50 blur-3xl"
          />

          <div className="relative mx-auto grid max-w-7xl gap-12 px-6 py-20 lg:grid-cols-[1fr_25rem] lg:items-center lg:py-28">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full bg-tealtint px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-teal">
                <span className="h-1.5 w-1.5 rounded-full bg-teal" />
                Multi-Tenant Care Management Platform
              </span>

              <h1 className="mt-6 font-display text-5xl font-bold leading-[1.05] tracking-tight text-ink sm:text-6xl">
                Home care,
                <br />
                <span className="text-teal">fully coordinated.</span>
              </h1>

              <p className="mt-6 max-w-xl text-lg leading-relaxed text-inksoft">
                CareNexa360 brings scheduling, care plans, medications, and compliance into one
                platform for home care agencies — with live carer tracking and a family portal
                built in from day one.
              </p>

              <div className="mt-8 flex flex-wrap items-center gap-4">
                <Link to="/login" className={PRIMARY_LINK}>
                  Sign In →
                </Link>
                <a
                  href="#how-it-works"
                  className="text-sm font-semibold text-ink hover:text-teal"
                >
                  See How It Works
                </a>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="col-span-2 rounded-2xl border-t-4 border-teal bg-white p-5 shadow-sm">
                <div className="text-xs font-semibold uppercase tracking-wide text-teal">
                  Care Modules
                </div>
                <div className="mt-1 text-4xl font-extrabold text-ink">12+</div>
                <div className="mt-1 text-sm text-inksoft">
                  Scheduling to safeguarding, in one platform
                </div>
              </div>

              <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line">
                <div className="text-xs font-semibold uppercase tracking-wide text-inksoft">
                  Report Types
                </div>
                <div className="mt-1 text-3xl font-extrabold text-ink">80+</div>
                <div className="mt-1 text-xs text-inksoft">Audit-ready, on demand</div>
              </div>

              <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line">
                <div className="text-xs font-semibold uppercase tracking-wide text-inksoft">
                  Carer Tracking
                </div>
                <div className="mt-1 text-3xl font-extrabold text-ink">Live</div>
                <div className="mt-1 text-xs text-inksoft">Real-time, per visit</div>
              </div>

              <div className="col-span-2 rounded-2xl border-t-4 border-lime bg-white p-5 shadow-sm">
                <div className="text-xs font-semibold uppercase tracking-wide text-lime">
                  Setup Overhead
                </div>
                <div className="mt-1 text-4xl font-extrabold text-ink">Zero</div>
                <div className="mt-1 text-sm text-inksoft">No IT project, no lengthy rollout</div>
              </div>
            </div>
          </div>
        </section>

        <section id="modules" className="mx-auto max-w-7xl px-6 py-20">
          <div className="max-w-2xl">
            <h2 className="font-display text-3xl font-bold tracking-tight text-ink sm:text-4xl">
              Everything a care agency needs, in one place.
            </h2>
            <p className="mt-3 text-lg text-inksoft">
              Built module by module for how home care actually runs — not bolted together from
              spreadsheets and side tools.
            </p>
          </div>

          <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {MODULES.map((module) => (
              <div
                key={module.title}
                className="rounded-2xl border border-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
              >
                <span
                  className={`inline-flex h-11 w-11 items-center justify-center rounded-xl ${TONE_CLASSES[module.tone]}`}
                >
                  <module.icon className="h-5 w-5" strokeWidth={2} />
                </span>
                <h3 className="mt-4 text-base font-semibold text-ink">{module.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-inksoft">{module.description}</p>
              </div>
            ))}
          </div>
        </section>

        <section id="how-it-works" className="bg-white py-20">
          <div className="mx-auto max-w-7xl px-6">
            <div className="max-w-2xl">
              <h2 className="font-display text-3xl font-bold tracking-tight text-ink sm:text-4xl">
                Up and running in three steps.
              </h2>
              <p className="mt-3 text-lg text-inksoft">
                No lengthy implementation — your agency's day-to-day is the whole onboarding.
              </p>
            </div>

            <div className="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
              {STEPS.map((step) => (
                <div key={step.number}>
                  <span className="flex h-10 w-10 items-center justify-center rounded-full bg-ink font-display text-sm font-bold text-white">
                    {step.number}
                  </span>
                  <h3 className="mt-4 text-lg font-semibold text-ink">{step.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-inksoft">{step.description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section id="why-us" className="mx-auto max-w-7xl px-6 py-20">
          <div className="overflow-hidden rounded-3xl bg-ink px-8 py-14 text-center sm:px-16">
            <h2 className="font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
              See your whole operation, in one screen.
            </h2>
            <p className="mx-auto mt-3 max-w-xl text-inksoft">
              From the visit on the ground to the trend on the owner's dashboard — CareNexa360
              keeps every branch, every carer, and every family in sync.
            </p>
            <div className="mt-8">
              <Link to="/login" className={SECONDARY_LINK}>
                Sign In →
              </Link>
            </div>
          </div>
        </section>
      </main>

      <footer className="border-t border-line">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row">
          <Logo compact />
          <p className="text-xs text-inksoft">
            © {new Date().getFullYear()} CareNexa360. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
}

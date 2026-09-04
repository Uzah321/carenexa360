import { useMemo, useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  Drawer,
  EmptyState,
  FormField,
  Input,
  RowActionsMenu,
  Select,
  StatusBadge,
  Tabs,
  Textarea,
  type TabItem,
} from "../../../design-system";
import { useCarePlans, useCreateCarePlan, type CarePlanSectionInput } from "../../care-planning/api";
import { apiErrorMessage } from "../../../lib/api-error";
import { useStaff } from "../../staff/api";
import type { StaffMember } from "../../../lib/types";
import { CARE_PLAN_AREAS, CARE_PLAN_RISK_LEVELS, type CarePlan, type CarePlanRiskLevel, type CarePlanSection } from "../../../lib/types";
import { todayIso } from "../../../lib/dates";

const EMPTY_SECTION: CarePlanSectionInput = {
  area: "personal_care",
  identified_need: "",
  risk: "",
  goal: "",
  intervention: "",
  equipment: "",
  frequency: "",
  responsible_staff_id: null,
  start_date: "",
  review_date: "",
  status: "ongoing",
};

const RISK_TONE: Record<CarePlanRiskLevel, "danger" | "warning" | "success"> = {
  high: "danger",
  medium: "warning",
  low: "success",
};

function RiskBadge({ risk }: { risk: string | null }) {
  if (!risk || !(CARE_PLAN_RISK_LEVELS as readonly string[]).includes(risk)) {
    return <StatusBadge label="Not assessed" tone="neutral" />;
  }
  return <StatusBadge label={risk.toUpperCase()} tone={RISK_TONE[risk as CarePlanRiskLevel]} />;
}

function areaLabel(area: string) {
  return area.replaceAll("_", " ");
}

function daysUntil(dateIso: string): number {
  const ms = new Date(`${dateIso}T00:00:00`).getTime() - new Date(`${todayIso()}T00:00:00`).getTime();
  return Math.round(ms / (1000 * 60 * 60 * 24));
}

function toCarePlanSectionInput(section: CarePlanSection): CarePlanSectionInput {
  return {
    area: section.area,
    identified_need: section.identified_need,
    risk: section.risk ?? "",
    goal: section.goal,
    intervention: section.intervention,
    equipment: section.equipment ?? "",
    frequency: section.frequency ?? "",
    responsible_staff_id: section.responsible_staff_id,
    start_date: section.start_date ?? "",
    review_date: section.review_date ?? "",
    status: section.status,
  };
}

// Shared by the multi-section "New Version" form and the single-section
// "Edit Section" drawer — both edit the exact same fields.
function SectionFormFields({
  value,
  onChange,
  idPrefix,
  staff,
}: {
  value: CarePlanSectionInput;
  onChange: (patch: Partial<CarePlanSectionInput>) => void;
  idPrefix: string;
  staff: StaffMember[] | undefined;
}) {
  return (
    <>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Area" htmlFor={`${idPrefix}-area`}>
          <Select
            id={`${idPrefix}-area`}
            value={value.area}
            onChange={(e) => onChange({ area: e.target.value as CarePlanSectionInput["area"] })}
          >
            {CARE_PLAN_AREAS.map((area) => (
              <option key={area} value={area}>
                {areaLabel(area)}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Risk level" htmlFor={`${idPrefix}-risk`}>
          <Select
            id={`${idPrefix}-risk`}
            value={value.risk ?? ""}
            onChange={(e) => onChange({ risk: e.target.value as CarePlanRiskLevel | "" })}
          >
            <option value="">Not assessed</option>
            {CARE_PLAN_RISK_LEVELS.map((r) => (
              <option key={r} value={r}>
                {r.charAt(0).toUpperCase() + r.slice(1)}
              </option>
            ))}
          </Select>
        </FormField>
      </div>
      <FormField label="Identified need" htmlFor={`${idPrefix}-need`}>
        <Textarea
          id={`${idPrefix}-need`}
          required
          value={value.identified_need}
          onChange={(e) => onChange({ identified_need: e.target.value })}
        />
      </FormField>
      <FormField label="Desired outcome (goal)" htmlFor={`${idPrefix}-goal`}>
        <Textarea id={`${idPrefix}-goal`} required value={value.goal} onChange={(e) => onChange({ goal: e.target.value })} />
      </FormField>
      <FormField label="Interventions (one per line)" htmlFor={`${idPrefix}-intervention`}>
        <Textarea
          id={`${idPrefix}-intervention`}
          required
          rows={4}
          value={value.intervention}
          onChange={(e) => onChange({ intervention: e.target.value })}
        />
      </FormField>
      <FormField label="Equipment" htmlFor={`${idPrefix}-equipment`}>
        <Input id={`${idPrefix}-equipment`} value={value.equipment ?? ""} onChange={(e) => onChange({ equipment: e.target.value })} />
      </FormField>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Frequency" htmlFor={`${idPrefix}-frequency`}>
          <Input
            id={`${idPrefix}-frequency`}
            value={value.frequency ?? ""}
            onChange={(e) => onChange({ frequency: e.target.value })}
          />
        </FormField>
        <FormField label="Responsible staff" htmlFor={`${idPrefix}-responsible`}>
          <Select
            id={`${idPrefix}-responsible`}
            value={value.responsible_staff_id ?? ""}
            onChange={(e) => onChange({ responsible_staff_id: e.target.value ? Number(e.target.value) : null })}
          >
            <option value="">Unassigned</option>
            {(staff ?? []).map((s) => (
              <option key={s.id} value={s.user_id}>
                {s.name}
              </option>
            ))}
          </Select>
        </FormField>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Start date" htmlFor={`${idPrefix}-start`}>
          <Input
            id={`${idPrefix}-start`}
            type="date"
            value={value.start_date ?? ""}
            onChange={(e) => onChange({ start_date: e.target.value })}
          />
        </FormField>
        <FormField label="Review date" htmlFor={`${idPrefix}-review`}>
          <Input
            id={`${idPrefix}-review`}
            type="date"
            value={value.review_date ?? ""}
            onChange={(e) => onChange({ review_date: e.target.value })}
          />
        </FormField>
      </div>
    </>
  );
}

interface SectionActionsProps {
  canManage: boolean;
  canRemove: boolean;
  onEdit: () => void;
  onRemove: () => void;
}

function sectionActions({ canManage, canRemove, onEdit, onRemove }: SectionActionsProps) {
  if (!canManage) return [];
  return [
    { label: "Edit section", onClick: onEdit },
    { label: "Remove section", onClick: onRemove, tone: "danger" as const, disabled: !canRemove },
  ];
}

function SectionDetail({ section, actions }: { section: CarePlanSection; actions: SectionActionsProps }) {
  const interventionLines = section.intervention.split("\n").map((l) => l.trim()).filter(Boolean);

  return (
    <Card className="print-avoid-break">
      <CardHeader>
        <div className="flex items-center justify-between">
          <span className="font-display text-base font-bold text-ink">{areaLabel(section.area)}</span>
          <div className="flex items-center gap-2">
            <RiskBadge risk={section.risk} />
            <RowActionsMenu actions={sectionActions(actions)} label={`${areaLabel(section.area)} actions`} />
          </div>
        </div>
      </CardHeader>
      <CardBody>
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Identified Need</h4>
            <p className="text-sm text-ink">{section.identified_need}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Desired Outcome</h4>
            <p className="text-sm text-ink">{section.goal}</p>
          </div>
        </div>

        <div className="mt-5">
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-inksoft">Interventions</h4>
          <ul className="space-y-1.5">
            {interventionLines.map((line, i) => (
              <li key={i} className="flex items-start gap-2 text-sm text-ink">
                <span className="mt-0.5 text-lime">✓</span>
                <span>{line}</span>
              </li>
            ))}
          </ul>
        </div>

        {section.equipment && (
          <div className="mt-5">
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Equipment</h4>
            <p className="text-sm text-ink">{section.equipment}</p>
          </div>
        )}

        <div className="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-4 sm:grid-cols-4">
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Frequency</h4>
            <p className="text-sm text-ink">{section.frequency ?? "—"}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Responsible</h4>
            <p className="text-sm text-ink">{section.responsible_staff_name ?? "—"}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Effective From</h4>
            <p className="text-sm text-ink">{section.start_date ?? "—"}</p>
          </div>
          <div>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-inksoft">Next Review</h4>
            <p className={`text-sm ${section.review_date && daysUntil(section.review_date) < 0 ? "font-semibold text-coral" : "text-ink"}`}>
              {section.review_date ?? "—"}
            </p>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

function OverviewTab({
  sections,
  onSelectArea,
  canManage,
  canRemove,
  onEdit,
  onRemove,
}: {
  sections: CarePlanSection[];
  onSelectArea: (area: string) => void;
  canManage: boolean;
  canRemove: boolean;
  onEdit: (section: CarePlanSection) => void;
  onRemove: (section: CarePlanSection) => void;
}) {
  return (
    <div className="space-y-2">
      {sections.map((section) => (
        <div
          key={section.id}
          className="flex w-full items-center justify-between gap-3 rounded-2xl border border-line bg-white px-4 py-3 transition-colors duration-150 hover:border-teal hover:bg-tealtint"
        >
          <button type="button" onClick={() => onSelectArea(section.area)} className="min-w-0 flex-1 text-left">
            <p className="font-semibold text-ink">{areaLabel(section.area)}</p>
            <p className="truncate text-sm text-inksoft">{section.goal}</p>
          </button>
          <div className="flex shrink-0 items-center gap-2">
            <RiskBadge risk={section.risk} />
            {canManage && (
              <RowActionsMenu
                actions={sectionActions({
                  canManage,
                  canRemove,
                  onEdit: () => onEdit(section),
                  onRemove: () => onRemove(section),
                })}
                label={`${areaLabel(section.area)} actions`}
              />
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

function RisksTab({ sections }: { sections: CarePlanSection[] }) {
  const severityOrder: Record<string, number> = { high: 0, medium: 1, low: 2 };
  const withRisk = sections
    .filter((s) => s.risk)
    .sort((a, b) => (severityOrder[a.risk ?? ""] ?? 3) - (severityOrder[b.risk ?? ""] ?? 3));

  if (withRisk.length === 0) {
    return <EmptyState message="No risks have been assessed on this care plan." />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-line bg-white">
      <table className="min-w-full divide-y divide-line">
        <thead className="bg-paper">
          <tr>
            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">Risk</th>
            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">Level</th>
            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">Control</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line bg-white">
          {withRisk.map((section) => (
            <tr key={section.id}>
              <td className="px-4 py-3 text-sm text-ink">
                <span className="font-medium">{areaLabel(section.area)}</span>
                <span className="block text-xs text-inksoft">{section.identified_need}</span>
              </td>
              <td className="px-4 py-3 text-sm">
                <RiskBadge risk={section.risk} />
              </td>
              <td className="px-4 py-3 text-sm text-ink">{section.equipment ? `${section.intervention} (${section.equipment})` : section.intervention}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function GoalsTab({ sections }: { sections: CarePlanSection[] }) {
  return (
    <div className="space-y-2">
      {sections.map((section) => (
        <div key={section.id} className="rounded-2xl border border-line bg-white px-4 py-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-inksoft">{areaLabel(section.area)}</p>
          <p className="mt-1 text-sm text-ink">{section.goal}</p>
        </div>
      ))}
    </div>
  );
}

function ReviewsTab({ sections }: { sections: CarePlanSection[] }) {
  const withReview = sections
    .filter((s) => s.review_date)
    .sort((a, b) => (a.review_date ?? "").localeCompare(b.review_date ?? ""));

  if (withReview.length === 0) {
    return <EmptyState message="No review dates have been set on this care plan." />;
  }

  return (
    <ul className="divide-y divide-line rounded-2xl border border-line bg-white">
      {withReview.map((section) => {
        const overdue = daysUntil(section.review_date as string) < 0;
        return (
          <li key={section.id} className="flex items-center justify-between px-4 py-3 text-sm">
            <span className="font-medium text-ink">{areaLabel(section.area)}</span>
            <span className={overdue ? "font-semibold text-coral" : "text-inksoft"}>
              {section.review_date} {overdue ? "· overdue" : ""}
            </span>
          </li>
        );
      })}
    </ul>
  );
}

function HistoryTab({ plans, viewingId, onSelect }: { plans: CarePlan[]; viewingId: number | null; onSelect: (id: number) => void }) {
  const sorted = [...plans].sort((a, b) => b.version - a.version);

  return (
    <ul className="divide-y divide-line rounded-2xl border border-line bg-white">
      {sorted.map((plan) => (
        <li key={plan.id}>
          <button
            type="button"
            onClick={() => onSelect(plan.id)}
            className={`flex w-full items-center justify-between px-4 py-3 text-left text-sm transition-colors duration-150 hover:bg-paper ${
              viewingId === plan.id ? "bg-tealtint" : ""
            }`}
          >
            <span className="font-medium text-ink">
              Version {plan.version} — effective {plan.effective_from}
            </span>
            <StatusBadge label={plan.status === "active" ? "CURRENT" : "archived"} tone={plan.status === "active" ? "success" : "neutral"} />
          </button>
        </li>
      ))}
    </ul>
  );
}

export function CarePlanTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: plans, isLoading } = useCarePlans(serviceUserId);
  const { data: staff } = useStaff(1, 100);
  const createCarePlan = useCreateCarePlan(serviceUserId);

  const [viewingId, setViewingId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState("overview");
  const [isOpen, setIsOpen] = useState(false);
  const [effectiveFrom, setEffectiveFrom] = useState("");
  const [notes, setNotes] = useState("");
  const [sections, setSections] = useState<CarePlanSectionInput[]>([{ ...EMPTY_SECTION }]);

  const [editingSection, setEditingSection] = useState<CarePlanSection | null>(null);
  const [editDraft, setEditDraft] = useState<CarePlanSectionInput>({ ...EMPTY_SECTION });
  const [removingSection, setRemovingSection] = useState<CarePlanSection | null>(null);
  // Shared by every caller of submitAsNewVersion — New Version, Edit Section,
  // and Remove Section — since only one of those drawers/dialogs is ever open
  // at once.
  const [versionError, setVersionError] = useState<string | null>(null);

  const activePlan = plans?.find((p) => p.status === "active") ?? null;
  const viewingPlan = plans?.find((p) => p.id === viewingId) ?? activePlan;
  const isViewingActivePlan = Boolean(activePlan && viewingPlan?.id === activePlan.id);

  const areaTabs: TabItem[] = useMemo(() => {
    if (!viewingPlan) return [];
    const present = new Set(viewingPlan.sections.map((s) => s.area));
    return CARE_PLAN_AREAS.filter((a) => present.has(a)).map((a) => ({ key: a, label: areaLabel(a) }));
  }, [viewingPlan]);

  const tabItems: TabItem[] = [
    { key: "overview", label: "Overview" },
    ...areaTabs,
    { key: "risks", label: "Risks" },
    { key: "goals", label: "Goals" },
    { key: "reviews", label: "Reviews" },
    { key: "history", label: "History" },
  ];

  const nextReviewDate = viewingPlan?.sections
    .map((s) => s.review_date)
    .filter((d): d is string => Boolean(d))
    .sort()[0];

  function openNewVersionDrawer() {
    setSections(activePlan && activePlan.sections.length > 0 ? activePlan.sections.map(toCarePlanSectionInput) : [{ ...EMPTY_SECTION }]);
    setEffectiveFrom(todayIso());
    setNotes(activePlan?.notes ?? "");
    setIsOpen(true);
  }

  function updateSection(index: number, patch: Partial<CarePlanSectionInput>) {
    setSections((prev) => prev.map((s, i) => (i === index ? { ...s, ...patch } : s)));
  }

  function normalizeSection(s: CarePlanSectionInput): CarePlanSectionInput {
    return { ...s, risk: s.risk || undefined, responsible_staff_id: s.responsible_staff_id || null };
  }

  // Throws on failure — each caller below decides what "stay open on error"
  // means for its own UI (a drawer form vs. a confirm dialog).
  async function submitAsNewVersion(newSections: CarePlanSectionInput[], planNotes: string) {
    await createCarePlan.mutateAsync({
      effective_from: todayIso(),
      notes: planNotes,
      sections: newSections.map(normalizeSection),
    });
    setViewingId(null);
  }

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setVersionError(null);
    try {
      await createCarePlan.mutateAsync({
        effective_from: effectiveFrom,
        notes,
        sections: sections.map(normalizeSection),
      });
      setIsOpen(false);
      setViewingId(null);
      setActiveTab("overview");
    } catch (err) {
      setVersionError(apiErrorMessage(err, "Could not save this version. Please try again."));
    }
  }

  function openEditSection(section: CarePlanSection) {
    setEditingSection(section);
    setEditDraft(toCarePlanSectionInput(section));
    setVersionError(null);
  }

  async function handleSaveEditedSection(event: FormEvent) {
    event.preventDefault();
    if (!activePlan || !editingSection) return;
    setVersionError(null);

    const newSections = activePlan.sections.map((s) => (s.id === editingSection.id ? editDraft : toCarePlanSectionInput(s)));
    try {
      await submitAsNewVersion(newSections, activePlan.notes ?? "");
      setEditingSection(null);
      // The edited section's area might have changed — land back on Overview
      // rather than a tab key that may no longer exist.
      setActiveTab("overview");
    } catch (err) {
      setVersionError(apiErrorMessage(err, "Could not save this section. Please try again."));
    }
  }

  async function handleConfirmRemove() {
    if (!activePlan || !removingSection) return;
    setVersionError(null);

    const newSections = activePlan.sections.filter((s) => s.id !== removingSection.id).map(toCarePlanSectionInput);
    try {
      await submitAsNewVersion(newSections, activePlan.notes ?? "");
      setRemovingSection(null);
      setActiveTab("overview");
    } catch (err) {
      setVersionError(apiErrorMessage(err, "Could not remove this section. Please try again."));
    }
  }

  if (isLoading) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  if (!activePlan) {
    return (
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <span>No Active Care Plan</span>
            <Button variant="secondary" onClick={openNewVersionDrawer}>
              New Version
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          <EmptyState message="No care plan has been created for this service user yet." />
        </CardBody>
      </Card>
    );
  }

  const canManageSections = isViewingActivePlan;
  const canRemoveSection = (activePlan.sections.length ?? 0) > 1;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white px-4 py-3">
        <div className="flex items-center gap-3">
          <StatusBadge label={viewingPlan?.status === "active" ? "Active" : "Archived"} tone={viewingPlan?.status === "active" ? "success" : "neutral"} />
          <span className="text-sm font-medium text-ink">Version {viewingPlan?.version}</span>
          {nextReviewDate && (
            <span className={`text-sm ${daysUntil(nextReviewDate) < 0 ? "font-semibold text-coral" : "text-inksoft"}`}>
              {daysUntil(nextReviewDate) < 0
                ? `Review overdue by ${Math.abs(daysUntil(nextReviewDate))} days`
                : `Review due in ${daysUntil(nextReviewDate)} days`}
            </span>
          )}
        </div>
        <Button variant="secondary" onClick={openNewVersionDrawer}>
          New Version
        </Button>
      </div>

      {!isViewingActivePlan && viewingPlan && (
        <Alert tone="info">You're viewing an archived version — editing and removing sections only applies to the current active plan.</Alert>
      )}

      <Tabs items={tabItems} activeKey={activeTab} onChange={setActiveTab} />

      {viewingPlan && (
        <div>
          {activeTab === "overview" && (
            <OverviewTab
              sections={viewingPlan.sections}
              onSelectArea={setActiveTab}
              canManage={canManageSections}
              canRemove={canRemoveSection}
              onEdit={openEditSection}
              onRemove={setRemovingSection}
            />
          )}
          {areaTabs.some((t) => t.key === activeTab) &&
            viewingPlan.sections
              .filter((s) => s.area === activeTab)
              .map((section) => (
                <SectionDetail
                  key={section.id}
                  section={section}
                  actions={{
                    canManage: canManageSections,
                    canRemove: canRemoveSection,
                    onEdit: () => openEditSection(section),
                    onRemove: () => setRemovingSection(section),
                  }}
                />
              ))}
          {activeTab === "risks" && <RisksTab sections={viewingPlan.sections} />}
          {activeTab === "goals" && <GoalsTab sections={viewingPlan.sections} />}
          {activeTab === "reviews" && <ReviewsTab sections={viewingPlan.sections} />}
          {activeTab === "history" && (
            <HistoryTab
              plans={plans ?? []}
              viewingId={viewingPlan.id}
              onSelect={(id) => {
                setViewingId(id);
                setActiveTab("overview");
              }}
            />
          )}
        </div>
      )}

      <Drawer
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setVersionError(null);
        }}
        title="New Care Plan Version"
      >
        <form onSubmit={handleCreate}>
          {versionError && (
            <div className="mb-4">
              <Alert tone="danger">{versionError}</Alert>
            </div>
          )}
          <FormField label="Effective from" htmlFor="effective_from">
            <Input
              id="effective_from"
              type="date"
              required
              value={effectiveFrom}
              onChange={(e) => setEffectiveFrom(e.target.value)}
            />
          </FormField>
          <FormField label="Notes" htmlFor="plan-notes">
            <Textarea id="plan-notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>

          <div className="mb-2 mt-6 text-sm font-semibold text-ink">
            Sections {activePlan && "(pre-filled from the current active plan — edit what's changed)"}
          </div>
          {sections.map((section, index) => (
            <div key={index} className="mb-4 rounded-xl border border-line p-3">
              <SectionFormFields
                value={section}
                onChange={(patch) => updateSection(index, patch)}
                idPrefix={`section-${index}`}
                staff={staff?.data}
              />
              {sections.length > 1 && (
                <button
                  type="button"
                  className="text-sm text-red-600 hover:text-red-500"
                  onClick={() => setSections((prev) => prev.filter((_, i) => i !== index))}
                >
                  Remove section
                </button>
              )}
            </div>
          ))}
          <Button
            type="button"
            variant="secondary"
            className="mb-6"
            onClick={() => setSections((prev) => [...prev, { ...EMPTY_SECTION }])}
          >
            Add another section
          </Button>

          <div className="flex justify-end gap-2 border-t border-line pt-4">
            <Button type="button" variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createCarePlan.isPending}>
              Save Version
            </Button>
          </div>
        </form>
      </Drawer>

      <Drawer
        isOpen={Boolean(editingSection)}
        onClose={() => {
          setEditingSection(null);
          setVersionError(null);
        }}
        title={editingSection ? `Edit — ${areaLabel(editingSection.area)}` : "Edit Section"}
      >
        <form onSubmit={handleSaveEditedSection}>
          {versionError && (
            <div className="mb-4">
              <Alert tone="danger">{versionError}</Alert>
            </div>
          )}
          <p className="mb-4 text-xs text-inksoft">
            Saving creates a new care plan version with this change — the current version stays in history unchanged.
          </p>
          <SectionFormFields value={editDraft} onChange={(patch) => setEditDraft((prev) => ({ ...prev, ...patch }))} idPrefix="edit-section" staff={staff?.data} />
          <div className="flex justify-end gap-2 border-t border-line pt-4">
            <Button type="button" variant="secondary" onClick={() => setEditingSection(null)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createCarePlan.isPending}>
              Save as New Version
            </Button>
          </div>
        </form>
      </Drawer>

      <ConfirmDialog
        isOpen={Boolean(removingSection)}
        title="Remove section"
        message={
          removingSection
            ? `Remove "${areaLabel(removingSection.area)}" from the care plan? This creates a new version without this section — the current version stays in history unchanged.`
            : ""
        }
        confirmLabel="Remove"
        tone="danger"
        isLoading={createCarePlan.isPending}
        error={versionError}
        onConfirm={handleConfirmRemove}
        onCancel={() => {
          setRemovingSection(null);
          setVersionError(null);
        }}
      />
    </div>
  );
}

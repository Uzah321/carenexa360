import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  DataTable,
  FilterBar,
  FormField,
  Input,
  Modal,
  Pagination,
  RowActionsMenu,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { todayIso } from "../../../lib/dates";
import { SPOT_CHECK_AREAS, type SpotCheck, type SpotCheckArea, type SpotCheckOutcome, type SpotCheckResult } from "../../../lib/types";
import { useServiceUsers } from "../../service-users/api";
import { useStaff } from "../../staff/api";
import { label, useSaveSpotCheck, useSpotChecks, type SpotCheckInput } from "../api";

const OUTCOME_TONE = { pass: "success", needs_improvement: "warning", fail: "danger" } as const;
const RESULT_CHOICES: { value: SpotCheckResult; text: string; selected: string }[] = [
  { value: "pass", text: "Pass", selected: "bg-lime text-white" },
  { value: "fail", text: "Fail", selected: "bg-coral text-white" },
  { value: "na", text: "N/A", selected: "bg-inksoft text-white" },
];

function emptyForm(): SpotCheckInput {
  return { check_date: todayIso(), results: {}, notes: "", actions_required: "", follow_up_date: "" };
}

/** Mirrors SpotCheck::outcomeFor — a preview; the API decides. */
function previewOutcome(results: SpotCheckInput["results"]): SpotCheckOutcome {
  const fails = Object.values(results ?? {}).filter((r) => r === "fail").length;
  return fails === 0 ? "pass" : fails === 1 ? "needs_improvement" : "fail";
}

export function SpotChecksPage() {
  const [outcome, setOutcome] = useState<SpotCheckOutcome | "">("");
  const [page, setPage] = useState(1);
  const { data, isLoading } = useSpotChecks({ outcome: outcome || undefined, page });
  const { data: staff } = useStaff(1, 500);
  const { data: serviceUsers } = useServiceUsers(1);
  const save = useSaveSpotCheck();

  const [isOpen, setIsOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<SpotCheckInput>(emptyForm());
  const [error, setError] = useState<string | null>(null);
  const set = (patch: SpotCheckInput) => setForm((prev) => ({ ...prev, ...patch }));

  function open(check: SpotCheck | null) {
    setEditingId(check?.id ?? null);
    setForm(
      check
        ? {
            staff_user_id: check.staff_user_id,
            service_user_id: check.service_user_id,
            check_date: check.check_date,
            results: check.results,
            notes: check.notes ?? "",
            actions_required: check.actions_required ?? "",
            follow_up_date: check.follow_up_date ?? "",
          }
        : emptyForm(),
    );
    setError(null);
    setIsOpen(true);
  }

  function setResult(area: SpotCheckArea, result: SpotCheckResult) {
    set({ results: { ...form.results, [area]: result } });
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    if (Object.keys(form.results ?? {}).length === 0) {
      setError("Mark at least one area before saving.");
      return;
    }
    try {
      await save.mutateAsync({
        id: editingId ?? undefined,
        ...form,
        notes: form.notes || null,
        actions_required: form.actions_required || null,
        follow_up_date: form.follow_up_date || null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this spot check. Please try again."));
    }
  }

  const columns: Column<SpotCheck>[] = [
    { key: "date", header: "Date", render: (row) => row.check_date },
    { key: "staff", header: "Staff Checked", render: (row) => <span className="font-medium text-ink">{row.staff_name ?? "—"}</span> },
    { key: "client", header: "Client", render: (row) => row.service_user_name ?? "—" },
    { key: "outcome", header: "Outcome", render: (row) => <StatusBadge label={label(row.outcome)} tone={OUTCOME_TONE[row.outcome]} /> },
    {
      key: "failed",
      header: "Areas Failed",
      render: (row) =>
        Object.entries(row.results)
          .filter(([, r]) => r === "fail")
          .map(([area]) => label(area))
          .join(", ") || "—",
    },
    { key: "checked_by", header: "Checked By", render: (row) => row.checked_by_name ?? "—" },
    { key: "follow_up", header: "Follow Up", render: (row) => row.follow_up_date ?? "—" },
    {
      key: "actions",
      header: "",
      className: "w-12 text-right",
      render: (row) => <RowActionsMenu actions={[{ label: "Open / Edit", onClick: () => open(row) }]} label={`Spot check of ${row.staff_name}`} />,
    },
  ];

  const preview = previewOutcome(form.results);

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Spot Checks</h1>
            <p className="mt-1 text-sm text-inksoft">
              Unannounced observations of staff at work. No fails is a pass, one fail needs improvement, more is a fail.
            </p>
          </div>
          <Button onClick={() => open(null)}>Record Spot Check</Button>
        </div>
      </div>

      <FilterBar>
        <FormField label="Outcome" htmlFor="spot-filter-outcome">
          <Select id="spot-filter-outcome" value={outcome} onChange={(e) => setOutcome(e.target.value as SpotCheckOutcome | "")}>
            <option value="">All outcomes</option>
            <option value="pass">Pass</option>
            <option value="needs_improvement">Needs improvement</option>
            <option value="fail">Fail</option>
          </Select>
        </FormField>
      </FilterBar>

      <DataTable columns={columns} rows={data?.data ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
      {data && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />}

      <Modal
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        title={editingId ? "Spot Check" : "Record Spot Check"}
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="spot-check-form" type="submit" isLoading={save.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="spot-check-form" onSubmit={handleSave}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <FormField label="Staff checked" htmlFor="sc-staff">
              <Select
                id="sc-staff"
                required
                value={form.staff_user_id ?? ""}
                onChange={(e) => set({ staff_user_id: e.target.value ? Number(e.target.value) : undefined })}
              >
                <option value="" disabled>
                  Select staff
                </option>
                {(staff?.data ?? []).map((s) => (
                  <option key={s.id} value={s.user_id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Client (optional)" htmlFor="sc-client">
              <Select
                id="sc-client"
                value={form.service_user_id ?? ""}
                onChange={(e) => set({ service_user_id: e.target.value ? Number(e.target.value) : null })}
              >
                <option value="">None</option>
                {(serviceUsers?.data ?? []).map((su) => (
                  <option key={su.id} value={su.id}>
                    {su.first_name} {su.last_name}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Date" htmlFor="sc-date">
              <Input id="sc-date" type="date" required value={form.check_date ?? ""} onChange={(e) => set({ check_date: e.target.value })} />
            </FormField>
          </div>

          <div className="mb-4 rounded-xl border border-line">
            {SPOT_CHECK_AREAS.map((area) => (
              <div key={area} className="flex items-center justify-between gap-3 border-b border-line px-3 py-2 last:border-b-0">
                <span id={`sc-area-${area}`} className="text-sm text-ink">
                  {label(area)}
                </span>
                <div role="radiogroup" aria-labelledby={`sc-area-${area}`} className="flex overflow-hidden rounded-full border border-line">
                  {RESULT_CHOICES.map((choice) => {
                    const selected = form.results?.[area] === choice.value;
                    return (
                      <button
                        key={choice.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => setResult(area, choice.value)}
                        className={`px-3 py-1 text-xs font-medium transition-colors duration-150 ${selected ? choice.selected : "bg-white text-inksoft hover:bg-paper"}`}
                      >
                        {choice.text}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
          <p className="-mt-2 mb-4 text-sm text-inksoft">
            Outcome: <StatusBadge label={label(preview)} tone={OUTCOME_TONE[preview]} />
          </p>

          <FormField label="Notes" htmlFor="sc-notes">
            <Textarea id="sc-notes" value={form.notes ?? ""} onChange={(e) => set({ notes: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr]">
            <FormField label="Actions required" htmlFor="sc-actions">
              <Textarea id="sc-actions" rows={2} value={form.actions_required ?? ""} onChange={(e) => set({ actions_required: e.target.value })} />
            </FormField>
            <FormField label="Follow up by" htmlFor="sc-follow-up">
              <Input id="sc-follow-up" type="date" value={form.follow_up_date ?? ""} onChange={(e) => set({ follow_up_date: e.target.value })} />
            </FormField>
          </div>
        </form>
      </Modal>
    </div>
  );
}

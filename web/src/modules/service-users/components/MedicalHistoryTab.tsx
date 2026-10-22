import { useState, type FormEvent, type ReactNode } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  DataTable,
  FormField,
  Input,
  Modal,
  RowActionsMenu,
  Select,
  StatusBadge,
  Suggestions,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useAuth } from "../../../lib/auth-context";
import { todayIso } from "../../../lib/dates";
import { formatDate, tenantSettings } from "../../../lib/preferences";
import {
  ALLERGY_SEVERITIES,
  ALLERGY_TYPES,
  MEDICAL_HISTORY_LEAD_ROLES,
  RESUSCITATION_STATUSES,
  SMOKING_STATUSES,
  type AllergySeverity,
  type AllergyType,
  type MedicalHistoryCategory,
  type MedicalHistoryEntry,
  type MedicalHistoryStatus,
  type ResuscitationStatus,
  type ServiceUser,
  type ServiceUserContact,
  type SmokingStatus,
} from "../../../lib/types";
import {
  useCreateMedicalHistoryEntry,
  useDeleteMedicalHistoryEntry,
  useMedicalHistory,
  useReviewMedicalHistory,
  useUpdateMedicalHistoryEntry,
  type MedicalHistoryEntryInput,
} from "../../medical-history/api";
import {
  ALLERGY_TYPE_LABELS,
  CATEGORY_CONFIG,
  CATEGORY_ORDER,
  FAMILY_RELATIONSHIPS,
  RESUSCITATION_LABELS,
  SEVERITY_LABELS,
  SEVERITY_TONE,
  SMOKING_LABELS,
  STATUS_LABELS,
  STATUS_TONE,
  entryColumns,
  sortEntries,
} from "../../medical-history/categories";
import { downloadMedicalHistoryWord, printMedicalHistory } from "../../medical-history/medicalHistoryExport";
import { useServiceUserContacts, useUpdateServiceUser } from "../api";

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-line py-2 text-sm last:border-0">
      <dt className="text-inksoft">{label}</dt>
      <dd className="text-right font-medium text-ink">{children}</dd>
    </div>
  );
}

/** Whether the history's last sign-off is older than the care plan review interval. */
function isReviewDue(reviewedAt: string | null): boolean {
  if (!reviewedAt) return true;
  const due = new Date(reviewedAt);
  due.setMonth(due.getMonth() + tenantSettings().care_pathway.review_interval_months);
  return due < new Date();
}

export function MedicalHistoryTab({ serviceUser }: { serviceUser: ServiceUser }) {
  const { user, hasAnyRole } = useAuth();
  const { data: entries, isLoading } = useMedicalHistory(serviceUser.id);
  const { data: contacts } = useServiceUserContacts(serviceUser.id);
  const updateEntry = useUpdateMedicalHistoryEntry(serviceUser.id);
  const deleteEntry = useDeleteMedicalHistoryEntry(serviceUser.id);

  const [editing, setEditing] = useState<{ category: MedicalHistoryCategory; entry: MedicalHistoryEntry | null } | null>(null);
  const [deleting, setDeleting] = useState<MedicalHistoryEntry | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const allEntries = entries ?? [];
  const isLead = hasAnyRole(MEDICAL_HISTORY_LEAD_ROLES);
  const gps = (contacts ?? []).filter((c) => c.type === "gp");

  async function handleDelete() {
    if (!deleting) return;
    setDeleteError(null);
    try {
      await deleteEntry.mutateAsync(deleting.id);
      setDeleting(null);
    } catch (err) {
      setDeleteError(apiErrorMessage(err, "Could not remove this entry. Please try again."));
    }
  }

  async function markResolved(entry: MedicalHistoryEntry) {
    setActionError(null);
    try {
      await updateEntry.mutateAsync({
        id: entry.id,
        category: entry.category,
        name: entry.name,
        status: "resolved",
        ...(CATEGORY_CONFIG[entry.category].endedLabel ? { ended_on: entry.ended_on ?? todayIso() } : {}),
      });
    } catch (err) {
      setActionError(apiErrorMessage(err, "Could not update this entry. Please try again."));
    }
  }

  return (
    <div className="space-y-4">
      <ReviewCard serviceUser={serviceUser} entries={allEntries} contacts={contacts ?? []} canReview={isLead} />

      <HealthBackgroundCard serviceUser={serviceUser} gps={gps} />

      {actionError && <Alert tone="danger">{actionError}</Alert>}

      {CATEGORY_ORDER.map((category) => (
        <HistorySection
          key={category}
          category={category}
          serviceUser={serviceUser}
          entries={allEntries.filter((e) => e.category === category)}
          isLoading={isLoading}
          onAdd={() => setEditing({ category, entry: null })}
          onEdit={(entry) => setEditing({ category, entry })}
          onResolve={markResolved}
          onDelete={(entry) => {
            setDeleteError(null);
            setDeleting(entry);
          }}
          canDelete={(entry) => isLead || entry.recorded_by === user?.id}
        />
      ))}

      {editing && (
        <EntryModal
          serviceUserId={serviceUser.id}
          category={editing.category}
          entry={editing.entry}
          onClose={() => setEditing(null)}
        />
      )}

      <ConfirmDialog
        isOpen={deleting !== null}
        title="Remove from Medical History"
        message={`Remove "${deleting?.name ?? ""}" from the medical history? Use this for something recorded in error. If it's simply no longer current, mark it resolved instead so it stays part of their history.`}
        confirmLabel="Remove"
        tone="danger"
        isLoading={deleteEntry.isPending}
        error={deleteError}
        onConfirm={handleDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}

/** Who last signed the history off, whether it's due again, and the exports. */
function ReviewCard({
  serviceUser,
  entries,
  contacts,
  canReview,
}: {
  serviceUser: ServiceUser;
  entries: MedicalHistoryEntry[];
  contacts: ServiceUserContact[];
  canReview: boolean;
}) {
  const review = useReviewMedicalHistory(serviceUser.id);
  const [isConfirming, setIsConfirming] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const reviewedAt = serviceUser.medical_history_reviewed_at;
  const isDue = isReviewDue(reviewedAt);

  async function handleReview() {
    setError(null);
    try {
      await review.mutateAsync();
      setIsConfirming(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not mark the history as reviewed. Please try again."));
    }
  }

  return (
    <Card>
      <CardBody>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <StatusBadge label={isDue ? "Review due" : "Up to date"} tone={isDue ? "warning" : "success"} />
            <span className="text-inksoft">
              {reviewedAt
                ? `Last reviewed ${formatDate(reviewedAt)}${serviceUser.medical_history_reviewed_by_name ? ` by ${serviceUser.medical_history_reviewed_by_name}` : ""}`
                : "This medical history hasn't been reviewed yet."}
            </span>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button variant="secondary" onClick={() => printMedicalHistory(serviceUser, entries, contacts)}>
              Print / PDF
            </Button>
            <Button variant="secondary" onClick={() => downloadMedicalHistoryWord(serviceUser, entries, contacts)}>
              Download Word
            </Button>
            {canReview && (
              <Button
                onClick={() => {
                  setError(null);
                  setIsConfirming(true);
                }}
              >
                Mark as Reviewed
              </Button>
            )}
          </div>
        </div>
      </CardBody>

      <ConfirmDialog
        isOpen={isConfirming}
        title="Mark Medical History as Reviewed"
        message={`Confirm you've gone through ${serviceUser.first_name}'s medical history and it's complete and up to date. It will be stamped with your name and today's date.`}
        confirmLabel="Mark as Reviewed"
        isLoading={review.isPending}
        error={error}
        onConfirm={handleReview}
        onCancel={() => setIsConfirming(false)}
      />
    </Card>
  );
}

const RESUSCITATION_TONE: Record<ResuscitationStatus, "danger" | "success"> = {
  dnacpr: "danger",
  for_resuscitation: "success",
};

/** Resuscitation decision, GP, lifestyle and a narrative summary. */
function HealthBackgroundCard({ serviceUser, gps }: { serviceUser: ServiceUser; gps: ServiceUserContact[] }) {
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [isOpen, setIsOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState(() => draftFrom(serviceUser));

  function draftFrom(su: ServiceUser) {
    return {
      resuscitation_status: su.resuscitation_status ?? "",
      resuscitation_notes: su.resuscitation_notes ?? "",
      smoking_status: su.smoking_status ?? "",
      alcohol_intake: su.alcohol_intake ?? "",
      medical_history_summary: su.medical_history_summary ?? "",
    };
  }

  function openModal() {
    setDraft(draftFrom(serviceUser));
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync({
        resuscitation_status: (draft.resuscitation_status || null) as ResuscitationStatus | null,
        resuscitation_notes: draft.resuscitation_notes || null,
        smoking_status: (draft.smoking_status || null) as SmokingStatus | null,
        alcohol_intake: draft.alcohol_intake || null,
        medical_history_summary: draft.medical_history_summary || null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save the health background. Please try again."));
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Health Background</span>
          <Button variant="secondary" onClick={openModal}>
            Edit
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        <div className="grid grid-cols-1 gap-x-8 lg:grid-cols-2">
          <dl>
            <InfoRow label="Resuscitation">
              {serviceUser.resuscitation_status ? (
                <StatusBadge
                  label={RESUSCITATION_LABELS[serviceUser.resuscitation_status]}
                  tone={RESUSCITATION_TONE[serviceUser.resuscitation_status]}
                />
              ) : (
                <span className="text-inksoft">Not recorded</span>
              )}
            </InfoRow>
            {serviceUser.resuscitation_notes && (
              <p className="whitespace-pre-line border-b border-line py-2 text-sm text-ink">{serviceUser.resuscitation_notes}</p>
            )}
            <InfoRow label="Registered GP">
              {gps.length > 0 ? (
                gps.map((gp) => (
                  <div key={gp.id}>
                    {gp.name}
                    {gp.phone && <span className="font-normal text-inksoft"> — {gp.phone}</span>}
                  </div>
                ))
              ) : (
                <span className="font-normal text-inksoft">Add a GP on the Overview tab's Contacts</span>
              )}
            </InfoRow>
          </dl>
          <dl>
            <InfoRow label="Smoking">{serviceUser.smoking_status ? SMOKING_LABELS[serviceUser.smoking_status] : "—"}</InfoRow>
            <InfoRow label="Alcohol">{serviceUser.alcohol_intake || "—"}</InfoRow>
          </dl>
        </div>
        <div className="mt-2">
          <div className="mb-1 text-sm text-inksoft">Summary</div>
          <p className="whitespace-pre-line text-sm text-ink">
            {serviceUser.medical_history_summary || "No summary written yet — a few lines on their health story helps anyone new to their care."}
          </p>
        </div>
      </CardBody>

      <Modal
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        title="Edit Health Background"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="edit-health-background-form" type="submit" isLoading={updateServiceUser.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-health-background-form" onSubmit={handleSave}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Resuscitation" htmlFor="hb-resus">
            <Select
              id="hb-resus"
              value={draft.resuscitation_status}
              onChange={(e) => setDraft({ ...draft, resuscitation_status: e.target.value })}
            >
              <option value="">Not recorded</option>
              {RESUSCITATION_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {RESUSCITATION_LABELS[s]}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="DNACPR, ReSPECT and advance decisions" htmlFor="hb-resus-notes">
            <Textarea
              id="hb-resus-notes"
              placeholder="Who agreed it and when, and where the signed form is kept"
              value={draft.resuscitation_notes}
              onChange={(e) => setDraft({ ...draft, resuscitation_notes: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Smoking" htmlFor="hb-smoking">
              <Select id="hb-smoking" value={draft.smoking_status} onChange={(e) => setDraft({ ...draft, smoking_status: e.target.value })}>
                <option value="">Not recorded</option>
                {SMOKING_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {SMOKING_LABELS[s]}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Alcohol" htmlFor="hb-alcohol">
              <Input
                id="hb-alcohol"
                placeholder="e.g. Occasional — 2 units a week"
                value={draft.alcohol_intake}
                onChange={(e) => setDraft({ ...draft, alcohol_intake: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Summary" htmlFor="hb-summary">
            <Textarea
              id="hb-summary"
              rows={5}
              placeholder="Their health story in a few lines — main conditions, what's changed recently, what matters for their care"
              value={draft.medical_history_summary}
              onChange={(e) => setDraft({ ...draft, medical_history_summary: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>
    </Card>
  );
}

/**
 * "None recorded" and "none" aren't the same thing for allergies — this
 * shows which it is, and lets staff confirm (or undo) no known allergies.
 */
function AllergyStatus({ serviceUser, hasCurrentAllergies }: { serviceUser: ServiceUser; hasCurrentAllergies: boolean }) {
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [error, setError] = useState<string | null>(null);

  if (hasCurrentAllergies) return null;

  async function setNoKnownAllergies(value: boolean) {
    setError(null);
    try {
      await updateServiceUser.mutateAsync({ no_known_allergies: value });
    } catch (err) {
      setError(apiErrorMessage(err, "Could not update allergy status. Please try again."));
    }
  }

  return (
    <div className="mb-3 space-y-2">
      {error && <Alert tone="danger">{error}</Alert>}
      {serviceUser.no_known_allergies ? (
        <Alert tone="success">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span>No known allergies — confirmed.</span>
            <button
              type="button"
              className="font-medium underline"
              onClick={() => setNoKnownAllergies(false)}
              disabled={updateServiceUser.isPending}
            >
              Undo
            </button>
          </div>
        </Alert>
      ) : (
        <Alert tone="warning" title="Allergy status not recorded">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span>Add any allergies below, or confirm they have none. Medication shouldn't be given until this is known.</span>
            <Button variant="secondary" onClick={() => setNoKnownAllergies(true)} isLoading={updateServiceUser.isPending}>
              Confirm No Known Allergies
            </Button>
          </div>
        </Alert>
      )}
    </div>
  );
}

function HistorySection({
  category,
  serviceUser,
  entries,
  isLoading,
  onAdd,
  onEdit,
  onResolve,
  onDelete,
  canDelete,
}: {
  category: MedicalHistoryCategory;
  serviceUser: ServiceUser;
  entries: MedicalHistoryEntry[];
  isLoading: boolean;
  onAdd: () => void;
  onEdit: (entry: MedicalHistoryEntry) => void;
  onResolve: (entry: MedicalHistoryEntry) => void;
  onDelete: (entry: MedicalHistoryEntry) => void;
  canDelete: (entry: MedicalHistoryEntry) => boolean;
}) {
  const config = CATEGORY_CONFIG[category];
  const rows = sortEntries(entries);

  const columns: Column<MedicalHistoryEntry>[] = [
    ...entryColumns(config).map((column) => ({
      key: column.key,
      header: column.label,
      render: (entry: MedicalHistoryEntry) => {
        if (column.key === "name") return <span className="font-medium text-ink">{entry.name}</span>;
        if (column.key === "status" && entry.status) {
          return <StatusBadge label={STATUS_LABELS[entry.status]} tone={STATUS_TONE[entry.status]} />;
        }
        if (column.key === "severity" && entry.severity) {
          return <StatusBadge label={SEVERITY_LABELS[entry.severity]} tone={SEVERITY_TONE[entry.severity]} />;
        }
        if (column.key === "notes" || column.key === "reaction") {
          return <span className="block max-w-xs whitespace-pre-line">{column.text(entry)}</span>;
        }
        return column.text(entry);
      },
    })),
    {
      key: "actions",
      header: "",
      render: (entry) => (
        <RowActionsMenu
          label={`Actions for ${entry.name}`}
          actions={[
            { label: "Edit", onClick: () => onEdit(entry) },
            {
              label: "Mark resolved",
              onClick: () => onResolve(entry),
              hidden: !config.statuses || entry.status === "resolved",
            },
            { label: "Remove", tone: "danger", onClick: () => onDelete(entry), hidden: !canDelete(entry) },
          ]}
        />
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>
            {config.title}
            {entries.length > 0 && <span className="ml-2 text-sm font-normal text-inksoft">{entries.length}</span>}
          </span>
          <Button variant="secondary" onClick={onAdd}>
            Add {config.noun}
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {category === "allergy" && !isLoading && (
          <AllergyStatus serviceUser={serviceUser} hasCurrentAllergies={entries.some((e) => e.status !== "resolved")} />
        )}
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(entry) => entry.id}
          isLoading={isLoading}
          emptyMessage={config.emptyMessage}
        />
      </CardBody>
    </Card>
  );
}

interface EntryDraft {
  name: string;
  status: MedicalHistoryStatus;
  severity: AllergySeverity | "";
  allergy_type: AllergyType | "";
  reaction: string;
  relationship: string;
  started_on: string;
  ended_on: string;
  provider: string;
  notes: string;
}

function EntryModal({
  serviceUserId,
  category,
  entry,
  onClose,
}: {
  serviceUserId: number;
  category: MedicalHistoryCategory;
  entry: MedicalHistoryEntry | null;
  onClose: () => void;
}) {
  const config = CATEGORY_CONFIG[category];
  const createEntry = useCreateMedicalHistoryEntry(serviceUserId);
  const updateEntry = useUpdateMedicalHistoryEntry(serviceUserId);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState<EntryDraft>({
    name: entry?.name ?? "",
    status: entry?.status ?? "active",
    severity: entry?.severity ?? "",
    allergy_type: entry?.allergy_type ?? "",
    reaction: entry?.reaction ?? "",
    relationship: entry?.relationship ?? "",
    started_on: entry?.started_on ?? "",
    ended_on: entry?.ended_on ?? "",
    provider: entry?.provider ?? "",
    notes: entry?.notes ?? "",
  });

  const showEnded = Boolean(config.endedLabel) && (!config.endedWhenResolved || draft.status === "resolved");
  const isSaving = createEntry.isPending || updateEntry.isPending;
  const title = `${entry ? "Edit" : "Add"} ${config.noun}`;
  const suggestions = config.suggestions ? tenantSettings().reference_data[config.suggestions] : null;

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);

    // Only what this category records — the API clears the rest anyway.
    const input: MedicalHistoryEntryInput = {
      category,
      name: draft.name.trim(),
      status: config.statuses ? draft.status : null,
      severity: config.isAllergy ? draft.severity || null : null,
      allergy_type: config.isAllergy ? draft.allergy_type || null : null,
      reaction: config.isAllergy ? draft.reaction || null : null,
      relationship: config.relationshipLabel ? draft.relationship || null : null,
      started_on: config.startedLabel ? draft.started_on || null : null,
      ended_on: showEnded ? draft.ended_on || null : null,
      provider: config.providerLabel ? draft.provider || null : null,
      notes: draft.notes || null,
    };

    try {
      if (entry) {
        await updateEntry.mutateAsync({ id: entry.id, ...input });
      } else {
        await createEntry.mutateAsync(input);
      }
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save this entry. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={title}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="medical-history-entry-form" type="submit" isLoading={isSaving}>
            Save
          </Button>
        </>
      }
    >
      <form id="medical-history-entry-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <FormField label={config.nameLabel} htmlFor="mh-name">
          <Input
            id="mh-name"
            required
            maxLength={255}
            list={suggestions ? "mh-name-options" : undefined}
            value={draft.name}
            onChange={(e) => setDraft({ ...draft, name: e.target.value })}
          />
          {suggestions && <Suggestions id="mh-name-options" items={suggestions} />}
        </FormField>

        {config.isAllergy && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Type" htmlFor="mh-allergy-type">
              <Select
                id="mh-allergy-type"
                value={draft.allergy_type}
                onChange={(e) => setDraft({ ...draft, allergy_type: e.target.value as AllergyType | "" })}
              >
                <option value="">Not recorded</option>
                {ALLERGY_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {ALLERGY_TYPE_LABELS[t]}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Severity" htmlFor="mh-severity">
              <Select
                id="mh-severity"
                value={draft.severity}
                onChange={(e) => setDraft({ ...draft, severity: e.target.value as AllergySeverity | "" })}
              >
                <option value="">Not recorded</option>
                {ALLERGY_SEVERITIES.map((s) => (
                  <option key={s} value={s}>
                    {SEVERITY_LABELS[s]}
                  </option>
                ))}
              </Select>
            </FormField>
          </div>
        )}
        {config.isAllergy && (
          <FormField label="Reaction" htmlFor="mh-reaction">
            <Textarea
              id="mh-reaction"
              placeholder="What happens — e.g. rash, swelling, difficulty breathing"
              value={draft.reaction}
              onChange={(e) => setDraft({ ...draft, reaction: e.target.value })}
            />
          </FormField>
        )}

        {config.statuses && (
          <FormField label="Status" htmlFor="mh-status">
            <Select
              id="mh-status"
              value={draft.status}
              onChange={(e) => setDraft({ ...draft, status: e.target.value as MedicalHistoryStatus })}
            >
              {config.statuses.map((s) => (
                <option key={s} value={s}>
                  {STATUS_LABELS[s]}
                </option>
              ))}
            </Select>
          </FormField>
        )}

        {config.relationshipLabel && (
          <FormField label={config.relationshipLabel} htmlFor="mh-relationship">
            <Input
              id="mh-relationship"
              list="mh-relationship-options"
              value={draft.relationship}
              onChange={(e) => setDraft({ ...draft, relationship: e.target.value })}
            />
            <Suggestions id="mh-relationship-options" items={FAMILY_RELATIONSHIPS} />
          </FormField>
        )}

        {(config.startedLabel || showEnded) && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {config.startedLabel && (
              <FormField label={config.startedLabel} htmlFor="mh-started">
                <Input
                  id="mh-started"
                  type="date"
                  max={todayIso()}
                  value={draft.started_on}
                  onChange={(e) => setDraft({ ...draft, started_on: e.target.value })}
                />
              </FormField>
            )}
            {showEnded && config.endedLabel && (
              <FormField label={config.endedLabel} htmlFor="mh-ended">
                <Input
                  id="mh-ended"
                  type="date"
                  min={draft.started_on || undefined}
                  value={draft.ended_on}
                  onChange={(e) => setDraft({ ...draft, ended_on: e.target.value })}
                />
              </FormField>
            )}
          </div>
        )}

        {config.providerLabel && (
          <FormField label={config.providerLabel} htmlFor="mh-provider">
            <Input
              id="mh-provider"
              maxLength={255}
              value={draft.provider}
              onChange={(e) => setDraft({ ...draft, provider: e.target.value })}
            />
          </FormField>
        )}

        <FormField label={config.notesLabel} htmlFor="mh-notes">
          <Textarea id="mh-notes" value={draft.notes} onChange={(e) => setDraft({ ...draft, notes: e.target.value })} />
        </FormField>

        {entry && (
          <p className="text-xs text-inksoft">
            Recorded {formatDate(entry.created_at)}
            {entry.recorded_by_name ? ` by ${entry.recorded_by_name}` : ""}
          </p>
        )}
      </form>
    </Modal>
  );
}

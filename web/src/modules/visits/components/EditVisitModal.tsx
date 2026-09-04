import { useEffect, useState, type FormEvent } from "react";
import { Alert, Button, FormField, Input, Modal, Select, TagInput } from "../../../design-system";
import { useStaff } from "../../staff/api";
import { VISIT_PRIORITIES, deliversVisits, type Visit } from "../../../lib/types";
import { useUpdateVisit, type UpdateVisitInput } from "../api";

function visitToEditForm(visit: Visit): UpdateVisitInput {
  return {
    carer_id: visit.carer_id,
    visit_date: visit.visit_date,
    start_time: visit.start_time,
    end_time: visit.end_time,
    care_tasks: visit.care_tasks,
    medication_tasks: visit.medication_tasks,
    required_skills: visit.required_skills,
    priority: visit.priority,
    status: visit.status,
    notes: visit.notes ?? "",
  };
}

function errorMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? "Something went wrong. Please try again.";
}

export function EditVisitModal({ visit, onClose }: { visit: Visit | null; onClose: () => void }) {
  const [editForm, setEditForm] = useState<UpdateVisitInput>({});
  const [error, setError] = useState<string | null>(null);
  const { data: staff } = useStaff(1, 100);
  const updateVisit = useUpdateVisit(visit?.id ?? 0);

  useEffect(() => {
    if (visit) {
      setEditForm(visitToEditForm(visit));
      setError(null);
    }
  }, [visit]);

  async function handleEdit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateVisit.mutateAsync(editForm);
    } catch (err) {
      setError(errorMessage(err));
      return;
    }
    onClose();
  }

  return (
    <Modal
      isOpen={Boolean(visit)}
      onClose={onClose}
      title="Edit Visit"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="edit-visit-form" type="submit" isLoading={updateVisit.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="edit-visit-form" onSubmit={handleEdit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <FormField label="Carer" htmlFor="edit-visit-carer">
          <Select
            id="edit-visit-carer"
            value={editForm.carer_id ?? ""}
            onChange={(e) => setEditForm({ ...editForm, carer_id: e.target.value ? Number(e.target.value) : null })}
          >
            <option value="">Unassigned</option>
            {(staff?.data ?? [])
              // Offer the people who deliver visits, but never drop whoever is
              // already assigned — otherwise editing an unrelated field on their
              // visit would silently unassign them.
              .filter((s) => deliversVisits(s.roles) || s.user_id === visit?.carer_id)
              .map((s) => (
                <option key={s.id} value={s.user_id}>
                  {s.name}
                </option>
              ))}
          </Select>
        </FormField>
        <FormField label="Visit date" htmlFor="edit-visit-date">
          <Input
            id="edit-visit-date"
            type="date"
            required
            value={editForm.visit_date ?? ""}
            onChange={(e) => setEditForm({ ...editForm, visit_date: e.target.value })}
          />
        </FormField>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField label="Start time" htmlFor="edit-visit-start">
            <Input
              id="edit-visit-start"
              type="time"
              required
              value={editForm.start_time ?? ""}
              onChange={(e) => setEditForm({ ...editForm, start_time: e.target.value })}
            />
          </FormField>
          <FormField label="End time" htmlFor="edit-visit-end">
            <Input
              id="edit-visit-end"
              type="time"
              required
              value={editForm.end_time ?? ""}
              onChange={(e) => setEditForm({ ...editForm, end_time: e.target.value })}
            />
          </FormField>
        </div>
        <FormField label="Priority" htmlFor="edit-visit-priority">
          <Select
            id="edit-visit-priority"
            value={editForm.priority ?? "medium"}
            onChange={(e) => setEditForm({ ...editForm, priority: e.target.value as UpdateVisitInput["priority"] })}
          >
            {VISIT_PRIORITIES.map((p) => (
              <option key={p} value={p}>
                {p}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Status" htmlFor="edit-visit-status">
          <Select
            id="edit-visit-status"
            value={editForm.status ?? "scheduled"}
            onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
          >
            <option value="scheduled">Scheduled</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
            <option value="missed">Missed</option>
            <option value="cancelled">Cancelled</option>
          </Select>
        </FormField>
        <FormField label="Care tasks" htmlFor="edit-visit-tasks">
          <TagInput
            id="edit-visit-tasks"
            value={editForm.care_tasks ?? []}
            onChange={(care_tasks) => setEditForm({ ...editForm, care_tasks })}
            placeholder="e.g. Morning wash"
          />
        </FormField>
        <FormField label="Notes" htmlFor="edit-visit-notes">
          <Input
            id="edit-visit-notes"
            value={editForm.notes ?? ""}
            onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
          />
        </FormField>
      </form>
    </Modal>
  );
}

import { useState, type FormEvent } from "react";
import { Alert, Button, Checkbox, EmptyState, FormField, Input, Modal, StatusBadge, Textarea } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { COMMUNICATION_ROLES } from "../../../lib/types";
import { useAnnouncements, useCreateAnnouncement, type CreateAnnouncementInput } from "../api";

const EMPTY_FORM: CreateAnnouncementInput = { title: "", body: "" };

export function AnnouncementsPage() {
  const { hasAnyRole } = useAuth();
  const canPost = hasAnyRole(COMMUNICATION_ROLES);

  const { data: announcements, isLoading } = useAnnouncements();
  const createAnnouncement = useCreateAnnouncement();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [form, setForm] = useState<CreateAnnouncementInput>(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createAnnouncement.mutateAsync(form);
      setIsCreateOpen(false);
      setForm(EMPTY_FORM);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not post the announcement. Please try again."));
    }
  }

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-ink">Announcements</h1>
            <p className="mt-1 text-sm text-inksoft">Organization-wide and branch news.</p>
          </div>
          {canPost && <Button onClick={() => setIsCreateOpen(true)}>New Announcement</Button>}
        </div>
      </div>

      {!isLoading && (announcements ?? []).length === 0 ? (
        <EmptyState message="No announcements yet." />
      ) : (
        <div className="space-y-3">
          {(announcements ?? []).map((announcement) => (
            <div key={announcement.id} className="rounded-2xl border border-line bg-white p-5">
              <div className="flex items-start justify-between gap-3">
                <h2 className="font-semibold text-ink">{announcement.title}</h2>
                {announcement.pinned && <StatusBadge label="Pinned" tone="info" />}
              </div>
              <p className="mt-2 whitespace-pre-wrap text-sm text-inksoft">{announcement.body}</p>
              <p className="mt-3 text-xs text-inksoft">
                {announcement.posted_by_name ?? "Unknown"} · {new Date(announcement.created_at).toLocaleDateString()}
                {announcement.branch_name && ` · ${announcement.branch_name}`}
              </p>
            </div>
          ))}
        </div>
      )}

      <Modal
        isOpen={isCreateOpen}
        onClose={() => {
          setIsCreateOpen(false);
          setError(null);
        }}
        title="New Announcement"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCreateOpen(false)}>
              Cancel
            </Button>
            <Button form="new-announcement-form" type="submit" isLoading={createAnnouncement.isPending}>
              Post
            </Button>
          </>
        }
      >
        <form id="new-announcement-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Title" htmlFor="announcement-title">
            <Input
              id="announcement-title"
              required
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
            />
          </FormField>
          <FormField label="Message" htmlFor="announcement-body">
            <Textarea
              id="announcement-body"
              required
              value={form.body}
              onChange={(e) => setForm({ ...form, body: e.target.value })}
            />
          </FormField>
          <div className="mt-2">
            <Checkbox
              id="announcement-pinned"
              label="Pin to top"
              checked={Boolean(form.pinned)}
              onChange={(e) => setForm({ ...form, pinned: e.target.checked })}
            />
          </div>
        </form>
      </Modal>
    </div>
  );
}

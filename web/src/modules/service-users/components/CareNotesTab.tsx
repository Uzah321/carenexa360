import { Mic, Square, Trash2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, Input } from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useAuth } from "../../../lib/auth-context";
import { CARE_NOTE_MODERATOR_ROLES, type CareNote } from "../../../lib/types";
import { careNoteAudioUrl, useDeleteCareNote, useRecordCareNote, useServiceUserCareNotes } from "../../care-notes/api";

const PREFERRED_MIME_TYPES = ["audio/webm", "audio/mp4", "audio/ogg"];

function pickSupportedMimeType(): string | undefined {
  if (typeof MediaRecorder === "undefined") return undefined;
  return PREFERRED_MIME_TYPES.find((type) => MediaRecorder.isTypeSupported(type));
}

function formatDuration(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, "0")}`;
}

export function CareNotesTab({ serviceUserId }: { serviceUserId: number }) {
  const { user, hasAnyRole } = useAuth();
  const { data: notes, isLoading } = useServiceUserCareNotes(serviceUserId);
  const recordNote = useRecordCareNote(serviceUserId);
  const deleteNote = useDeleteCareNote(serviceUserId);

  const canRecordInBrowser =
    typeof navigator !== "undefined" && Boolean(navigator.mediaDevices?.getUserMedia) && typeof MediaRecorder !== "undefined";

  const [isRecording, setIsRecording] = useState(false);
  const [elapsedSeconds, setElapsedSeconds] = useState(0);
  const [recordedBlob, setRecordedBlob] = useState<Blob | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [caption, setCaption] = useState("");
  const [micError, setMicError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [deletingNote, setDeletingNote] = useState<CareNote | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);
  const streamRef = useRef<MediaStream | null>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      streamRef.current?.getTracks().forEach((track) => track.stop());
      if (timerRef.current) clearInterval(timerRef.current);
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    };
    // Cleanup only, deliberately not re-run on every previewUrl change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function startRecording() {
    setMicError(null);
    setSaveError(null);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      streamRef.current = stream;
      chunksRef.current = [];

      const mimeType = pickSupportedMimeType();
      const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
      recorder.ondataavailable = (e) => {
        if (e.data.size > 0) chunksRef.current.push(e.data);
      };
      recorder.onstop = () => {
        const blob = new Blob(chunksRef.current, { type: mimeType ?? "audio/webm" });
        setRecordedBlob(blob);
        setPreviewUrl(URL.createObjectURL(blob));
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
      };

      mediaRecorderRef.current = recorder;
      recorder.start();
      setIsRecording(true);
      setElapsedSeconds(0);
      timerRef.current = setInterval(() => setElapsedSeconds((s) => s + 1), 1000);
    } catch {
      setMicError("Couldn't access the microphone — you can still upload a recorded audio file below.");
    }
  }

  function stopRecording() {
    mediaRecorderRef.current?.stop();
    setIsRecording(false);
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
  }

  function discardRecording() {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setRecordedBlob(null);
    setPreviewUrl(null);
    setCaption("");
    setElapsedSeconds(0);
  }

  function handleFileSelected(file: File) {
    setMicError(null);
    setSaveError(null);
    setRecordedBlob(file);
    setPreviewUrl(URL.createObjectURL(file));
  }

  async function handleSave() {
    if (!recordedBlob) return;
    setSaveError(null);
    try {
      await recordNote.mutateAsync({
        audio: recordedBlob,
        caption: caption.trim() || undefined,
        duration_seconds: elapsedSeconds > 0 ? elapsedSeconds : undefined,
      });
      discardRecording();
    } catch (err) {
      setSaveError(apiErrorMessage(err, "Could not save this note. Please try again."));
    }
  }

  async function handleDelete() {
    if (!deletingNote) return;
    setDeleteError(null);
    try {
      await deleteNote.mutateAsync(deletingNote.id);
      setDeletingNote(null);
    } catch (err) {
      setDeleteError(apiErrorMessage(err, "Could not delete this note. Please try again."));
    }
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>Record a note</CardHeader>
        <CardBody>
          {micError && (
            <div className="mb-3">
              <Alert tone="warning">{micError}</Alert>
            </div>
          )}
          {saveError && (
            <div className="mb-3">
              <Alert tone="danger">{saveError}</Alert>
            </div>
          )}

          {!recordedBlob ? (
            <div className="flex flex-wrap items-center gap-3">
              {canRecordInBrowser && !isRecording && (
                <Button onClick={startRecording}>
                  <Mic className="h-4 w-4" aria-hidden /> Record a note
                </Button>
              )}
              {isRecording && (
                <Button variant="danger" onClick={stopRecording}>
                  <Square className="h-4 w-4" aria-hidden /> Stop ({formatDuration(elapsedSeconds)})
                </Button>
              )}
              {(!canRecordInBrowser || micError) && !isRecording && (
                <label className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper">
                  Upload an audio file
                  <input
                    type="file"
                    accept="audio/*"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) handleFileSelected(file);
                      e.target.value = "";
                    }}
                  />
                </label>
              )}
            </div>
          ) : (
            <div className="space-y-3">
              <audio controls src={previewUrl ?? undefined} className="w-full" />
              <Input
                placeholder="Add a short caption (optional)"
                value={caption}
                onChange={(e) => setCaption(e.target.value)}
                maxLength={255}
              />
              <div className="flex gap-2">
                <Button onClick={handleSave} isLoading={recordNote.isPending}>
                  Save note
                </Button>
                <Button variant="secondary" onClick={discardRecording} disabled={recordNote.isPending}>
                  Discard
                </Button>
              </div>
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Notes</CardHeader>
        <CardBody>
          {!isLoading && (notes ?? []).length === 0 ? (
            <EmptyState message="No voice notes recorded yet." />
          ) : (
            <ul className="space-y-3">
              {(notes ?? []).map((note) => {
                const canDelete = note.author_id === user?.id || hasAnyRole(CARE_NOTE_MODERATOR_ROLES);
                return (
                  <li key={note.id} className="rounded-xl border border-line p-3">
                    <div className="mb-2 flex items-start justify-between gap-3">
                      <div>
                        <p className="text-sm font-medium text-ink">{note.author_name ?? "Unknown"}</p>
                        <p className="text-xs text-inksoft">
                          {new Date(note.created_at).toLocaleString()}
                          {note.duration_seconds != null && ` · ${formatDuration(note.duration_seconds)}`}
                        </p>
                      </div>
                      {canDelete && (
                        <button
                          type="button"
                          aria-label="Delete note"
                          className="text-inksoft hover:text-coral"
                          onClick={() => setDeletingNote(note)}
                        >
                          <Trash2 className="h-4 w-4" aria-hidden />
                        </button>
                      )}
                    </div>
                    {note.caption && <p className="mb-2 text-sm text-ink">{note.caption}</p>}
                    <audio controls src={careNoteAudioUrl(note.id)} className="w-full" />
                  </li>
                );
              })}
            </ul>
          )}
        </CardBody>
      </Card>

      <ConfirmDialog
        isOpen={Boolean(deletingNote)}
        title="Delete note"
        message="Delete this voice note? This cannot be undone."
        confirmLabel="Delete"
        tone="danger"
        isLoading={deleteNote.isPending}
        error={deleteError}
        onConfirm={handleDelete}
        onCancel={() => {
          setDeletingNote(null);
          setDeleteError(null);
        }}
      />
    </div>
  );
}

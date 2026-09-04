import { Alert } from "./Alert";
import { Button } from "./form/Button";
import { Modal } from "./Modal";

interface ConfirmDialogProps {
  isOpen: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  tone?: "default" | "danger";
  isLoading?: boolean;
  /**
   * Set when a previous confirm attempt failed — the caller keeps the dialog
   * open and passes the reason here rather than the confirm click silently
   * closing nothing and leaving no trace of what went wrong.
   */
  error?: string | null;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  isOpen,
  title,
  message,
  confirmLabel = "Confirm",
  tone = "default",
  isLoading = false,
  error,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  return (
    <Modal
      isOpen={isOpen}
      onClose={onCancel}
      title={title}
      footer={
        <>
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
          <Button variant={tone === "danger" ? "danger" : "primary"} onClick={onConfirm} isLoading={isLoading}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      {error && (
        <div className="mb-3">
          <Alert tone="danger">{error}</Alert>
        </div>
      )}
      <p className="text-sm text-inksoft">{message}</p>
    </Modal>
  );
}

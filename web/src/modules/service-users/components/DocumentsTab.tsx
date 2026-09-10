import { useState } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FileUpload,
  FormField,
  Input,
  RowActionsMenu,
  type Column,
  type RowAction,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { downloadDocument, useDeleteDocument, useServiceUserDocuments, useUploadDocument } from "../../documents/api";
import type { CareDocument } from "../../../lib/types";

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function DocumentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: documents, isLoading } = useServiceUserDocuments(serviceUserId);
  const upload = useUploadDocument(serviceUserId);
  const deleteDocument = useDeleteDocument(serviceUserId);

  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [category, setCategory] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<CareDocument | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function handleUpload() {
    if (!pendingFile) return;
    setError(null);
    try {
      await upload.mutateAsync({ file: pendingFile, category: category || undefined });
      setPendingFile(null);
      setCategory("");
    } catch (err) {
      setError(apiErrorMessage(err, "Could not upload this file. Please try again."));
    }
  }

  async function handleConfirmDelete() {
    if (!deleteTarget) return;
    setDeleteError(null);
    try {
      await deleteDocument.mutateAsync(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(apiErrorMessage(err, "Could not delete this document. Please try again."));
    }
  }

  const columns: Column<CareDocument>[] = [
    { key: "filename", header: "File", render: (row) => row.original_filename },
    { key: "category", header: "Category", render: (row) => row.category ?? "—" },
    { key: "size", header: "Size", render: (row) => formatSize(row.size) },
    { key: "uploaded_by", header: "Uploaded By", render: (row) => row.uploaded_by_name ?? "—" },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (row) => {
        const actions: RowAction[] = [
          { label: "Download", onClick: () => downloadDocument(row.id, row.original_filename) },
          { label: "Delete", onClick: () => setDeleteTarget(row), tone: "danger" },
        ];
        return <RowActionsMenu actions={actions} label={`${row.original_filename} actions`} />;
      },
    },
  ];

  return (
    <Card>
      <CardHeader>Documents</CardHeader>
      <CardBody>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <div className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-line p-3">
          <div className="flex-1">
            <FileUpload onSelect={setPendingFile} />
          </div>
          <FormField label="Category" htmlFor="doc-category">
            <Input id="doc-category" value={category} onChange={(e) => setCategory(e.target.value)} />
          </FormField>
          <Button onClick={handleUpload} disabled={!pendingFile} isLoading={upload.isPending}>
            Upload
          </Button>
        </div>

        {!isLoading && (documents ?? []).length === 0 ? (
          <EmptyState message="No documents uploaded yet." />
        ) : (
          <DataTable columns={columns} rows={documents ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <ConfirmDialog
        isOpen={Boolean(deleteTarget)}
        title="Delete document"
        message={`Delete "${deleteTarget?.original_filename}"? This permanently removes the file — this can't be undone.`}
        confirmLabel="Delete"
        tone="danger"
        isLoading={deleteDocument.isPending}
        error={deleteError}
        onConfirm={handleConfirmDelete}
        onCancel={() => {
          setDeleteTarget(null);
          setDeleteError(null);
        }}
      />
    </Card>
  );
}

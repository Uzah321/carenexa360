import { useState } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  DataTable,
  EmptyState,
  FileUpload,
  FormField,
  Input,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { downloadDocument, useServiceUserDocuments, useUploadDocument } from "../../documents/api";
import type { CareDocument } from "../../../lib/types";

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function DocumentsTab({ serviceUserId }: { serviceUserId: number }) {
  const { data: documents, isLoading } = useServiceUserDocuments(serviceUserId);
  const upload = useUploadDocument(serviceUserId);

  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [category, setCategory] = useState("");
  const [error, setError] = useState<string | null>(null);

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

  const columns: Column<CareDocument>[] = [
    { key: "filename", header: "File", render: (row) => row.original_filename },
    { key: "category", header: "Category", render: (row) => row.category ?? "—" },
    { key: "size", header: "Size", render: (row) => formatSize(row.size) },
    { key: "uploaded_by", header: "Uploaded By", render: (row) => row.uploaded_by_name ?? "—" },
    {
      key: "actions",
      header: "",
      render: (row) => (
        <button
          type="button"
          className="text-sm font-medium text-teal hover:text-teal/90"
          onClick={() => downloadDocument(row.id, row.original_filename)}
        >
          Download
        </button>
      ),
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
    </Card>
  );
}

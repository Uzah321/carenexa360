import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, Download } from "lucide-react";
import { Alert, Button, Card, CardBody, CardHeader, StatusBadge } from "../../../design-system";
import { apiClient } from "../../../lib/api-client";
import { apiErrorMessage } from "../../../lib/api-error";

interface DataCheck {
  key: string;
  label: string;
  description: string;
  severity: "danger" | "warning" | "info";
  count: number;
  sample: { label: string; link: string | null }[];
}

const EXPORTS: { key: string; label: string }[] = [
  { key: "service_users", label: "Clients" },
  { key: "staff", label: "Staff" },
  { key: "visits", label: "Visits" },
  { key: "medications", label: "Medications" },
  { key: "medication_administrations", label: "Medication records (MAR)" },
  { key: "observations", label: "Observations" },
  { key: "incidents", label: "Incidents" },
  { key: "complaints", label: "Complaints" },
];

function useDataChecks() {
  return useQuery({
    queryKey: ["data-maintenance", "checks"],
    queryFn: async () => (await apiClient.get<{ data: DataCheck[] }>("/data-maintenance/checks")).data.data,
  });
}

/** Checks that find gaps and stale records, each linking to where it's fixed, plus CSV exports. */
export function DataMaintenanceTab() {
  const { data: checks, isLoading, refetch, isFetching } = useDataChecks();
  const [downloading, setDownloading] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);

  async function download(dataset: string) {
    setDownloading(dataset);
    setExportError(null);
    try {
      const response = await apiClient.get<Blob>(`/data-maintenance/exports/${dataset}`, { responseType: "blob" });
      const filename = /filename="?([^";]+)"?/.exec(String(response.headers["content-disposition"] ?? ""))?.[1] ?? `${dataset}.csv`;
      const url = URL.createObjectURL(response.data);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (err) {
      setExportError(apiErrorMessage(err, "Could not export that data. Please try again."));
    } finally {
      setDownloading(null);
    }
  }

  const issues = (checks ?? []).filter((c) => c.count > 0);
  const clean = (checks ?? []).filter((c) => c.count === 0);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span>Data quality checks</span>
            <Button variant="secondary" onClick={() => refetch()} isLoading={isFetching}>
              Run checks again
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <p className="text-sm text-inksoft">Checking your records…</p>
          ) : (
            <>
              {issues.length === 0 && <Alert tone="success">No problems found — your records are complete.</Alert>}
              <ul className="space-y-3">
                {issues.map((check) => (
                  <li key={check.key} className="rounded-xl border border-line p-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="font-semibold text-ink">{check.label}</p>
                        <p className="text-sm text-inksoft">{check.description}</p>
                      </div>
                      <StatusBadge label={String(check.count)} tone={check.severity} />
                    </div>
                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                      {check.sample.map((item, i) =>
                        item.link ? (
                          <Link key={i} to={item.link} className="font-medium text-teal hover:text-teal/90">
                            {item.label}
                          </Link>
                        ) : (
                          <span key={i} className="text-ink">
                            {item.label}
                          </span>
                        ),
                      )}
                      {check.count > check.sample.length && <span className="text-inksoft">and {check.count - check.sample.length} more</span>}
                    </div>
                  </li>
                ))}
              </ul>
              {clean.length > 0 && (
                <div className="mt-4">
                  <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-inksoft">All clear</p>
                  <ul className="grid grid-cols-1 gap-1.5 text-sm text-inksoft sm:grid-cols-2">
                    {clean.map((check) => (
                      <li key={check.key} className="flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-lime" aria-hidden />
                        {check.label}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Export data</CardHeader>
        <CardBody>
          <p className="mb-4 text-sm text-inksoft">
            Download your organisation's records as CSV files that open in Excel — for backups, audits or subject access requests.
            Times are in your organisation's timezone.
          </p>
          {exportError && (
            <div className="mb-4">
              <Alert tone="danger">{exportError}</Alert>
            </div>
          )}
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {EXPORTS.map((dataset) => (
              <Button key={dataset.key} variant="secondary" onClick={() => download(dataset.key)} isLoading={downloading === dataset.key}>
                <Download className="mr-1.5 h-4 w-4" aria-hidden />
                {dataset.label}
              </Button>
            ))}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

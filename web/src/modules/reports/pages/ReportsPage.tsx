import { Download } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Alert, Card, CardBody, CardHeader, DataTable, EmptyState, PageHeader, Select, type Column } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { useBranches } from "../../organization/api";
import { useGenerateReport } from "../api";
import { REPORT_CATALOG, type ReportDef } from "../reportCatalog";
import { startOfMonthIso, todayIso } from "../../../lib/dates";

function firstAvailable(reports: ReportDef[]): ReportDef | null {
  return reports.find((r) => r.key) ?? null;
}

export function ReportsPage() {
  const { user } = useAuth();
  const [categoryKey, setCategoryKey] = useState(REPORT_CATALOG[0].key);
  const category = REPORT_CATALOG.find((c) => c.key === categoryKey) ?? REPORT_CATALOG[0];

  const [selectedLabel, setSelectedLabel] = useState<string | null>(firstAvailable(category.reports)?.label ?? null);
  const selectedReport = category.reports.find((r) => r.label === selectedLabel) ?? null;

  const [from, setFrom] = useState(startOfMonthIso);
  const [to, setTo] = useState(todayIso);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [activeKey, setActiveKey] = useState<string | null>(null);

  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const { data: report, isLoading, isFetching, isError } = useGenerateReport(activeKey, from, to, branchId);

  // Switching category resets to that category's first real report and
  // clears whatever was previously generated — a stale report from a
  // different category shouldn't linger on screen.
  useEffect(() => {
    setSelectedLabel(firstAvailable(category.reports)?.label ?? null);
    setActiveKey(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [categoryKey]);

  const columns: Column<Record<string, string | number>>[] = useMemo(
    () =>
      (report?.columns ?? []).map((col) => ({
        key: col.key,
        header: col.label,
        render: (row) => row[col.key] ?? "—",
      })),
    [report],
  );

  const branchName = branchId ? (branches?.data ?? []).find((b) => b.id === branchId)?.name : null;

  // Report rows have no stable id of their own — tag each with a synthetic
  // index key for React/DataTable's benefit. Columns are rendered from
  // `report.columns` only, so this extra field never shows up as data.
  const keyedRows = useMemo(
    () => (report?.rows ?? []).map((row, index) => ({ ...row, __rowKey: index })),
    [report],
  );

  return (
    <div>
      <div className="print:hidden">
        <PageHeader
          title="Reports"
          description="Choose a report, filter by date, generate it, then download a PDF."
        />

        <Card className="mb-4">
          <CardBody>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <div>
                <label className="mb-1 block text-xs font-semibold text-inksoft">Category</label>
                <Select value={categoryKey} onChange={(e) => setCategoryKey(e.target.value)}>
                  {REPORT_CATALOG.map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </Select>
              </div>
              <div>
                <label className="mb-1 block text-xs font-semibold text-inksoft">Report</label>
                <Select value={selectedLabel ?? ""} onChange={(e) => setSelectedLabel(e.target.value)}>
                  {category.reports.map((r) => (
                    <option key={r.label} value={r.label} disabled={!r.key}>
                      {r.label}
                      {!r.key ? " (Coming soon)" : ""}
                    </option>
                  ))}
                </Select>
              </div>
              <div>
                <label className="mb-1 block text-xs font-semibold text-inksoft">From</label>
                <input
                  type="date"
                  value={from}
                  onChange={(e) => setFrom(e.target.value)}
                  className="w-full rounded-lg border border-line px-3 py-2 text-sm shadow-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-semibold text-inksoft">To</label>
                <input
                  type="date"
                  value={to}
                  onChange={(e) => setTo(e.target.value)}
                  className="w-full rounded-lg border border-line px-3 py-2 text-sm shadow-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-semibold text-inksoft">Branch</label>
                <Select
                  value={branchId ?? ""}
                  onChange={(e) => setBranchId(e.target.value ? Number(e.target.value) : null)}
                >
                  <option value="">All Branches</option>
                  {(branches?.data ?? []).map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </Select>
              </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
              <button
                type="button"
                disabled={!selectedReport?.key}
                onClick={() => setActiveKey(selectedReport?.key ?? null)}
                className="rounded-full bg-teal px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 hover:bg-teal/90 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Generate Report
              </button>
              {report && (
                <button
                  type="button"
                  onClick={() => window.print()}
                  title="Opens your browser's print dialog — choose 'Save as PDF' as the destination to download it"
                  className="flex items-center gap-1.5 rounded-full bg-tealtint px-4 py-2 text-sm font-semibold text-teal transition-colors duration-150 hover:bg-tealtint/80"
                >
                  <Download className="h-4 w-4" aria-hidden />
                  Download PDF
                </button>
              )}
            </div>

            {!selectedReport?.key && (
              <p className="mt-3 text-sm text-inksoft">This report isn't wired up to real data yet — pick another one from the list.</p>
            )}
          </CardBody>
        </Card>
      </div>

      {isError && (
        <div className="mb-4 print:hidden">
          <Alert tone="danger">Couldn't generate that report. Please try again.</Alert>
        </div>
      )}

      {(isLoading || isFetching) && activeKey && (
        <Card>
          <CardBody>Generating…</CardBody>
        </Card>
      )}

      {report && !isLoading && (
        <Card className="print-avoid-break">
          <CardHeader>
            <div>
              <p className="font-display text-lg font-bold text-ink">{report.title}</p>
              <p className="text-xs text-inksoft">
                {report.filters.from} to {report.filters.to}
                {branchName ? ` · ${branchName}` : ""} · {report.rows.length} record{report.rows.length === 1 ? "" : "s"}
              </p>
            </div>
          </CardHeader>
          <CardBody>
            {report.rows.length === 0 ? (
              <EmptyState message="No data found for this report and date range." />
            ) : (
              <DataTable columns={columns} rows={keyedRows} rowKey={(row) => row.__rowKey} />
            )}
          </CardBody>
        </Card>
      )}

      {!report && !isLoading && !isFetching && (
        <div className="print:hidden">
          <EmptyState message="Choose a report and click Generate Report to see the data." />
        </div>
      )}
    </div>
  );
}

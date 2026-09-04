import {
  Card,
  CardBody,
  CardHeader,
  DataTable,
  PageHeader,
  StatTile,
  TrendLineChart,
  type Column,
} from "../../../design-system";
import { useOperationsDashboard, type OperationsDashboardData } from "../api";

function weekLabel(iso: string): string {
  const d = new Date(`${iso}T00:00:00`);
  return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function money(value: number): string {
  return `$${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

export function OperationsDashboardPage() {
  const { data, isLoading } = useOperationsDashboard();

  const branchColumns: Column<OperationsDashboardData["branches"][number]>[] = [
    { key: "name", header: "Branch", render: (row) => row.name },
    { key: "service_users", header: "Service Users", render: (row) => String(row.service_user_count) },
    { key: "staff", header: "Staff", render: (row) => String(row.staff_count) },
    { key: "visits", header: "Visits month to date", render: (row) => String(row.visits_this_month) },
    { key: "incidents", header: "Open incidents", render: (row) => String(row.open_incidents) },
  ];

  return (
    <div>
      <PageHeader
        title="Operations Dashboard"
        description="Business health and care quality at a glance — headline numbers, 8-week trends, and risk posture."
      />

      {isLoading || !data ? (
        <Card>
          <CardBody>Loading…</CardBody>
        </Card>
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <StatTile
              label="Active service users"
              value={String(data.headline.active_service_users)}
              tone="teal"
            />
            <StatTile label="Active staff" value={String(data.headline.active_staff)} tone="sky" />
            <StatTile
              label="Visits month to date"
              value={String(data.headline.visits_this_month)}
              tone="lime"
            />
            <StatTile
              label="Revenue this month"
              value={money(data.headline.revenue_this_month)}
              tone="teal"
            />
            <StatTile
              label="Outstanding invoices"
              value={money(data.headline.outstanding_invoices_total)}
              tone="amber"
            />
            <StatTile label="Open incidents" value={String(data.headline.open_incidents)} tone="coral" />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>Visits per week</CardHeader>
              <CardBody>
                <TrendLineChart
                  tone="teal"
                  data={data.trends.weeks.map((w, i) => ({ label: weekLabel(w), value: data.trends.visits[i] }))}
                />
              </CardBody>
            </Card>
            <Card>
              <CardHeader>Revenue per week</CardHeader>
              <CardBody>
                <TrendLineChart
                  tone="lime"
                  valueFormatter={money}
                  data={data.trends.weeks.map((w, i) => ({ label: weekLabel(w), value: data.trends.revenue[i] }))}
                />
              </CardBody>
            </Card>
            <Card>
              <CardHeader>Incidents per week</CardHeader>
              <CardBody>
                <TrendLineChart
                  tone="coral"
                  data={data.trends.weeks.map((w, i) => ({ label: weekLabel(w), value: data.trends.incidents[i] }))}
                />
              </CardBody>
            </Card>
            <Card>
              <CardHeader>Rota coverage per week</CardHeader>
              <CardBody>
                <TrendLineChart
                  tone="sky"
                  valueFormatter={(v) => `${v}%`}
                  data={data.trends.weeks.map((w, i) => ({
                    label: weekLabel(w),
                    value: data.trends.rota_coverage_pct[i],
                  }))}
                />
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader>Risk &amp; compliance posture</CardHeader>
            <CardBody>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatTile label="Open incidents" value={String(data.risk.open_incidents)} tone="coral" />
                <StatTile
                  label="Non-compliant"
                  value={String(data.risk.non_compliant_requirements)}
                  description="compliance requirements"
                  tone="amber"
                />
                <StatTile
                  label="Training expiring"
                  value={String(data.risk.training_expiring_soon)}
                  description="within 30 days"
                  tone="amber"
                />
                <StatTile
                  label="Safeguarding"
                  value={String(data.risk.open_safeguarding_cases)}
                  description="open cases"
                  tone="plum"
                />
              </div>
            </CardBody>
          </Card>

          {data.branches.length > 1 && (
            <div>
              <h2 className="mb-2 text-sm font-semibold text-ink">Branch comparison</h2>
              <DataTable columns={branchColumns} rows={data.branches} rowKey={(row) => row.id} />
            </div>
          )}
        </div>
      )}
    </div>
  );
}

import { useState, type ReactNode } from "react";
import { Link } from "react-router-dom";
import { ExternalLink, Globe, Monitor, User as UserIcon } from "lucide-react";
import {
  Alert,
  Button,
  DataTable,
  Drawer,
  FilterBar,
  FormField,
  Input,
  PageHeader,
  Pagination,
  Select,
  StatusBadge,
  type Column,
} from "../../../design-system";
import type { AuditLogChange, AuditLogEntry } from "../../../lib/types";
import { formatDate, formatDateTime, getTenantPreferences } from "../../../lib/preferences";
import { useStaff } from "../../staff/api";
import { useAuditLog, useAuditLogEntry, useAuditRecordTypes, type AuditLogFilters } from "../api";

const ACTION_STYLE = {
  created: { label: "Created", tone: "success" },
  updated: { label: "Updated", tone: "info" },
  deleted: { label: "Deleted", tone: "danger" },
} as const;

const ISO_TIMESTAMP = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** A stored value as a person would read it. */
function displayValue(value: unknown, resolved: string | null): ReactNode {
  if (resolved) return resolved;
  if (value === null || value === undefined || value === "") return <span className="text-inksoft">—</span>;
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (typeof value === "string" && ISO_TIMESTAMP.test(value)) return formatDateTime(value);
  if (typeof value === "string" && ISO_DATE.test(value)) return formatDate(value);
  if (Array.isArray(value)) {
    if (value.length === 0) return <span className="text-inksoft">None</span>;
    if (value.every((v) => typeof v !== "object")) return value.join(", ");
  }
  // Some values were logged as JSON text ("[]", "{\"a\":1}") — show them as the data they are.
  if (typeof value === "string" && /^[[{]/.test(value)) {
    try {
      return displayValue(JSON.parse(value), null);
    } catch {
      // Not JSON after all — falls through to plain text.
    }
  }
  if (typeof value === "object") {
    return <pre className="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-paper p-2 text-xs">{JSON.stringify(value, null, 2)}</pre>;
  }
  const text = String(value);
  // Rich text fields are stored as HTML — show them as plain text here.
  return /<[a-z][\s\S]*>/i.test(text) ? text.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim() : text;
}

/** "3 minutes ago" / "2 days ago". */
function relativeTime(iso: string): string {
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ["year", 31536000],
    ["month", 2592000],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
  ];
  const format = new Intl.RelativeTimeFormat(getTenantPreferences().locale, { numeric: "auto" });
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return format.format(-Math.floor(seconds / size), unit);
  }
  return "just now";
}

function DetailRow({ icon, label, children }: { icon?: ReactNode; label: string; children: ReactNode }) {
  return (
    <div className="flex gap-3 border-b border-line py-2.5 last:border-b-0">
      <dt className="flex w-28 shrink-0 items-start gap-1.5 text-sm text-inksoft">
        {icon}
        {label}
      </dt>
      <dd className="min-w-0 flex-1 text-sm text-ink">{children}</dd>
    </div>
  );
}

function ChangesTable({ action, changes }: { action: AuditLogEntry["action"]; changes: AuditLogChange[] }) {
  if (changes.length === 0) {
    return <p className="text-sm text-inksoft">No field values were recorded for this action.</p>;
  }
  const showBefore = action !== "created";
  const showAfter = action !== "deleted";

  return (
    <div className="overflow-x-auto rounded-xl border border-line">
      <table className="min-w-full divide-y divide-line text-sm">
        <thead className="bg-paper">
          <tr>
            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">Field</th>
            {showBefore && <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">{showAfter ? "Before" : "Value"}</th>}
            {showAfter && <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-inksoft">{showBefore ? "After" : "Value"}</th>}
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {changes.map((change) => (
            <tr key={change.field} className="align-top">
              <td className="whitespace-nowrap px-3 py-2 font-medium text-ink">{change.label}</td>
              {showBefore && <td className={`px-3 py-2 ${showAfter ? "text-inksoft line-through decoration-coral/40" : "text-ink"}`}>{displayValue(change.before, change.before_display)}</td>}
              {showAfter && <td className="px-3 py-2 text-ink">{displayValue(change.after, change.after_display)}</td>}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function AuditEntryDrawer({ entryId, onClose }: { entryId: number | null; onClose: () => void }) {
  const { data: entry, isLoading, isError } = useAuditLogEntry(entryId);
  const [showRaw, setShowRaw] = useState(false);
  const { timezone } = getTenantPreferences();

  return (
    <Drawer isOpen={entryId !== null} onClose={onClose} title="Audit entry">
      {isError && <Alert tone="danger">Could not load this entry.</Alert>}
      {(isLoading || !entry) && !isError && <p className="text-sm text-inksoft">Loading…</p>}
      {entry && (
        <div className="space-y-6">
          <div>
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <StatusBadge label={ACTION_STYLE[entry.action].label} tone={ACTION_STYLE[entry.action].tone} />
              <span className="text-sm text-inksoft">{entry.record_label}</span>
            </div>
            <p className="font-display text-lg font-bold text-ink">{entry.record_name}</p>
            <p className="mt-1 text-sm text-inksoft">
              {entry.user_name ?? "The system"} {entry.action} this {entry.record_label.toLowerCase()}
              {entry.action === "updated" && entry.changes.length > 0 && ` — ${entry.changes.length} field${entry.changes.length === 1 ? "" : "s"} changed`}.
            </p>
            {entry.record_link && (
              <Link to={entry.record_link} className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-teal hover:text-teal/90">
                Open {entry.record_label.toLowerCase()} <ExternalLink className="h-3.5 w-3.5" aria-hidden />
              </Link>
            )}
            {!entry.record_exists && entry.action !== "deleted" && (
              <p className="mt-2 text-xs text-inksoft">This record has since been removed.</p>
            )}
          </div>

          <dl>
            <DetailRow label="When">
              {formatDateTime(entry.created_at)} <span className="text-inksoft">· {relativeTime(entry.created_at)}</span>
              <span className="block text-xs text-inksoft">{timezone} time</span>
            </DetailRow>
            <DetailRow icon={<UserIcon className="mt-0.5 h-3.5 w-3.5" aria-hidden />} label="Who">
              {entry.user_name ?? "System (no signed-in user)"}
              {entry.user_email && <span className="block text-xs text-inksoft">{entry.user_email}</span>}
              {entry.user_roles.length > 0 && <span className="block text-xs text-inksoft">{entry.user_roles.join(", ")}</span>}
            </DetailRow>
            <DetailRow icon={<Globe className="mt-0.5 h-3.5 w-3.5" aria-hidden />} label="IP address">
              {entry.ip_address ?? "—"}
            </DetailRow>
            <DetailRow icon={<Monitor className="mt-0.5 h-3.5 w-3.5" aria-hidden />} label="Device">
              {entry.device ?? "—"}
              {entry.user_agent && <span className="block break-all text-xs text-inksoft">{entry.user_agent}</span>}
            </DetailRow>
            <DetailRow label="Record">
              {entry.record_label} #{entry.auditable_id}
            </DetailRow>
            <DetailRow label="Entry">#{entry.id}</DetailRow>
          </dl>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink">
              {entry.action === "created" ? "Values set" : entry.action === "deleted" ? "Values at deletion" : "What changed"}
            </h3>
            <ChangesTable action={entry.action} changes={entry.changes} />
          </div>

          <div>
            <button type="button" className="text-xs font-medium text-teal hover:text-teal/90" onClick={() => setShowRaw(!showRaw)}>
              {showRaw ? "Hide" : "Show"} raw data
            </button>
            {showRaw && (
              <pre className="mt-2 max-h-80 overflow-auto rounded-lg bg-paper p-3 text-xs text-ink">
                {JSON.stringify({ old_values: entry.old_values, new_values: entry.new_values }, null, 2)}
              </pre>
            )}
          </div>
        </div>
      )}
    </Drawer>
  );
}

const EMPTY_FILTERS: AuditLogFilters = { action: "", record_type: "", from: "", to: "" };

export function AuditLogPage() {
  const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_FILTERS);
  const [page, setPage] = useState(1);
  const [openEntryId, setOpenEntryId] = useState<number | null>(null);
  const { data, isLoading } = useAuditLog({ ...filters, page });
  const { data: recordTypes } = useAuditRecordTypes();
  const { data: staff } = useStaff(1, 500);

  const setFilter = (patch: AuditLogFilters) => {
    setFilters((prev) => ({ ...prev, ...patch }));
    setPage(1);
  };
  const hasFilters = Object.values(filters).some((v) => v !== "" && v !== undefined);

  const columns: Column<AuditLogEntry>[] = [
    {
      key: "created_at",
      header: "When",
      render: (row) => (
        <div>
          <div>{formatDateTime(row.created_at)}</div>
          <div className="text-xs text-inksoft">{relativeTime(row.created_at)}</div>
        </div>
      ),
    },
    { key: "user_name", header: "Who", render: (row) => row.user_name ?? "System" },
    { key: "action", header: "Action", render: (row) => <StatusBadge label={ACTION_STYLE[row.action].label} tone={ACTION_STYLE[row.action].tone} /> },
    {
      key: "record",
      header: "Done To",
      render: (row) => (
        <div className="min-w-0">
          <div className="font-medium text-ink">{row.record_name}</div>
          <div className="text-xs text-inksoft">{row.record_label}</div>
        </div>
      ),
    },
    {
      key: "changes",
      header: "Changed",
      render: (row) =>
        row.action === "updated" && row.changed_fields.length > 0 ? (
          <span className="text-sm text-inksoft">
            {row.changed_fields
              .slice(0, 3)
              .map((f) => f.replace(/_id$/, "").replaceAll("_", " "))
              .join(", ")}
            {row.changed_fields.length > 3 && ` +${row.changed_fields.length - 3} more`}
          </span>
        ) : (
          <span className="text-inksoft">—</span>
        ),
    },
    { key: "ip_address", header: "IP", render: (row) => row.ip_address ?? "—" },
  ];

  return (
    <div>
      <PageHeader title="Audit Log" description="Every important action taken in your organisation. Click an entry to see exactly what changed." />

      <FilterBar>
        <FormField label="Action" htmlFor="audit-action">
          <Select id="audit-action" value={filters.action} onChange={(e) => setFilter({ action: e.target.value })}>
            <option value="">All actions</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
          </Select>
        </FormField>
        <FormField label="Record type" htmlFor="audit-type">
          <Select id="audit-type" value={filters.record_type} onChange={(e) => setFilter({ record_type: e.target.value })}>
            <option value="">All records</option>
            {(recordTypes ?? []).map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="Who" htmlFor="audit-user">
          <Select
            id="audit-user"
            value={filters.user_id ?? ""}
            onChange={(e) => setFilter({ user_id: e.target.value ? Number(e.target.value) : undefined })}
          >
            <option value="">Anyone</option>
            {(staff?.data ?? []).map((s) => (
              <option key={s.id} value={s.user_id}>
                {s.name}
              </option>
            ))}
          </Select>
        </FormField>
        <FormField label="From" htmlFor="audit-from">
          <Input id="audit-from" type="date" value={filters.from} onChange={(e) => setFilter({ from: e.target.value })} />
        </FormField>
        <FormField label="To" htmlFor="audit-to">
          <Input id="audit-to" type="date" value={filters.to} onChange={(e) => setFilter({ to: e.target.value })} />
        </FormField>
        {hasFilters && (
          <div className="flex items-end pb-4">
            <Button variant="secondary" onClick={() => setFilter({ ...EMPTY_FILTERS, user_id: undefined })}>
              Clear
            </Button>
          </div>
        )}
      </FilterBar>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage={hasFilters ? "No entries match these filters." : "No activity recorded yet."}
        onRowClick={(row) => setOpenEntryId(row.id)}
        rowLabel={(row) => `View details: ${ACTION_STYLE[row.action].label} ${row.record_label} ${row.record_name}`}
      />
      {data && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} onPageChange={setPage} />}

      <AuditEntryDrawer entryId={openEntryId} onClose={() => setOpenEntryId(null)} />
    </div>
  );
}

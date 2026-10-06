import { Select } from "../../../design-system";
import { useAuth } from "../../../lib/auth-context";
import { useBranches } from "../api";

/**
 * Picks one of the organisation's locations (System Settings → Locations)
 * to put a client or staff member at. Deactivated locations can't be newly
 * chosen, but one already set stays visible so saving doesn't silently
 * clear it.
 */
export function LocationSelect({
  id,
  value,
  onChange,
}: {
  id: string;
  value: number | null | undefined;
  onChange: (branchId: number | null) => void;
}) {
  const { user } = useAuth();
  const { data: branches } = useBranches(user?.tenant_id ?? 0);
  const options = (branches?.data ?? []).filter((b) => b.status === "active" || b.id === value);

  return (
    <Select id={id} value={value ?? ""} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}>
      <option value="">No location</option>
      {options.map((branch) => (
        <option key={branch.id} value={branch.id}>
          {branch.name}
          {branch.status === "inactive" ? " (inactive)" : ""}
        </option>
      ))}
    </Select>
  );
}

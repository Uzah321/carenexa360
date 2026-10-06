/** "Harare (inactive)" — for filters, where an inactive location's history is still worth viewing. */
export function branchOptionLabel(branch: { name: string; status: string }): string {
  return branch.status === "inactive" ? `${branch.name} (inactive)` : branch.name;
}

import { useState } from "react";
import { Alert, Button, Card, CardBody, CardHeader, TagInput } from "../../../design-system";
import { DEFAULT_SETTINGS, REFERENCE_LISTS, REFERENCE_LIST_LABELS, type ReferenceList } from "../defaults";
import { useSaveSettings } from "./useSaveSettings";

/**
 * The organisation's own lists — care tasks, skills, job titles, document
 * categories, medication routes and forms, equipment — offered as quick
 * picks wherever those are entered. Free text is still accepted there.
 */
export function ReferenceDataTab({ tenantId }: { tenantId: number }) {
  const settingsState = useSaveSettings(tenantId);

  if (settingsState.isLoading || !settingsState.tenant) {
    return (
      <Card>
        <CardBody>Loading…</CardBody>
      </Card>
    );
  }

  return <ReferenceDataForm loaded={settingsState.tenant.settings.reference_data} {...settingsState} />;
}

function ReferenceDataForm({
  loaded,
  save,
  isSaving,
  saved,
  error,
}: { loaded: Record<ReferenceList, string[]> } & Pick<ReturnType<typeof useSaveSettings>, "save" | "isSaving" | "saved" | "error">) {
  const [lists, setLists] = useState<Record<ReferenceList, string[]>>(() => ({ ...loaded }));

  return (
    <Card>
      <CardHeader>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <span>Reference Data</span>
          <Button onClick={() => save({ settings: { reference_data: lists } })} isLoading={isSaving}>
            Save lists
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        <p className="mb-4 text-sm text-inksoft">
          These lists are offered as quick picks across the app. Staff can still type anything that isn't on a list.
          Type an item and press Enter to add it; click × to remove one.
        </p>
        {saved && (
          <div className="mb-4">
            <Alert tone="success">Lists saved — the new choices are available straight away.</Alert>
          </div>
        )}
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <div className="grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-2">
          {REFERENCE_LISTS.map((key) => (
            <div key={key}>
              <div className="mb-1 flex items-baseline justify-between gap-2">
                <label htmlFor={`ref-${key}`} className="text-sm font-medium text-ink">
                  {REFERENCE_LIST_LABELS[key].label} <span className="text-xs text-inksoft">({lists[key].length})</span>
                </label>
                <button
                  type="button"
                  className="text-xs font-medium text-teal hover:text-teal/90"
                  onClick={() => setLists({ ...lists, [key]: [...DEFAULT_SETTINGS.reference_data[key]] })}
                >
                  Restore defaults
                </button>
              </div>
              <p className="mb-2 text-xs text-inksoft">{REFERENCE_LIST_LABELS[key].usedFor}</p>
              <TagInput
                id={`ref-${key}`}
                value={lists[key]}
                onChange={(items) => setLists({ ...lists, [key]: items })}
                placeholder="Add an item and press Enter"
              />
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

import { useState, type FormEvent, type ReactNode } from "react";
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
  Modal,
  RowActionsMenu,
  Select,
  StatusBadge,
  TagInput,
  Textarea,
  type Column,
  type RowAction,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { downloadDocument, useServiceUserDocuments, useUploadDocument } from "../../documents/api";
import {
  useCreateServiceUserContact,
  useDeleteServiceUserContact,
  useGrantPortalAccess,
  useServiceUserContacts,
  useUpdateServiceUser,
  type CreateContactInput,
} from "../api";
import { SERVICE_USER_CONTACT_TYPES, type ServiceUser, type ServiceUserContact } from "../../../lib/types";

const HOSPITAL_RECORD_CATEGORY = "Hospital Record";

function InfoRow({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div className="flex justify-between border-b border-line py-2 text-sm last:border-0">
      <dt className="text-inksoft">{label}</dt>
      <dd className="text-right font-medium text-ink">{value || "—"}</dd>
    </div>
  );
}

function TagList({ label, items }: { label: string; items: string[] | undefined }) {
  const safeItems = items ?? [];
  return (
    <div className="border-b border-line py-2 last:border-0">
      <div className="mb-1 text-sm text-inksoft">{label}</div>
      {safeItems.length === 0 ? (
        <span className="text-sm text-inksoft">None recorded</span>
      ) : (
        <div className="flex flex-wrap gap-1.5">
          {safeItems.map((item) => (
            <StatusBadge key={item} label={item} tone="warning" />
          ))}
        </div>
      )}
    </div>
  );
}

function HospitalDocuments({ serviceUserId }: { serviceUserId: number }) {
  const { data: documents, isLoading } = useServiceUserDocuments(serviceUserId);
  const upload = useUploadDocument(serviceUserId);
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);

  const hospitalDocuments = (documents ?? []).filter((doc) => doc.category === HOSPITAL_RECORD_CATEGORY);

  async function handleUpload() {
    if (!pendingFile) return;
    setError(null);
    try {
      await upload.mutateAsync({ file: pendingFile, category: HOSPITAL_RECORD_CATEGORY });
      setPendingFile(null);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not upload this file. Please try again."));
    }
  }

  return (
    <div className="border-t border-line pt-3">
      <div className="mb-1 text-sm text-inksoft">Discharge letters &amp; scans</div>
      {error && (
        <div className="mb-2">
          <Alert tone="danger">{error}</Alert>
        </div>
      )}
      {!isLoading && hospitalDocuments.length > 0 && (
        <ul className="mb-3 space-y-1">
          {hospitalDocuments.map((doc) => (
            <li key={doc.id} className="flex items-center justify-between text-sm">
              <span className="truncate text-ink">{doc.original_filename}</span>
              <button
                type="button"
                className="ml-2 shrink-0 font-medium text-teal hover:text-teal/90"
                onClick={() => downloadDocument(doc.id, doc.original_filename)}
              >
                Download
              </button>
            </li>
          ))}
        </ul>
      )}
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex-1">
          <FileUpload accept="application/pdf,image/*" onSelect={setPendingFile} />
        </div>
        <Button variant="secondary" onClick={handleUpload} disabled={!pendingFile} isLoading={upload.isPending}>
          Upload
        </Button>
      </div>
    </div>
  );
}

export function OverviewTab({ serviceUser }: { serviceUser: ServiceUser }) {
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <PersonalDetailsCard serviceUser={serviceUser} />

      <MedicalSummaryCard serviceUser={serviceUser} />

      <HospitalRecordsCard serviceUser={serviceUser} />

      <CareNotesCard serviceUser={serviceUser} />

      <LocationCard serviceUser={serviceUser} />

      <ContactsCard serviceUserId={serviceUser.id} />
    </div>
  );
}

/** A Card with an "Edit" button in its header that opens a Modal form —
 * shared shape for the four Overview panels below and LocationCard, so a
 * field save always follows the same pattern: local draft state, PATCH via
 * useUpdateServiceUser, close on success. */
function EditableCard({
  title,
  formId,
  isOpen,
  onOpenChange,
  isSaving,
  error,
  onSubmit,
  children,
  form,
}: {
  title: string;
  formId: string;
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  isSaving: boolean;
  error: string | null;
  onSubmit: (event: FormEvent) => void;
  children: ReactNode;
  form: ReactNode;
}) {
  const actions: RowAction[] = [{ label: "Edit", onClick: () => onOpenChange(true) }];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>{title}</span>
          <RowActionsMenu actions={actions} label={`${title} actions`} />
        </div>
      </CardHeader>
      <CardBody>{children}</CardBody>

      <Modal
        isOpen={isOpen}
        onClose={() => onOpenChange(false)}
        title={`Edit ${title}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button form={formId} type="submit" isLoading={isSaving}>
              Save
            </Button>
          </>
        }
      >
        <form id={formId} onSubmit={onSubmit}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          {form}
        </form>
      </Modal>
    </Card>
  );
}

function PersonalDetailsCard({ serviceUser }: { serviceUser: ServiceUser }) {
  const [isOpen, setIsOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [draft, setDraft] = useState({
    preferred_name: serviceUser.preferred_name ?? "",
    date_of_birth: serviceUser.date_of_birth ?? "",
    gender: serviceUser.gender ?? "",
    language: serviceUser.language ?? "",
    phone: serviceUser.phone ?? "",
    email: serviceUser.email ?? "",
    address: serviceUser.address ?? "",
    funding_source: serviceUser.funding_source ?? "",
  });

  function openModal() {
    setDraft({
      preferred_name: serviceUser.preferred_name ?? "",
      date_of_birth: serviceUser.date_of_birth ?? "",
      gender: serviceUser.gender ?? "",
      language: serviceUser.language ?? "",
      phone: serviceUser.phone ?? "",
      email: serviceUser.email ?? "",
      address: serviceUser.address ?? "",
      funding_source: serviceUser.funding_source ?? "",
    });
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync({
        preferred_name: draft.preferred_name || null,
        date_of_birth: draft.date_of_birth || null,
        gender: draft.gender || null,
        language: draft.language || null,
        phone: draft.phone || null,
        email: draft.email || null,
        address: draft.address || null,
        funding_source: draft.funding_source || null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save these details. Please try again."));
    }
  }

  return (
    <EditableCard
      title="Personal Details"
      formId="edit-personal-details-form"
      isOpen={isOpen}
      onOpenChange={(open) => (open ? openModal() : setIsOpen(false))}
      isSaving={updateServiceUser.isPending}
      error={error}
      onSubmit={handleSave}
      form={
        <>
          <FormField label="Preferred name" htmlFor="pd-preferred-name">
            <Input
              id="pd-preferred-name"
              value={draft.preferred_name}
              onChange={(e) => setDraft({ ...draft, preferred_name: e.target.value })}
            />
          </FormField>
          <FormField label="Date of birth" htmlFor="pd-dob">
            <Input
              id="pd-dob"
              type="date"
              value={draft.date_of_birth}
              onChange={(e) => setDraft({ ...draft, date_of_birth: e.target.value })}
            />
          </FormField>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Gender" htmlFor="pd-gender">
              <Input id="pd-gender" value={draft.gender} onChange={(e) => setDraft({ ...draft, gender: e.target.value })} />
            </FormField>
            <FormField label="Language" htmlFor="pd-language">
              <Input
                id="pd-language"
                value={draft.language}
                onChange={(e) => setDraft({ ...draft, language: e.target.value })}
              />
            </FormField>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="Phone" htmlFor="pd-phone">
              <Input id="pd-phone" value={draft.phone} onChange={(e) => setDraft({ ...draft, phone: e.target.value })} />
            </FormField>
            <FormField label="Email" htmlFor="pd-email">
              <Input
                id="pd-email"
                type="email"
                value={draft.email}
                onChange={(e) => setDraft({ ...draft, email: e.target.value })}
              />
            </FormField>
          </div>
          <FormField label="Address" htmlFor="pd-address">
            <Textarea id="pd-address" value={draft.address} onChange={(e) => setDraft({ ...draft, address: e.target.value })} />
          </FormField>
          <FormField label="Funding source" htmlFor="pd-funding">
            <Input
              id="pd-funding"
              value={draft.funding_source}
              onChange={(e) => setDraft({ ...draft, funding_source: e.target.value })}
            />
          </FormField>
        </>
      }
    >
      <dl>
        <InfoRow label="Preferred name" value={serviceUser.preferred_name} />
        <InfoRow label="Date of birth" value={serviceUser.date_of_birth} />
        <InfoRow label="Gender" value={serviceUser.gender} />
        <InfoRow label="Language" value={serviceUser.language} />
        <InfoRow label="Phone" value={serviceUser.phone} />
        <InfoRow label="Email" value={serviceUser.email} />
        <InfoRow label="Address" value={serviceUser.address} />
        <InfoRow label="Funding source" value={serviceUser.funding_source} />
      </dl>
    </EditableCard>
  );
}

function MedicalSummaryCard({ serviceUser }: { serviceUser: ServiceUser }) {
  const [isOpen, setIsOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [draft, setDraft] = useState({
    allergies: serviceUser.allergies ?? [],
    diagnoses: serviceUser.diagnoses ?? [],
    medical_conditions: serviceUser.medical_conditions ?? [],
    disabilities: serviceUser.disabilities ?? [],
  });

  function openModal() {
    setDraft({
      allergies: serviceUser.allergies ?? [],
      diagnoses: serviceUser.diagnoses ?? [],
      medical_conditions: serviceUser.medical_conditions ?? [],
      disabilities: serviceUser.disabilities ?? [],
    });
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync(draft);
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save the medical summary. Please try again."));
    }
  }

  return (
    <EditableCard
      title="Medical Summary"
      formId="edit-medical-summary-form"
      isOpen={isOpen}
      onOpenChange={(open) => (open ? openModal() : setIsOpen(false))}
      isSaving={updateServiceUser.isPending}
      error={error}
      onSubmit={handleSave}
      form={
        <>
          <FormField label="Allergies" htmlFor="ms-allergies">
            <TagInput
              id="ms-allergies"
              value={draft.allergies}
              onChange={(tags) => setDraft({ ...draft, allergies: tags })}
              placeholder="Type and press Enter…"
            />
          </FormField>
          <FormField label="Diagnoses" htmlFor="ms-diagnoses">
            <TagInput
              id="ms-diagnoses"
              value={draft.diagnoses}
              onChange={(tags) => setDraft({ ...draft, diagnoses: tags })}
              placeholder="Type and press Enter…"
            />
          </FormField>
          <FormField label="Medical conditions" htmlFor="ms-conditions">
            <TagInput
              id="ms-conditions"
              value={draft.medical_conditions}
              onChange={(tags) => setDraft({ ...draft, medical_conditions: tags })}
              placeholder="Type and press Enter…"
            />
          </FormField>
          <FormField label="Disabilities" htmlFor="ms-disabilities">
            <TagInput
              id="ms-disabilities"
              value={draft.disabilities}
              onChange={(tags) => setDraft({ ...draft, disabilities: tags })}
              placeholder="Type and press Enter…"
            />
          </FormField>
        </>
      }
    >
      <TagList label="Allergies" items={serviceUser.allergies} />
      <TagList label="Diagnoses" items={serviceUser.diagnoses} />
      <TagList label="Medical conditions" items={serviceUser.medical_conditions} />
      <TagList label="Disabilities" items={serviceUser.disabilities} />
    </EditableCard>
  );
}

function HospitalRecordsCard({ serviceUser }: { serviceUser: ServiceUser }) {
  const [isOpen, setIsOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [draft, setDraft] = useState({
    referring_hospital: serviceUser.referring_hospital ?? "",
    hospital_record_number: serviceUser.hospital_record_number ?? "",
    discharge_date: serviceUser.discharge_date ?? "",
    discharge_summary: serviceUser.discharge_summary ?? "",
  });

  function openModal() {
    setDraft({
      referring_hospital: serviceUser.referring_hospital ?? "",
      hospital_record_number: serviceUser.hospital_record_number ?? "",
      discharge_date: serviceUser.discharge_date ?? "",
      discharge_summary: serviceUser.discharge_summary ?? "",
    });
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync({
        referring_hospital: draft.referring_hospital || null,
        hospital_record_number: draft.hospital_record_number || null,
        discharge_date: draft.discharge_date || null,
        discharge_summary: draft.discharge_summary || null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save the hospital records. Please try again."));
    }
  }

  return (
    <EditableCard
      title="Hospital Records"
      formId="edit-hospital-records-form"
      isOpen={isOpen}
      onOpenChange={(open) => (open ? openModal() : setIsOpen(false))}
      isSaving={updateServiceUser.isPending}
      error={error}
      onSubmit={handleSave}
      form={
        <>
          <FormField label="Referring hospital" htmlFor="hr-referring">
            <Input
              id="hr-referring"
              value={draft.referring_hospital}
              onChange={(e) => setDraft({ ...draft, referring_hospital: e.target.value })}
            />
          </FormField>
          <FormField label="Hospital record number" htmlFor="hr-record-number">
            <Input
              id="hr-record-number"
              value={draft.hospital_record_number}
              onChange={(e) => setDraft({ ...draft, hospital_record_number: e.target.value })}
            />
          </FormField>
          <FormField label="Discharge date" htmlFor="hr-discharge-date">
            <Input
              id="hr-discharge-date"
              type="date"
              value={draft.discharge_date}
              onChange={(e) => setDraft({ ...draft, discharge_date: e.target.value })}
            />
          </FormField>
          <FormField label="Discharge summary" htmlFor="hr-discharge-summary">
            <Textarea
              id="hr-discharge-summary"
              value={draft.discharge_summary}
              onChange={(e) => setDraft({ ...draft, discharge_summary: e.target.value })}
            />
          </FormField>
        </>
      }
    >
      <dl>
        <InfoRow label="Referring hospital" value={serviceUser.referring_hospital} />
        <InfoRow label="Hospital record number" value={serviceUser.hospital_record_number} />
        <InfoRow label="Discharge date" value={serviceUser.discharge_date} />
      </dl>
      <div className="border-b border-line py-2">
        <div className="mb-1 text-sm text-inksoft">Discharge summary</div>
        <p className="text-sm text-ink">{serviceUser.discharge_summary || "None recorded"}</p>
      </div>
      <HospitalDocuments serviceUserId={serviceUser.id} />
    </EditableCard>
  );
}

function CareNotesCard({ serviceUser }: { serviceUser: ServiceUser }) {
  const [isOpen, setIsOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);
  const [draft, setDraft] = useState({
    mobility_notes: serviceUser.mobility_notes ?? "",
    communication_needs: serviceUser.communication_needs ?? "",
    dietary_needs: serviceUser.dietary_needs ?? "",
    cultural_preferences: serviceUser.cultural_preferences ?? "",
    religious_requirements: serviceUser.religious_requirements ?? "",
    behavioural_considerations: serviceUser.behavioural_considerations ?? "",
    preferred_routines: serviceUser.preferred_routines ?? "",
    capacity_consent_notes: serviceUser.capacity_consent_notes ?? "",
  });

  function openModal() {
    setDraft({
      mobility_notes: serviceUser.mobility_notes ?? "",
      communication_needs: serviceUser.communication_needs ?? "",
      dietary_needs: serviceUser.dietary_needs ?? "",
      cultural_preferences: serviceUser.cultural_preferences ?? "",
      religious_requirements: serviceUser.religious_requirements ?? "",
      behavioural_considerations: serviceUser.behavioural_considerations ?? "",
      preferred_routines: serviceUser.preferred_routines ?? "",
      capacity_consent_notes: serviceUser.capacity_consent_notes ?? "",
    });
    setError(null);
    setIsOpen(true);
  }

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync({
        mobility_notes: draft.mobility_notes || null,
        communication_needs: draft.communication_needs || null,
        dietary_needs: draft.dietary_needs || null,
        cultural_preferences: draft.cultural_preferences || null,
        religious_requirements: draft.religious_requirements || null,
        behavioural_considerations: draft.behavioural_considerations || null,
        preferred_routines: draft.preferred_routines || null,
        capacity_consent_notes: draft.capacity_consent_notes || null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save these care notes. Please try again."));
    }
  }

  return (
    <EditableCard
      title="Care Notes"
      formId="edit-care-notes-form"
      isOpen={isOpen}
      onOpenChange={(open) => (open ? openModal() : setIsOpen(false))}
      isSaving={updateServiceUser.isPending}
      error={error}
      onSubmit={handleSave}
      form={
        <>
          <FormField label="Mobility" htmlFor="cn-mobility">
            <Textarea id="cn-mobility" value={draft.mobility_notes} onChange={(e) => setDraft({ ...draft, mobility_notes: e.target.value })} />
          </FormField>
          <FormField label="Communication needs" htmlFor="cn-communication">
            <Textarea
              id="cn-communication"
              value={draft.communication_needs}
              onChange={(e) => setDraft({ ...draft, communication_needs: e.target.value })}
            />
          </FormField>
          <FormField label="Dietary needs" htmlFor="cn-dietary">
            <Textarea id="cn-dietary" value={draft.dietary_needs} onChange={(e) => setDraft({ ...draft, dietary_needs: e.target.value })} />
          </FormField>
          <FormField label="Cultural preferences" htmlFor="cn-cultural">
            <Textarea
              id="cn-cultural"
              value={draft.cultural_preferences}
              onChange={(e) => setDraft({ ...draft, cultural_preferences: e.target.value })}
            />
          </FormField>
          <FormField label="Religious requirements" htmlFor="cn-religious">
            <Textarea
              id="cn-religious"
              value={draft.religious_requirements}
              onChange={(e) => setDraft({ ...draft, religious_requirements: e.target.value })}
            />
          </FormField>
          <FormField label="Behavioural considerations" htmlFor="cn-behavioural">
            <Textarea
              id="cn-behavioural"
              value={draft.behavioural_considerations}
              onChange={(e) => setDraft({ ...draft, behavioural_considerations: e.target.value })}
            />
          </FormField>
          <FormField label="Preferred routines" htmlFor="cn-routines">
            <Textarea
              id="cn-routines"
              value={draft.preferred_routines}
              onChange={(e) => setDraft({ ...draft, preferred_routines: e.target.value })}
            />
          </FormField>
          <FormField label="Capacity / consent notes" htmlFor="cn-capacity">
            <Textarea
              id="cn-capacity"
              value={draft.capacity_consent_notes}
              onChange={(e) => setDraft({ ...draft, capacity_consent_notes: e.target.value })}
            />
          </FormField>
        </>
      }
    >
      <dl>
        <InfoRow label="Mobility" value={serviceUser.mobility_notes} />
        <InfoRow label="Communication needs" value={serviceUser.communication_needs} />
        <InfoRow label="Dietary needs" value={serviceUser.dietary_needs} />
        <InfoRow label="Cultural preferences" value={serviceUser.cultural_preferences} />
        <InfoRow label="Religious requirements" value={serviceUser.religious_requirements} />
        <InfoRow label="Behavioural considerations" value={serviceUser.behavioural_considerations} />
        <InfoRow label="Preferred routines" value={serviceUser.preferred_routines} />
        <InfoRow label="Capacity / consent notes" value={serviceUser.capacity_consent_notes} />
      </dl>
    </EditableCard>
  );
}

function LocationCard({ serviceUser }: { serviceUser: ServiceUser }) {
  const [isOpen, setIsOpen] = useState(false);
  const [latitude, setLatitude] = useState(serviceUser.latitude?.toString() ?? "");
  const [longitude, setLongitude] = useState(serviceUser.longitude?.toString() ?? "");
  const [error, setError] = useState<string | null>(null);
  const updateServiceUser = useUpdateServiceUser(serviceUser.id);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateServiceUser.mutateAsync({
        latitude: latitude ? Number(latitude) : null,
        longitude: longitude ? Number(longitude) : null,
      });
      setIsOpen(false);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not save the location. Please try again."));
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Location</span>
          <Button variant="secondary" onClick={() => setIsOpen(true)}>
            Edit
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        <dl>
          <InfoRow label="Latitude" value={serviceUser.latitude?.toString()} />
          <InfoRow label="Longitude" value={serviceUser.longitude?.toString()} />
        </dl>
        <p className="mt-2 text-xs text-inksoft">
          Used to verify a carer's GPS check-in is within range of this address. Copy coordinates from Google Maps.
        </p>
      </CardBody>

      <Modal
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setError(null);
        }}
        title="Edit Location"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="edit-location-form" type="submit" isLoading={updateServiceUser.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="edit-location-form" onSubmit={handleSave}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Latitude" htmlFor="location-lat">
            <Input
              id="location-lat"
              type="number"
              step="any"
              value={latitude}
              onChange={(e) => setLatitude(e.target.value)}
            />
          </FormField>
          <FormField label="Longitude" htmlFor="location-lng">
            <Input
              id="location-lng"
              type="number"
              step="any"
              value={longitude}
              onChange={(e) => setLongitude(e.target.value)}
            />
          </FormField>
        </form>
      </Modal>
    </Card>
  );
}

const EMPTY_CONTACT: CreateContactInput = {
  type: "next_of_kin",
  name: "",
};

function GrantPortalAccessModal({
  serviceUserId,
  contact,
  onClose,
}: {
  serviceUserId: number;
  contact: ServiceUserContact;
  onClose: () => void;
}) {
  const grantPortalAccess = useGrantPortalAccess(serviceUserId);
  const [email, setEmail] = useState(contact.email ?? "");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await grantPortalAccess.mutateAsync({ contactId: contact.id, email, password });
      onClose();
    } catch (err) {
      const response = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
      const errors = response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(" ") : "Something went wrong.");
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={`Grant Portal Access — ${contact.name}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="grant-portal-access-form" type="submit" isLoading={grantPortalAccess.isPending}>
            Grant Access
          </Button>
        </>
      }
    >
      <form id="grant-portal-access-form" onSubmit={handleSubmit}>
        {error && <p className="mb-3 text-sm text-red-600">{error}</p>}
        <FormField label="Email" htmlFor="portal-access-email">
          <Input
            id="portal-access-email"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </FormField>
        <FormField label="Temporary password" htmlFor="portal-access-password">
          <Input
            id="portal-access-password"
            type="password"
            required
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </FormField>
      </form>
    </Modal>
  );
}

function ContactsCard({ serviceUserId }: { serviceUserId: number }) {
  const { data: contacts, isLoading } = useServiceUserContacts(serviceUserId);
  const createContact = useCreateServiceUserContact(serviceUserId);
  const deleteContact = useDeleteServiceUserContact(serviceUserId);
  const [isOpen, setIsOpen] = useState(false);
  const [form, setForm] = useState<CreateContactInput>(EMPTY_CONTACT);
  const [portalAccessContact, setPortalAccessContact] = useState<ServiceUserContact | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createContact.mutateAsync(form);
      setIsOpen(false);
      setForm(EMPTY_CONTACT);
    } catch (err) {
      setError(apiErrorMessage(err, "Could not add this contact. Please try again."));
    }
  }

  const columns: Column<ServiceUserContact>[] = [
    { key: "type", header: "Type", render: (row) => row.type.replaceAll("_", " ") },
    { key: "name", header: "Name", render: (row) => row.name },
    { key: "phone", header: "Phone", render: (row) => row.phone ?? "—" },
    {
      key: "portal_access",
      header: "Portal Access",
      render: (row) =>
        row.has_portal_access ? (
          <StatusBadge label="Granted" tone="success" />
        ) : (
          <button
            type="button"
            className="text-sm font-medium text-teal hover:text-teal/90"
            onClick={() => setPortalAccessContact(row)}
          >
            Grant Access
          </button>
        ),
    },
    {
      key: "actions",
      header: "",
      render: (row) => (
        <button
          type="button"
          className="text-sm text-red-600 hover:text-red-500"
          onClick={() => deleteContact.mutate(row.id)}
        >
          Remove
        </button>
      ),
    },
  ];

  return (
    <Card className="lg:col-span-2">
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Contacts</span>
          <Button variant="secondary" onClick={() => setIsOpen(true)}>
            Add Contact
          </Button>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (contacts ?? []).length === 0 ? (
          <EmptyState message="No contacts recorded yet." />
        ) : (
          <DataTable
            columns={columns}
            rows={contacts ?? []}
            rowKey={(row) => row.id}
            isLoading={isLoading}
          />
        )}
      </CardBody>

      <Modal
        isOpen={isOpen}
        onClose={() => {
          setIsOpen(false);
          setError(null);
        }}
        title="Add Contact"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsOpen(false)}>
              Cancel
            </Button>
            <Button form="create-contact-form" type="submit" isLoading={createContact.isPending}>
              Add
            </Button>
          </>
        }
      >
        <form id="create-contact-form" onSubmit={handleCreate}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <FormField label="Type" htmlFor="contact-type">
            <Select
              id="contact-type"
              required
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value as CreateContactInput["type"] })}
            >
              {SERVICE_USER_CONTACT_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type.replaceAll("_", " ")}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Name" htmlFor="contact-name">
            <Input
              id="contact-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Relationship" htmlFor="contact-relationship">
            <Input
              id="contact-relationship"
              value={form.relationship ?? ""}
              onChange={(e) => setForm({ ...form, relationship: e.target.value })}
            />
          </FormField>
          <FormField label="Phone" htmlFor="contact-phone">
            <Input
              id="contact-phone"
              value={form.phone ?? ""}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
            />
          </FormField>
          <FormField label="Email" htmlFor="contact-email">
            <Input
              id="contact-email"
              type="email"
              value={form.email ?? ""}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
          </FormField>
          <FormField label="Notes" htmlFor="contact-notes">
            <Textarea
              id="contact-notes"
              value={form.notes ?? ""}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
            />
          </FormField>
        </form>
      </Modal>

      {portalAccessContact && (
        <GrantPortalAccessModal
          serviceUserId={serviceUserId}
          contact={portalAccessContact}
          onClose={() => setPortalAccessContact(null)}
        />
      )}
    </Card>
  );
}

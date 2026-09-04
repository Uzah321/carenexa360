import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  DataTable,
  EmptyState,
  FormField,
  Input,
  Modal,
  Select,
  StatusBadge,
  Textarea,
  type Column,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import {
  useCreateServiceUserContact,
  useDeleteServiceUserContact,
  useGrantPortalAccess,
  useServiceUserContacts,
  useUpdateServiceUser,
  type CreateContactInput,
} from "../api";
import { SERVICE_USER_CONTACT_TYPES, type ServiceUser, type ServiceUserContact } from "../../../lib/types";

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

export function OverviewTab({ serviceUser }: { serviceUser: ServiceUser }) {
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>Personal Details</CardHeader>
        <CardBody>
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
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Medical Summary</CardHeader>
        <CardBody>
          <TagList label="Allergies" items={serviceUser.allergies} />
          <TagList label="Diagnoses" items={serviceUser.diagnoses} />
          <TagList label="Medical conditions" items={serviceUser.medical_conditions} />
          <TagList label="Disabilities" items={serviceUser.disabilities} />
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Care Notes</CardHeader>
        <CardBody>
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
        </CardBody>
      </Card>

      <LocationCard serviceUser={serviceUser} />

      <ContactsCard serviceUserId={serviceUser.id} />
    </div>
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

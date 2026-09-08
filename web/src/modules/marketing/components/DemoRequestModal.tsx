import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { Alert, Button, FormField, Input, Modal, Textarea } from "../../../design-system";
import { useCreateDemoRequest } from "../api";

function errorMessage(err: unknown): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? "Something went wrong. Please try again.";
}

const INITIAL_FORM = {
  name: "",
  email: "",
  organization_name: "",
  phone: "",
  message: "",
};

export function DemoRequestModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  const [form, setForm] = useState(INITIAL_FORM);
  const [error, setError] = useState<string | null>(null);
  const createDemoRequest = useCreateDemoRequest();

  function handleClose() {
    setForm(INITIAL_FORM);
    setError(null);
    createDemoRequest.reset();
    onClose();
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await createDemoRequest.mutateAsync({
        ...form,
        phone: form.phone || undefined,
        message: form.message || undefined,
      });
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Request a demo"
      footer={
        createDemoRequest.isSuccess ? (
          <Button onClick={handleClose}>Done</Button>
        ) : (
          <>
            <Button variant="secondary" onClick={handleClose}>
              Cancel
            </Button>
            <Button form="demo-request-form" type="submit" isLoading={createDemoRequest.isPending}>
              Send request
            </Button>
          </>
        )
      }
    >
      {createDemoRequest.isSuccess ? (
        <div>
          <Alert tone="success">
            Thanks — we've got your request and someone from our team will be in touch shortly. We've also emailed
            you a copy of this.
          </Alert>
          <p className="mt-4 text-sm text-inksoft">
            No need to wait, though — log in now to a live demo with realistic sample data (staff, service users,
            schedules, care plans, and more) and explore it yourself:
          </p>
          <dl className="mt-3 space-y-1 rounded-xl bg-paper p-4 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-inksoft">Email</dt>
              <dd className="font-mono font-medium text-ink">{createDemoRequest.data?.demo_login.email}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-inksoft">Password</dt>
              <dd className="font-mono font-medium text-ink">{createDemoRequest.data?.demo_login.password}</dd>
            </div>
          </dl>
          <Link
            to="/login"
            className="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-teal px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-teal/90"
          >
            Log In to the Demo →
          </Link>
        </div>
      ) : (
        <form id="demo-request-form" onSubmit={handleSubmit}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <p className="mb-4 text-sm text-inksoft">
            Tell us a bit about your agency and we'll set up a walkthrough.
          </p>
          <FormField label="Your name" htmlFor="demo-name">
            <Input
              id="demo-name"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </FormField>
          <FormField label="Work email" htmlFor="demo-email">
            <Input
              id="demo-email"
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
          </FormField>
          <FormField label="Organization name" htmlFor="demo-organization">
            <Input
              id="demo-organization"
              required
              value={form.organization_name}
              onChange={(e) => setForm({ ...form, organization_name: e.target.value })}
            />
          </FormField>
          <FormField label="Phone (optional)" htmlFor="demo-phone">
            <Input
              id="demo-phone"
              type="tel"
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
            />
          </FormField>
          <FormField label="What would you like to see? (optional)" htmlFor="demo-message">
            <Textarea
              id="demo-message"
              rows={3}
              value={form.message}
              onChange={(e) => setForm({ ...form, message: e.target.value })}
            />
          </FormField>
        </form>
      )}
    </Modal>
  );
}

import { useEffect, useState, type FormEvent } from "react";
import QRCode from "qrcode";
import { ShieldCheck, ShieldOff } from "lucide-react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  FormField,
  Input,
  Modal,
  PageHeader,
  StatusBadge,
} from "../../../design-system";
import { apiErrorMessage } from "../../../lib/api-error";
import { useAuth } from "../../../lib/auth-context";
import { useConfirmTwoFactor, useDisableTwoFactor, useStartTwoFactorSetup } from "../api";

function EnableTwoFactorModal({ onClose }: { onClose: () => void }) {
  const { refreshUser } = useAuth();
  const startSetup = useStartTwoFactorSetup();
  const confirmSetup = useConfirmTwoFactor();
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [secret, setSecret] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  useEffect(() => {
    startSetup
      .mutateAsync()
      .then(async (setup) => {
        setSecret(setup.secret);
        setQrDataUrl(await QRCode.toDataURL(setup.otpauth_url));
      })
      .catch(() => setError("Could not start setup. Please try again."));
    // Start setup exactly once when the modal opens.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleConfirm(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await confirmSetup.mutateAsync(code);
      await refreshUser();
      setDone(true);
    } catch (err) {
      setError(apiErrorMessage(err, "That code didn't work. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Enable Two-Factor Authentication"
      footer={
        done ? (
          <Button onClick={onClose}>Done</Button>
        ) : (
          <>
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button
              form="confirm-two-factor-form"
              type="submit"
              isLoading={confirmSetup.isPending}
              disabled={code.length !== 6}
            >
              Verify &amp; Enable
            </Button>
          </>
        )
      }
    >
      {done ? (
        <Alert tone="success">
          Two-factor authentication is on. You'll be asked for a code from your authenticator app the next time you
          sign in.
        </Alert>
      ) : (
        <form id="confirm-two-factor-form" onSubmit={handleConfirm}>
          {error && (
            <div className="mb-4">
              <Alert tone="danger">{error}</Alert>
            </div>
          )}
          <p className="mb-3 text-sm text-inksoft">
            Scan this with an authenticator app (Google Authenticator, Authy, 1Password, etc.), or enter the key
            manually.
          </p>
          {qrDataUrl ? (
            <img src={qrDataUrl} alt="Two-factor setup QR code" className="mx-auto mb-3 h-48 w-48" />
          ) : (
            <div className="mb-3 flex h-48 items-center justify-center text-sm text-inksoft">Generating…</div>
          )}
          {secret && (
            <p className="mb-4 break-all rounded-lg bg-paper px-3 py-2 text-center font-mono text-xs text-inksoft">
              {secret}
            </p>
          )}
          <FormField label="Enter the 6-digit code from your app" htmlFor="setup-code">
            <Input
              id="setup-code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              required
              maxLength={6}
              placeholder="123456"
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/[^0-9]/g, ""))}
              className="text-center text-lg tracking-[0.3em]"
            />
          </FormField>
        </form>
      )}
    </Modal>
  );
}

function DisableTwoFactorModal({ onClose }: { onClose: () => void }) {
  const { refreshUser } = useAuth();
  const disable = useDisableTwoFactor();
  const [currentPassword, setCurrentPassword] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await disable.mutateAsync(currentPassword);
      await refreshUser();
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, "Could not disable two-factor authentication. Please try again."));
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Disable Two-Factor Authentication"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button form="disable-two-factor-form" type="submit" variant="danger" isLoading={disable.isPending}>
            Disable
          </Button>
        </>
      }
    >
      <form id="disable-two-factor-form" onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4">
            <Alert tone="danger">{error}</Alert>
          </div>
        )}
        <p className="mb-4 text-sm text-inksoft">
          Your account will no longer ask for a code at sign-in. Confirm your password to continue.
        </p>
        <FormField label="Current password" htmlFor="disable-password">
          <Input
            id="disable-password"
            type="password"
            required
            autoFocus
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
          />
        </FormField>
      </form>
    </Modal>
  );
}

export function MyAccountPage() {
  const { user } = useAuth();
  const [isEnabling, setIsEnabling] = useState(false);
  const [isDisabling, setIsDisabling] = useState(false);

  if (!user) return null;

  return (
    <div>
      <PageHeader title="My Account" description="Your profile and sign-in security." />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>Profile</CardHeader>
          <CardBody>
            <dl>
              <div className="flex justify-between border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Name</dt>
                <dd className="font-medium text-ink">{user.name}</dd>
              </div>
              <div className="flex justify-between border-b border-line py-2 text-sm">
                <dt className="text-inksoft">Email</dt>
                <dd className="font-medium text-ink">{user.email}</dd>
              </div>
              <div className="flex justify-between py-2 text-sm">
                <dt className="text-inksoft">Role</dt>
                <dd className="font-medium text-ink">{user.roles[0] ?? "—"}</dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Security</CardHeader>
          <CardBody>
            <div className="flex items-center justify-between rounded-xl bg-paper p-4">
              <div className="flex items-center gap-3">
                {user.mfa_enabled ? (
                  <ShieldCheck className="h-8 w-8 text-lime" />
                ) : (
                  <ShieldOff className="h-8 w-8 text-inksoft" />
                )}
                <div>
                  <p className="text-sm font-semibold text-ink">Two-factor authentication</p>
                  <StatusBadge label={user.mfa_enabled ? "Enabled" : "Not enabled"} tone={user.mfa_enabled ? "success" : "neutral"} />
                </div>
              </div>
              {user.mfa_enabled ? (
                <Button variant="danger" onClick={() => setIsDisabling(true)}>
                  Disable
                </Button>
              ) : (
                <Button onClick={() => setIsEnabling(true)}>Enable</Button>
              )}
            </div>
            <p className="mt-3 text-xs text-inksoft">
              Adds a second step at sign-in using a code from an authenticator app on your phone, on top of your
              password.
            </p>
          </CardBody>
        </Card>
      </div>

      {isEnabling && <EnableTwoFactorModal onClose={() => setIsEnabling(false)} />}
      {isDisabling && <DisableTwoFactorModal onClose={() => setIsDisabling(false)} />}
    </div>
  );
}

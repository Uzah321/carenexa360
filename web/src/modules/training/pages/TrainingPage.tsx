import { useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
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
import { useAuth } from "../../../lib/auth-context";
import { apiErrorMessage } from "../../../lib/api-error";
import { useStaff } from "../../staff/api";
import {
  useCreateTrainingCourse,
  useCreateTrainingRecord,
  useTrainingCourses,
  useTrainingRecords,
  type CreateTrainingCourseInput,
} from "../api";
import type { TrainingRecord, TrainingRecordStatus } from "../../../lib/types";
import { COMPLIANCE_ROLES } from "../../../lib/types";

const STATUS_TONE: Record<TrainingRecordStatus, "success" | "warning" | "danger" | "neutral"> = {
  valid: "success",
  expiring_soon: "warning",
  expired: "danger",
  no_expiry: "neutral",
};

function recordColumns(showUser: boolean): Column<TrainingRecord>[] {
  const columns: Column<TrainingRecord>[] = [];
  if (showUser) {
    columns.push({ key: "user", header: "Staff", render: (row) => row.user_name ?? "—" });
  }
  columns.push(
    { key: "course", header: "Course", render: (row) => row.training_course_name ?? "—" },
    { key: "completed", header: "Completed", render: (row) => row.completed_date },
    { key: "expiry", header: "Expires", render: (row) => row.expiry_date ?? "Never" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge label={row.status.replaceAll("_", " ")} tone={STATUS_TONE[row.status]} />,
    },
  );
  return columns;
}

function AllStaffTrainingSection() {
  const [statusFilter, setStatusFilter] = useState<TrainingRecordStatus | "">("");
  const { data: records, isLoading } = useTrainingRecords({ status: statusFilter || undefined });
  const { data: staff } = useStaff(1);
  const { data: courses } = useTrainingCourses();
  const createRecord = useCreateTrainingRecord();
  const createCourse = useCreateTrainingCourse();

  const [isRecordOpen, setIsRecordOpen] = useState(false);
  const [userId, setUserId] = useState<number | "">("");
  const [courseId, setCourseId] = useState<number | "">("");
  const [completedDate, setCompletedDate] = useState("");
  const [expiryDate, setExpiryDate] = useState("");

  const [isCourseOpen, setIsCourseOpen] = useState(false);
  const [courseForm, setCourseForm] = useState<CreateTrainingCourseInput>({ name: "" });
  const [recordError, setRecordError] = useState<string | null>(null);
  const [courseError, setCourseError] = useState<string | null>(null);

  async function handleRecord(event: FormEvent) {
    event.preventDefault();
    if (!userId || !courseId) return;
    setRecordError(null);
    try {
      await createRecord.mutateAsync({
        user_id: userId,
        training_course_id: courseId,
        completed_date: completedDate,
        expiry_date: expiryDate || undefined,
      });
      setIsRecordOpen(false);
      setUserId("");
      setCourseId("");
      setCompletedDate("");
      setExpiryDate("");
    } catch (err) {
      setRecordError(apiErrorMessage(err, "Could not log this training record. Please try again."));
    }
  }

  async function handleCourse(event: FormEvent) {
    event.preventDefault();
    setCourseError(null);
    try {
      await createCourse.mutateAsync(courseForm);
      setIsCourseOpen(false);
      setCourseForm({ name: "" });
    } catch (err) {
      setCourseError(apiErrorMessage(err, "Could not save this course. Please try again."));
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span>All Staff Training</span>
            <Select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as TrainingRecordStatus | "")}
              className="w-auto"
            >
              <option value="">All statuses</option>
              <option value="valid">Valid</option>
              <option value="expiring_soon">Expiring soon</option>
              <option value="expired">Expired</option>
              <option value="no_expiry">No expiry</option>
            </Select>
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => setIsCourseOpen(true)}>
              New Course
            </Button>
            <Button onClick={() => setIsRecordOpen(true)}>Log Training</Button>
          </div>
        </div>
      </CardHeader>
      <CardBody>
        {!isLoading && (records ?? []).length === 0 ? (
          <EmptyState message="No training records found." />
        ) : (
          <DataTable columns={recordColumns(true)} rows={records ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
        )}
      </CardBody>

      <Modal
        isOpen={isRecordOpen}
        onClose={() => {
          setIsRecordOpen(false);
          setRecordError(null);
        }}
        title="Log Training"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsRecordOpen(false)}>
              Cancel
            </Button>
            <Button form="log-training-form" type="submit" isLoading={createRecord.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="log-training-form" onSubmit={handleRecord}>
          {recordError && (
            <div className="mb-4">
              <Alert tone="danger">{recordError}</Alert>
            </div>
          )}
          <FormField label="Staff member" htmlFor="training-user">
            <Select
              id="training-user"
              required
              value={userId}
              onChange={(e) => setUserId(e.target.value ? Number(e.target.value) : "")}
            >
              <option value="" disabled>
                Select a staff member
              </option>
              {(staff?.data ?? []).map((s) => (
                <option key={s.id} value={s.user_id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Course" htmlFor="training-course">
            <Select
              id="training-course"
              required
              value={courseId}
              onChange={(e) => setCourseId(e.target.value ? Number(e.target.value) : "")}
            >
              <option value="" disabled>
                Select a course
              </option>
              {(courses ?? []).map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Completed date" htmlFor="training-completed">
            <Input
              id="training-completed"
              type="date"
              required
              value={completedDate}
              onChange={(e) => setCompletedDate(e.target.value)}
            />
          </FormField>
          <FormField label="Expiry date (optional — auto-computed from the course's validity period if left blank)" htmlFor="training-expiry">
            <Input id="training-expiry" type="date" value={expiryDate} onChange={(e) => setExpiryDate(e.target.value)} />
          </FormField>
        </form>
      </Modal>

      <Modal
        isOpen={isCourseOpen}
        onClose={() => {
          setIsCourseOpen(false);
          setCourseError(null);
        }}
        title="New Training Course"
        footer={
          <>
            <Button variant="secondary" onClick={() => setIsCourseOpen(false)}>
              Cancel
            </Button>
            <Button form="new-course-form" type="submit" isLoading={createCourse.isPending}>
              Save
            </Button>
          </>
        }
      >
        <form id="new-course-form" onSubmit={handleCourse}>
          {courseError && (
            <div className="mb-4">
              <Alert tone="danger">{courseError}</Alert>
            </div>
          )}
          <FormField label="Name" htmlFor="course-name">
            <Input
              id="course-name"
              required
              value={courseForm.name}
              onChange={(e) => setCourseForm({ ...courseForm, name: e.target.value })}
            />
          </FormField>
          <FormField label="Category" htmlFor="course-category">
            <Input
              id="course-category"
              value={courseForm.category ?? ""}
              onChange={(e) => setCourseForm({ ...courseForm, category: e.target.value })}
            />
          </FormField>
          <FormField label="Validity period (months, optional)" htmlFor="course-validity">
            <Input
              id="course-validity"
              type="number"
              min={1}
              value={courseForm.validity_period_months ?? ""}
              onChange={(e) => setCourseForm({ ...courseForm, validity_period_months: Number(e.target.value) })}
            />
          </FormField>
          <FormField label="Description" htmlFor="course-description">
            <Textarea
              id="course-description"
              value={courseForm.description ?? ""}
              onChange={(e) => setCourseForm({ ...courseForm, description: e.target.value })}
            />
          </FormField>
          <div className="mt-2">
            <Checkbox
              id="course-mandatory"
              label="Mandatory for all staff"
              checked={Boolean(courseForm.is_mandatory)}
              onChange={(e) => setCourseForm({ ...courseForm, is_mandatory: e.target.checked })}
            />
          </div>
        </form>
      </Modal>
    </Card>
  );
}

export function TrainingPage() {
  const { hasAnyRole, user } = useAuth();
  const isComplianceAdmin = hasAnyRole(COMPLIANCE_ROLES);

  const { data: myRecords, isLoading } = useTrainingRecords({ user_id: user?.id });

  return (
    <div>
      <div className="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <h1 className="text-2xl font-extrabold tracking-tight text-ink">Training</h1>
        <p className="mt-1 text-sm text-inksoft">Certifications and compliance tracking.</p>
      </div>

      <Card>
        <CardHeader>My Training</CardHeader>
        <CardBody>
          {!isLoading && (myRecords ?? []).length === 0 ? (
            <EmptyState message="No training records yet." />
          ) : (
            <DataTable columns={recordColumns(false)} rows={myRecords ?? []} rowKey={(row) => row.id} isLoading={isLoading} />
          )}
        </CardBody>
      </Card>

      {isComplianceAdmin && (
        <div className="mt-6">
          <AllStaffTrainingSection />
        </div>
      )}
    </div>
  );
}

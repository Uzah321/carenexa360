import { CheckCircle2, Circle, Clock, TriangleAlert } from "lucide-react";
import { Card, CardBody, CardHeader, StatusBadge } from "../../../design-system";
import { formatDate } from "../../../lib/preferences";
import { useCarePathway, type CarePathwayStage } from "../../care-planning/api";

const STATUS: Record<CarePathwayStage["status"], { text: (s: CarePathwayStage) => string; tone: "success" | "warning" | "danger" | "info" | "neutral" }> = {
  done: { text: (s) => `Done ${formatDate(s.done)}`, tone: "success" },
  done_late: { text: (s) => `Done late ${formatDate(s.done)}`, tone: "warning" },
  due: { text: (s) => `Due ${formatDate(s.due)}`, tone: "info" },
  overdue: { text: (s) => `Overdue since ${formatDate(s.due)}`, tone: "danger" },
  waiting: { text: () => "After the previous step", tone: "neutral" },
};

function StageIcon({ status }: { status: CarePathwayStage["status"] }) {
  const className = "h-4 w-4 shrink-0";
  if (status === "done") return <CheckCircle2 className={`${className} text-lime`} aria-hidden />;
  if (status === "done_late") return <CheckCircle2 className={`${className} text-amber`} aria-hidden />;
  if (status === "overdue") return <TriangleAlert className={`${className} text-coral`} aria-hidden />;
  if (status === "due") return <Clock className={`${className} text-sky`} aria-hidden />;
  return <Circle className={`${className} text-inksoft`} aria-hidden />;
}

/** The client's progress through referral → assessment → care plan → reviews, against the organisation's timescales. */
export function CarePathwayCard({ serviceUserId }: { serviceUserId: number }) {
  const { data, isLoading } = useCarePathway(serviceUserId);

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <span>Care Pathway</span>
          {data && data.overdue > 0 && <StatusBadge label={`${data.overdue} overdue`} tone="danger" />}
        </div>
      </CardHeader>
      <CardBody>
        {isLoading || !data ? (
          <p className="text-sm text-inksoft">Loading…</p>
        ) : (
          <ol className="space-y-2.5">
            {data.stages.map((stage) => (
              <li key={stage.key} className="flex items-center justify-between gap-3 text-sm">
                <span className="flex items-center gap-2 text-ink">
                  <StageIcon status={stage.status} />
                  {stage.label}
                </span>
                <StatusBadge label={STATUS[stage.status].text(stage)} tone={STATUS[stage.status].tone} />
              </li>
            ))}
          </ol>
        )}
        <p className="mt-3 text-xs text-inksoft">Timescales are set in System Settings → Care Pathway.</p>
      </CardBody>
    </Card>
  );
}

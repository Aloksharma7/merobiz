import type { ProjectWorkStatus } from "@/lib/types";

export const PROJECT_WORK_STATUSES: Array<{ value: ProjectWorkStatus; label: string }> = [
  { value: "started", label: "Started" },
  { value: "first_draft", label: "First draft" },
  { value: "send_draft", label: "Send draft" },
  { value: "correction_ongoing", label: "Correction ongoing" },
  { value: "waiting_for_feedback", label: "Waiting for feedback" },
  { value: "submitted", label: "Submitted" },
  { value: "approved", label: "Approved" },
  { value: "cancelled", label: "Cancelled / Refunded" },
];

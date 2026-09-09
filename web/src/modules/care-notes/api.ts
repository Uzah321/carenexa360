import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "../../lib/api-client";
import type { CareNote } from "../../lib/types";

export function useServiceUserCareNotes(serviceUserId: number) {
  return useQuery({
    queryKey: ["service-users", serviceUserId, "care-notes"],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CareNote[] }>(
        `/service-users/${serviceUserId}/care-notes`,
      );
      return data.data;
    },
    enabled: Boolean(serviceUserId),
  });
}

export interface RecordCareNoteInput {
  audio: Blob;
  caption?: string;
  duration_seconds?: number;
  visit_id?: number;
}

export function useRecordCareNote(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: RecordCareNoteInput) => {
      const formData = new FormData();
      // A recorded Blob has no filename — the backend validates by the
      // reported MIME type, not the extension, so this name only matters
      // for the request's multipart part.
      formData.append("audio", input.audio, "note.webm");
      if (input.caption) formData.append("caption", input.caption);
      if (input.duration_seconds != null) formData.append("duration_seconds", String(input.duration_seconds));
      if (input.visit_id != null) formData.append("visit_id", String(input.visit_id));

      const { data } = await apiClient.post<{ data: CareNote }>(
        `/service-users/${serviceUserId}/care-notes`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      return data.data;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "care-notes"] });
    },
  });
}

export function useDeleteCareNote(serviceUserId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (careNoteId: number) => {
      await apiClient.delete(`/care-notes/${careNoteId}`);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["service-users", serviceUserId, "care-notes"] });
    },
  });
}

/** Same-origin, cookie-authenticated — safe to use directly as an <audio src>. */
export function careNoteAudioUrl(careNoteId: number): string {
  return `/api/v1/care-notes/${careNoteId}/audio`;
}

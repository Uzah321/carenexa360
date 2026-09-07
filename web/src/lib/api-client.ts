import axios from "axios";

export const apiClient = axios.create({
  baseURL: "/api/v1",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

export async function ensureCsrfCookie() {
  await axios.get("/sanctum/csrf-cookie", { baseURL: "/", withCredentials: true });
}

const SESSION_EXPIRED_MESSAGE = "Your session has expired due to inactivity.";

let onSessionExpired: (() => void) | null = null;

/** Registered once by AuthProvider so a mid-session idle-timeout logout (see
 * EnforceSessionTimeout) clears local auth state immediately instead of
 * leaving the UI showing a "logged in" screen that 401s on every action
 * until the user happens to refresh. */
export function setSessionExpiredHandler(handler: () => void) {
  onSessionExpired = handler;
}

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && error.response?.data?.message === SESSION_EXPIRED_MESSAGE) {
      onSessionExpired?.();
    }
    return Promise.reject(error);
  },
);

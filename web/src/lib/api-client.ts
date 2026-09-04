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

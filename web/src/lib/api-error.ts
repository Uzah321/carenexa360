/**
 * Turns an axios error from a failed mutation into text worth showing someone.
 * Laravel validation failures carry a 422 with an `errors` object keyed by
 * field; anything else falls back to the request's own `message`. Without
 * this, a form's submit handler has nothing to display and a failed request
 * just does nothing visible — the button spinner stops and the user is left
 * with no idea whether it worked.
 */
export function apiErrorMessage(err: unknown, fallback = "Something went wrong. Please try again."): string {
  const response = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })
    .response;
  const errors = response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return response?.data?.message ?? fallback;
}

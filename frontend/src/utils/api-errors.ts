import { isAxiosError } from 'axios'

/** First validation message per field from a Laravel 422 response ({ name: 'The name field is required.' }). */
export function validationErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error) || error.response?.status !== 422) {
    return {}
  }

  const errors: Record<string, string[]> = error.response.data?.errors ?? {}

  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

/** The API's message for a failed request, or a fallback. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  return (isAxiosError(error) && error.response?.data?.message) || fallback
}

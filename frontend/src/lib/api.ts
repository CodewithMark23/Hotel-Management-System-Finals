const base = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const key = 'hotel_token'
export const getToken = () => sessionStorage.getItem(key)
export const setToken = (token: string | null) => token ? sessionStorage.setItem(key, token) : sessionStorage.removeItem(key)

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken()
  let response: Response
  try {
    response = await fetch(`${base}${path}`, {
      ...options,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}), ...options.headers },
    })
  } catch {
    throw new Error('Cannot reach the API. Make sure the Laravel server is running.')
  }
  if (response.status === 204) return undefined as T
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    if ([502, 503, 504].includes(response.status)) {
      throw new Error('Cannot reach the Laravel API. In another terminal, run: cd backend, then php artisan serve --host=127.0.0.1 --port=8000.')
    }
    if (response.status === 401) {
      setToken(null)
      window.dispatchEvent(new Event('hotel:unauthorized'))
    }
    const errors = data?.errors as Record<string, string[]> | undefined
    throw new Error(errors ? Object.values(errors).flat().join(' ') : data?.message || `Request failed (${response.status}).`)
  }
  return data as T
}

import { loadApiBaseUrl } from './apiConfig'

export async function apiRequest(path, options = {}) {
  const apiBaseUrl = await loadApiBaseUrl()
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
  })

  const payload = await response.json().catch(() => ({}))

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.message || 'No se pudo completar la solicitud.')
  }

  return payload
}

export function login(username, password) {
  return apiRequest('/login.php', {
    method: 'POST',
    body: JSON.stringify({ username, password }),
  })
}

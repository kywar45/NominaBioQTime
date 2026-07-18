let cachedApiBaseUrl = ''

export function getDefaultApiBaseUrl() {
  if (window.location.port && !window.location.pathname.includes('/NominaBioQTime')) {
    // Dev server: usar mismo origen para que pase por el proxy y evitar CORS
    return `${window.location.protocol}//${window.location.hostname}:${window.location.port}/NominaBioQTime/api`
  }

  return 'api'
}

function getPublicConfigUrl() {
  const path = window.location.pathname.endsWith('/')
    ? window.location.pathname
    : window.location.pathname.replace(/\/[^/]*$/, '/')

  return `${window.location.origin}${path}api-config.json`
}

function getSettingsUrl() {
  // settings.php siempre está en api/settings.php relativo a la raíz del proyecto.
  // Se deriva de la misma URL donde se sirve api-config.json, nunca de la URL que se guarda.
  return getPublicConfigUrl().replace('api-config.json', 'api/settings.php')
}

export function normalizeApiBaseUrl(value) {
  const cleaned = String(value || '').trim()

  if (!cleaned) {
    return getDefaultApiBaseUrl()
  }

  return cleaned.replace(/\/+$/, '')
}

export async function loadApiBaseUrl({ force = false } = {}) {
  if (cachedApiBaseUrl && !force) {
    return cachedApiBaseUrl
  }

  try {
    const response = await fetch(`${getSettingsUrl()}?t=${Date.now()}`, {
      cache: 'no-store',
    })
    const payload = await response.json()
    cachedApiBaseUrl = normalizeApiBaseUrl(payload.config?.apiBaseUrl)
  } catch {
    cachedApiBaseUrl = getDefaultApiBaseUrl()
  }

  return cachedApiBaseUrl
}

export function getApiBaseUrl() {
  return cachedApiBaseUrl || getDefaultApiBaseUrl()
}

export async function saveApiBaseUrl(value) {
  const normalized = normalizeApiBaseUrl(value)
  const response = await fetch(getSettingsUrl(), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ apiBaseUrl: normalized }),
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.message || 'No se pudo guardar la ruta de API.')
  }

  cachedApiBaseUrl = normalizeApiBaseUrl(payload.config?.apiBaseUrl || normalized)

  return cachedApiBaseUrl
}

export async function resetApiBaseUrl() {
  return saveApiBaseUrl(getDefaultApiBaseUrl())
}

export async function refreshApiBaseUrl() {
  return loadApiBaseUrl({ force: true })
}

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

export function listEmployees() {
  return apiRequest('/empleados.php')
}

export function updateEmployee(id, employee) {
  return apiRequest(`/empleados.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(employee),
  })
}

export function deleteEmployee(id) {
  return apiRequest(`/empleados.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listShifts() {
  return apiRequest('/turnos.php')
}

export function createShift(shift) {
  return apiRequest('/turnos.php', {
    method: 'POST',
    body: JSON.stringify(shift),
  })
}

export function updateShift(id, shift) {
  return apiRequest(`/turnos.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(shift),
  })
}

export function deleteShift(id) {
  return apiRequest(`/turnos.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listDepartments() {
  return apiRequest('/departamentos.php')
}

export function createDepartment(department) {
  return apiRequest('/departamentos.php', {
    method: 'POST',
    body: JSON.stringify(department),
  })
}

export function updateDepartment(id, department) {
  return apiRequest(`/departamentos.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(department),
  })
}

export function deleteDepartment(id) {
  return apiRequest(`/departamentos.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

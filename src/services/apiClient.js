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

export function listRules() {
  return apiRequest('/reglas.php')
}

export function createRule(rule) {
  return apiRequest('/reglas.php', {
    method: 'POST',
    body: JSON.stringify(rule),
  })
}

export function updateRule(id, rule) {
  return apiRequest(`/reglas.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(rule),
  })
}

export function deleteRule(id) {
  return apiRequest(`/reglas.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listVacations() {
  return apiRequest('/vacaciones.php')
}

export function createVacation(vacation) {
  return apiRequest('/vacaciones.php', {
    method: 'POST',
    body: JSON.stringify(vacation),
  })
}

export function updateVacation(id, vacation) {
  return apiRequest(`/vacaciones.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(vacation),
  })
}

export function deleteVacation(id) {
  return apiRequest(`/vacaciones.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listHolidays() {
  return apiRequest('/festivos.php')
}

export function createHoliday(holiday) {
  return apiRequest('/festivos.php', {
    method: 'POST',
    body: JSON.stringify(holiday),
  })
}

export function updateHoliday(id, holiday) {
  return apiRequest(`/festivos.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(holiday),
  })
}

export function deleteHoliday(id) {
  return apiRequest(`/festivos.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listLoans() {
  return apiRequest('/prestamos.php')
}

export function createLoan(loan) {
  return apiRequest('/prestamos.php', {
    method: 'POST',
    body: JSON.stringify(loan),
  })
}

export function updateLoan(id, loan) {
  return apiRequest(`/prestamos.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(loan),
  })
}

export function deleteLoan(id) {
  return apiRequest(`/prestamos.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function createLoanPayment(payment) {
  return apiRequest('/prestamo_pagos.php', {
    method: 'POST',
    body: JSON.stringify(payment),
  })
}

export function listImssConfigs() {
  return apiRequest('/imss.php')
}

export function updateImssConfig(employeeId, config) {
  return apiRequest(`/imss.php?id=${encodeURIComponent(employeeId)}`, {
    method: 'PUT',
    body: JSON.stringify(config),
  })
}

export function deleteImssConfig(employeeId) {
  return apiRequest(`/imss.php?id=${encodeURIComponent(employeeId)}`, {
    method: 'DELETE',
  })
}

export function listCompanies() {
  return apiRequest('/empresas.php')
}

export function createCompany(company) {
  return apiRequest('/empresas.php', {
    method: 'POST',
    body: JSON.stringify(company),
  })
}

export function updateCompany(id, company) {
  return apiRequest(`/empresas.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(company),
  })
}

export function deactivateCompany(id) {
  return apiRequest(`/empresas.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listBanks() {
  return apiRequest('/bancos.php')
}

export function createBank(bank) {
  return apiRequest('/bancos.php', {
    method: 'POST',
    body: JSON.stringify(bank),
  })
}

export function updateBank(id, bank) {
  return apiRequest(`/bancos.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(bank),
  })
}

export function deactivateBank(id) {
  return apiRequest(`/bancos.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
  })
}

export function listPayroll(params = {}) {
  const query = new URLSearchParams()

  if (params.inicio) {
    query.set('inicio', params.inicio)
  }

  if (params.fin) {
    query.set('fin', params.fin)
  }

  const suffix = query.toString() ? `?${query.toString()}` : ''

  return apiRequest(`/nomina.php${suffix}`)
}

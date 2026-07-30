<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Control de adeudos</span>
        <h1>Prestamos</h1>
        <p>Registra prestamos y genera el plan de pagos con carta responsiva para firma.</p>
      </div>
      <q-btn
        class="module-action"
        icon="add_card"
        label="Nuevo prestamo"
        outline
        @click="openCreate"
      />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="account_balance_wallet" />
        <div>
          <span>Prestamos</span>
          <strong>{{ loans.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="payments" />
        <div>
          <span>Total prestado</span>
          <strong>{{ formatCurrency(totalLoaned) }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="receipt_long" />
        <div>
          <span>Saldo</span>
          <strong>{{ formatCurrency(totalBalance) }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Prestamos registrados</h2>
          <p>Montos, plazos y documentos para firma del empleado.</p>
        </div>
        <q-input
          v-model="filter"
          dense
          outlined
          placeholder="Buscar prestamo"
          class="module-search"
        >
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <q-table
        :rows="loans"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_prestamo"
        flat
        hide-bottom
        class="module-table module-table--scroll"
      >
        <template #body-cell-actions="props">
          <q-td :props="props">
            <div class="table-actions">
              <q-btn
                flat
                round
                dense
                icon="payments"
                aria-label="Registrar pago"
                :disable="props.row.estatus === 'liquidado'"
                @click="openPayment(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="print"
                aria-label="Imprimir carta y pagares"
                @click="printLoan(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="account_balance_wallet" />
            <span>No hay prestamos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">{{
              editingLoan ? 'Editar prestamo' : 'Nuevo prestamo'
            }}</span>
            <h2>{{ selectedEmployeeName }}</h2>
          </div>
          <q-btn
            flat
            round
            dense
            icon="close"
            aria-label="Cerrar"
            :disable="saving"
            v-close-popup
          />
        </q-card-section>

        <q-form @submit.prevent="saveLoan">
          <q-card-section class="loan-form">
            <q-select
              v-model="form.empleado_id"
              outlined
              emit-value
              map-options
              label="Empleado"
              :options="employeeOptions"
              :disable="saving"
              :rules="[(value) => Boolean(value) || 'Selecciona un empleado']"
            />

            <div class="shift-form__grid">
              <q-input
                v-model.number="form.monto"
                outlined
                type="number"
                min="0"
                step="0.01"
                label="Monto del prestamo"
                :disable="saving"
              />
              <q-input
                v-model="form.fecha_prestamo"
                outlined
                type="date"
                label="Fecha del prestamo"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model.number="form.numero_pagos"
                outlined
                type="number"
                min="1"
                max="120"
                label="Numero de pagos"
                :disable="saving"
              />
              <q-select
                v-model="form.frecuencia_pago"
                outlined
                emit-value
                map-options
                label="Frecuencia"
                :options="frequencyOptions"
                :disable="saving"
              />
            </div>

            <q-input
              v-model="form.primer_pago"
              outlined
              type="date"
              label="Primer pago"
              :disable="saving"
            />

            <div class="loan-plan-preview">
              <div>
                <span>Pago estimado</span>
                <strong>{{ formatCurrency(estimatedPayment) }}</strong>
              </div>
              <div>
                <span>Total</span>
                <strong>{{ formatCurrency(form.monto) }}</strong>
              </div>
            </div>

            <q-input
              v-model.trim="form.notas"
              outlined
              type="textarea"
              label="Notas"
              autogrow
              :disable="saving"
            />

            <q-banner v-if="formError" rounded class="module-error">
              {{ formError }}
            </q-banner>
          </q-card-section>

          <q-card-actions align="right" class="module-dialog__actions">
            <q-btn flat label="Cancelar" :disable="saving" v-close-popup />
            <q-btn unelevated icon="save" label="Guardar" type="submit" :loading="saving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="paymentDialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">Proceso de pago</span>
            <h2>{{ paymentLoan?.empleado || 'Prestamo' }}</h2>
          </div>
          <q-btn
            flat
            round
            dense
            icon="close"
            aria-label="Cerrar"
            :disable="paying"
            v-close-popup
          />
        </q-card-section>

        <q-form @submit.prevent="savePayment">
          <q-card-section class="loan-form">
            <div class="loan-plan-preview">
              <div>
                <span>Pagado</span>
                <strong>{{ formatCurrency(paymentLoan?.pagado) }}</strong>
              </div>
              <div>
                <span>Saldo</span>
                <strong>{{ formatCurrency(paymentLoan?.saldo) }}</strong>
              </div>
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model.number="paymentForm.monto"
                outlined
                type="number"
                min="0"
                step="0.01"
                label="Monto del pago"
                :disable="paying"
              />
              <q-input
                v-model="paymentForm.fecha_pago"
                outlined
                type="date"
                label="Fecha de pago"
                :disable="paying"
              />
            </div>

            <q-input
              v-model.trim="paymentForm.notas"
              outlined
              type="textarea"
              label="Notas"
              autogrow
              :disable="paying"
            />

            <div class="loan-history">
              <h3>Historial de pagos</h3>
              <div v-if="paymentLoan?.pagos?.length" class="loan-history__list">
                <div v-for="payment in paymentLoan.pagos" :key="payment.id_pago">
                  <span>{{ formatDate(payment.fecha_pago) }}</span>
                  <strong>{{ formatCurrency(payment.monto) }}</strong>
                </div>
              </div>
              <p v-else>No hay pagos registrados.</p>
            </div>

            <q-banner v-if="paymentError" rounded class="module-error">
              {{ paymentError }}
            </q-banner>
          </q-card-section>

          <q-card-actions align="right" class="module-dialog__actions">
            <q-btn flat label="Cancelar" :disable="paying" v-close-popup />
            <q-btn unelevated icon="save" label="Registrar pago" type="submit" :loading="paying" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  createLoan,
  createLoanPayment,
  listEmployees,
  listLoans,
  updateLoan,
} from 'src/services/apiClient'

const loans = ref([])
const employees = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingLoan = ref(null)
const saving = ref(false)
const formError = ref('')
const paymentDialogOpen = ref(false)
const paymentLoan = ref(null)
const paying = ref(false)
const paymentError = ref('')
const today = new Date().toISOString().slice(0, 10)
const defaultForm = {
  empleado_id: null,
  monto: 0,
  fecha_prestamo: today,
  numero_pagos: 12,
  frecuencia_pago: 'mensual',
  primer_pago: today,
  notas: '',
}
const form = ref({ ...defaultForm })
const paymentForm = ref({
  prestamo_id: null,
  fecha_pago: today,
  monto: 0,
  notas: '',
})
const pagination = {
  rowsPerPage: 0,
}
const frequencyOptions = [
  { label: 'Semanal', value: 'semanal' },
  { label: 'Quincenal', value: 'quincenal' },
  { label: 'Mensual', value: 'mensual' },
]
const totalLoaned = computed(() =>
  loans.value.reduce((total, loan) => total + Number(loan.monto || 0), 0),
)
const totalBalance = computed(() =>
  loans.value.reduce((total, loan) => total + Number(loan.saldo || 0), 0),
)
const estimatedPayment = computed(() => {
  const amount = Number(form.value.monto || 0)
  const payments = Number(form.value.numero_pagos || 0)

  return payments > 0 ? amount / payments : 0
})
const selectedEmployeeName = computed(() => {
  const employee = employees.value.find(
    (item) => String(item.id) === String(form.value.empleado_id),
  )

  return employee?.nombre || 'Empleado'
})
const activeLoanEmployeeIds = computed(
  () =>
    new Set(
      loans.value
        .filter((loan) => loan.estatus === 'activo')
        .map((loan) => String(loan.empleado_id)),
    ),
)
const employeeOptions = computed(() =>
  employees.value
    .filter((employee) => {
      if (Number(employee.activo) !== 1) {
        return false
      }

      if (editingLoan.value && String(employee.id) === String(editingLoan.value.empleado_id)) {
        return true
      }

      return !activeLoanEmployeeIds.value.has(String(employee.id))
    })
    .map((employee) => ({
      label: employee.nombre,
      value: String(employee.id),
    })),
)
const columns = [
  {
    name: 'empleado',
    label: 'Empleado',
    field: 'empleado',
    align: 'left',
    sortable: true,
    format: (value, row) => value || `Empleado #${row.empleado_id}`,
  },
  {
    name: 'monto',
    label: 'Monto',
    field: 'monto',
    align: 'right',
    sortable: true,
    format: (value) => formatCurrency(value),
  },
  {
    name: 'fecha_prestamo',
    label: 'Fecha prestamo',
    field: 'fecha_prestamo',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  {
    name: 'pagado',
    label: 'Pagado',
    field: 'pagado',
    align: 'right',
    sortable: true,
    format: (value) => formatCurrency(value),
  },
  {
    name: 'saldo',
    label: 'Saldo',
    field: 'saldo',
    align: 'right',
    sortable: true,
    format: (value) => formatCurrency(value),
  },
  {
    name: 'estatus',
    label: 'Estatus',
    field: 'estatus',
    align: 'left',
    sortable: true,
    format: (value) => (value === 'liquidado' ? 'Liquidado' : 'Activo'),
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadPageData)

async function loadPageData() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [loansPayload, employeesPayload] = await Promise.all([listLoans(), listEmployees()])
    loans.value = loansPayload.loans || []
    employees.value = employeesPayload.employees || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadLoansOnly() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listLoans()
    loans.value = payload.loans || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingLoan.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

async function saveLoan() {
  saving.value = true
  formError.value = ''

  try {
    if (editingLoan.value) {
      await updateLoan(editingLoan.value.id_prestamo, form.value)
    } else {
      await createLoan(form.value)
    }

    dialogOpen.value = false
    await loadLoansOnly()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function printLoan(loan) {
  if (!loan) {
    return
  }

  const printWindow = window.open('', '_blank', 'width=900,height=1100')

  if (!printWindow) {
    return
  }

  printWindow.document.write(buildPrintableDocument(loan))
  printWindow.document.close()
  printWindow.onafterprint = () => printWindow.close()
  printWindow.addEventListener('load', () => {
    printWindow.focus()
    printWindow.print()
  })
}

function openPayment(loan) {
  paymentLoan.value = loan
  paymentError.value = ''
  paymentForm.value = {
    prestamo_id: loan.id_prestamo,
    fecha_pago: today,
    monto: Number(loan.saldo || 0),
    notas: '',
  }
  paymentDialogOpen.value = true
}

async function savePayment() {
  if (!paymentLoan.value) {
    return
  }

  paying.value = true
  paymentError.value = ''

  try {
    await createLoanPayment(paymentForm.value)
    paymentDialogOpen.value = false
    await loadLoansOnly()
  } catch (error) {
    paymentError.value = error.message
  } finally {
    paying.value = false
  }
}

function formatDate(value) {
  if (!value) {
    return 'Sin fecha'
  }

  const [year, month, day] = String(value).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day}/${month}/${year}`
}

function formatFrequency(value) {
  return (
    {
      semanal: 'Semanal',
      quincenal: 'Quincenal',
      mensual: 'Mensual',
    }[value] || value
  )
}

function formatCurrency(value) {
  const amount = Number(value)

  if (!Number.isFinite(amount)) {
    return '$0.00'
  }

  return amount.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
}

function buildPrintableDocument(loan) {
  const notes = loan.notas ? `<p>${escapeHtml(loan.notas)}</p>` : ''
  const payNotes = (loan.plan || [])
    .map(
      (payment) => `
        <article class="note">
          <header>
            <span>Pagare ${payment.numero}</span>
            <strong>${formatCurrency(payment.monto)}</strong>
          </header>
          <p>Debo y pagare a la empresa la cantidad indicada, correspondiente al prestamo recibido, con vencimiento el <strong>${formatDate(payment.fecha)}</strong>.</p>
          <div class="meta">
            <span>Saldo posterior: ${formatCurrency(payment.saldo)}</span>
            <span>${escapeHtml(loan.empleado || '')}</span>
          </div>
          <div class="signature">Firma del empleado</div>
        </article>
      `,
    )
    .join('')

  return `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8">
        <title>Prestamo - ${escapeHtml(loan.empleado || 'Empleado')}</title>
        <style>
          @page { size: letter; margin: 12mm; }
          * { box-sizing: border-box; }
          body { margin: 0; background: #fff; color: #102033; font-family: Arial, sans-serif; font-size: 12px; }
          .page { max-width: 186mm; margin: 0 auto; }
          .letter, .note { border: 1px solid #9aa8b8; border-radius: 4px; padding: 16px; margin: 0 0 10px; break-inside: avoid; page-break-inside: avoid; }
          h1 { margin: 0 0 12px; font-size: 28px; line-height: 1.05; }
          p { margin: 0 0 10px; line-height: 1.55; }
          .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 34px; }
          .signatures div, .signature { border-top: 1px solid #102033; padding-top: 7px; text-align: center; font-weight: 700; }
          .signatures span { display: block; color: #627086; font-size: 10px; margin-bottom: 4px; }
          .notes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
          .note header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
          .note header span { font-size: 10px; font-weight: 800; text-transform: uppercase; }
          .note header strong { font-size: 18px; }
          .meta { display: flex; justify-content: space-between; gap: 10px; margin: 14px 0 28px; color: #627086; font-size: 10px; font-weight: 700; }
          @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          }
        </style>
        <script>
          window.onafterprint = () => window.close();
        </scr${'ipt'}>
      </head>
      <body>
        <main class="page">
          <section class="letter">
            <h1>Carta responsiva de prestamo</h1>
            <p>Por medio de la presente, <strong>${escapeHtml(loan.empleado || '')}</strong> reconoce haber recibido un prestamo por la cantidad de <strong>${formatCurrency(loan.monto)}</strong>, otorgado el dia <strong>${formatDate(loan.fecha_prestamo)}</strong>.</p>
            <p>El empleado se compromete a liquidar dicho monto en <strong>${loan.numero_pagos}</strong> pago(s) con frecuencia <strong>${formatFrequency(loan.frecuencia_pago)}</strong>, iniciando el <strong>${formatDate(loan.primer_pago)}</strong>, conforme al plan de pagos incluido en este documento.</p>
            ${notes}
            <div class="signatures">
              <div><span>Empleado</span>${escapeHtml(loan.empleado || '')}</div>
              <div><span>Recursos humanos</span>Nombre y firma</div>
            </div>
          </section>
          <section class="notes">${payNotes}</section>
        </main>
      </body>
    </html>
  `
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}
</script>

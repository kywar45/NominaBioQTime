<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Gestion de descanso</span>
        <h1>Vacaciones</h1>
        <p>Asigna vacaciones a un empleado seleccionando dias y rango de fechas.</p>
      </div>
      <q-btn
        class="module-action"
        icon="add"
        label="Asignar vacaciones"
        outline
        @click="openCreate"
      />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="beach_access" />
        <div>
          <span>Asignaciones</span>
          <strong>{{ vacations.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="event_available" />
        <div>
          <span>Dias asignados</span>
          <strong>{{ formatNumber(assignedTotal) }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="groups" />
        <div>
          <span>Empleados</span>
          <strong>{{ employeeVacationCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Vacaciones asignadas</h2>
          <p>Periodos autorizados por recursos humanos.</p>
        </div>
        <q-input
          v-model="filter"
          dense
          outlined
          placeholder="Buscar empleado"
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
        :rows="vacations"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_vacacion"
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
                icon="edit"
                aria-label="Editar vacaciones"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar vacaciones"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="beach_access" />
            <span>No hay vacaciones asignadas.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">
              {{ editingVacation ? 'Editar vacaciones' : 'Asignar vacaciones' }}
            </span>
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

        <q-form @submit.prevent="saveVacation">
          <q-card-section class="vacation-form">
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
                v-model.number="form.dias_vacaciones"
                outlined
                type="number"
                min="0.5"
                step="0.5"
                label="Dias de vacaciones"
                :disable="saving"
              />
              <q-input
                v-model="form.fecha_inicio"
                outlined
                readonly
                label="Fecha inicio"
                :disable="saving"
              >
                <template #append>
                  <q-icon name="event" class="cursor-pointer">
                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                      <q-date
                        v-model="form.fecha_inicio"
                        mask="YYYY-MM-DD"
                        class="vacation-date-picker"
                      >
                        <div class="row items-center justify-end">
                          <q-btn v-close-popup label="Listo" color="primary" flat />
                        </div>
                      </q-date>
                    </q-popup-proxy>
                  </q-icon>
                </template>
              </q-input>
            </div>

            <q-input v-model="form.fecha_fin" outlined readonly label="Fecha fin" :disable="saving">
              <template #append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="form.fecha_fin" mask="YYYY-MM-DD" class="vacation-date-picker">
                      <div class="row items-center justify-end">
                        <q-btn v-close-popup label="Listo" color="primary" flat />
                      </div>
                    </q-date>
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>

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

    <q-dialog v-model="deleteDialogOpen">
      <q-card class="confirm-dialog">
        <q-card-section class="confirm-dialog__body">
          <q-icon name="delete" />
          <div>
            <span class="module-kicker">Eliminar vacaciones</span>
            <h2>Eliminar registro</h2>
            <p>
              El registro de vacaciones de "{{ vacationToDelete?.empleado }}" se eliminara de la
              base.
            </p>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="confirm-dialog__actions">
          <q-btn flat label="Cancelar" :disable="deleting" v-close-popup />
          <q-btn
            unelevated
            icon="delete"
            label="Eliminar"
            color="negative"
            :loading="deleting"
            @click="deleteSelectedVacation"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  createVacation,
  deleteVacation,
  listEmployees,
  listVacations,
  updateVacation,
} from 'src/services/apiClient'

const vacations = ref([])
const employees = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingVacation = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const vacationToDelete = ref(null)
const deleting = ref(false)
const today = new Date().toISOString().slice(0, 10)
const defaultForm = {
  empleado_id: null,
  dias_vacaciones: 1,
  fecha_inicio: today,
  fecha_fin: today,
  notas: '',
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}
const assignedTotal = computed(() =>
  vacations.value.reduce((total, vacation) => total + Number(vacation.dias_vacaciones || 0), 0),
)
const employeeVacationCount = computed(
  () => new Set(vacations.value.map((vacation) => String(vacation.empleado_id))).size,
)
const selectedEmployeeName = computed(() => {
  const employee = employees.value.find(
    (item) => String(item.id) === String(form.value.empleado_id),
  )

  return employee?.nombre || 'Empleado'
})
const employeeOptions = computed(() =>
  employees.value
    .filter((employee) => Number(employee.activo) === 1)
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
    name: 'dias_vacaciones',
    label: 'Dias',
    field: 'dias_vacaciones',
    align: 'center',
    sortable: true,
    format: (value) => formatNumber(value),
  },
  {
    name: 'fecha_inicio',
    label: 'Inicio',
    field: 'fecha_inicio',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  {
    name: 'fecha_fin',
    label: 'Fin',
    field: 'fecha_fin',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadPageData)

async function loadPageData() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [vacationsPayload, employeesPayload] = await Promise.all([
      listVacations(),
      listEmployees(),
    ])
    vacations.value = vacationsPayload.vacations || []
    employees.value = employeesPayload.employees || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadVacationsOnly() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listVacations()
    vacations.value = payload.vacations || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingVacation.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

function openEdit(vacation) {
  editingVacation.value = vacation
  formError.value = ''
  form.value = {
    empleado_id: vacation.empleado_id ? String(vacation.empleado_id) : null,
    dias_vacaciones: Number(vacation.dias_vacaciones || 1),
    fecha_inicio: vacation.fecha_inicio || today,
    fecha_fin: vacation.fecha_fin || today,
    notas: vacation.notas || '',
  }
  dialogOpen.value = true
}

async function saveVacation() {
  saving.value = true
  formError.value = ''

  try {
    if (editingVacation.value) {
      await updateVacation(editingVacation.value.id_vacacion, form.value)
    } else {
      await createVacation(form.value)
    }

    dialogOpen.value = false
    await loadVacationsOnly()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(vacation) {
  vacationToDelete.value = vacation
  deleteDialogOpen.value = true
}

async function deleteSelectedVacation() {
  if (!vacationToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteVacation(vacationToDelete.value.id_vacacion)
    deleteDialogOpen.value = false
    vacationToDelete.value = null
    await loadVacationsOnly()
  } catch (error) {
    errorMessage.value = error.message
    loading.value = false
  } finally {
    deleting.value = false
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

function formatNumber(value) {
  const numberValue = Number(value)

  if (!Number.isFinite(numberValue)) {
    return '0'
  }

  return numberValue.toLocaleString('es-MX', {
    minimumFractionDigits: Number.isInteger(numberValue) ? 0 : 1,
    maximumFractionDigits: 2,
  })
}
</script>

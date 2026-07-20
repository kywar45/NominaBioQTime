<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Gestion de personal</span>
        <p>Consulta empleados y asigna departamento, turno y sueldo para nomina.</p>
      </div>
      <section class="module-stats">
        <article class="module-stat">
          <q-icon name="groups" />
          <div>
            <span>Total</span>
            <strong>{{ employees.length }}</strong>
          </div>
        </article>
        <article class="module-stat">
          <q-icon name="verified" />
          <div>
            <span>Activos</span>
            <strong>{{ activeCount }}</strong>
          </div>
        </article>
        <article class="module-stat">
          <q-icon name="assignment_ind" />
          <div>
            <span>Configurados</span>
            <strong>{{ configuredCount }}</strong>
          </div>
        </article>
      </section>
    </header>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Directorio de empleados</h2>
          <p>Configura la informacion laboral sin crear empleados nuevos.</p>
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
        :rows="employees"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id"
        flat
        hide-bottom
        class="module-table module-table--scroll"
      >
        <template #body-cell-activo="props">
          <q-td :props="props">
            <span :class="['status-pill', Number(props.value) === 1 ? 'status-pill--on' : '']">
              {{ Number(props.value) === 1 ? 'Activo' : 'Inactivo' }}
            </span>
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <div class="table-actions">
              <q-btn
                flat
                round
                dense
                icon="edit"
                aria-label="Editar empleado"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar empleado"
                :disable="Number(props.row.activo) !== 1"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="person_search" />
            <span>No hay empleados cargados.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">Editar empleado</span>
            <h2>{{ editingEmployee?.nombre || 'Empleado' }}</h2>
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

        <q-form @submit.prevent="saveEmployee">
          <q-card-section class="employee-form">
            <q-input
              v-model="form.fecha_ingreso"
              outlined
              clearable
              type="date"
              label="Fecha de ingreso"
              :disable="saving"
            />

            <div class="shift-form__grid">
              <q-select
                v-model="form.departamento_id"
                outlined
                emit-value
                map-options
                clearable
                label="Departamento"
                :options="departmentOptions"
                :disable="saving"
              />
              <q-select
                v-model="form.turno_id"
                outlined
                emit-value
                map-options
                clearable
                label="Turno"
                :options="shiftOptions"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model.number="form.sueldo_base"
                outlined
                type="number"
                min="0"
                step="0.01"
                label="Sueldo"
                :disable="saving"
              />
              <q-select
                v-model="form.tipo_sueldo"
                outlined
                clearable
                label="Tipo de sueldo"
                :options="salaryTypeOptions"
                :disable="saving"
              />
            </div>

            <div class="employee-form__single">
              <div class="shift-form__toggles">
                <q-toggle v-model="form.activo" label="Empleado activo" :disable="saving" />
              </div>
            </div>

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
            <span class="module-kicker">Eliminar empleado</span>
            <h2>Desactivar empleado</h2>
            <p>
              El empleado "{{ employeeToDelete?.nombre }}" quedara inactivo, sin borrarse de la
              base.
            </p>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="confirm-dialog__actions">
          <q-btn flat label="Cancelar" :disable="deleting" v-close-popup />
          <q-btn
            unelevated
            icon="delete"
            label="Desactivar"
            color="negative"
            :loading="deleting"
            @click="deleteSelectedEmployee"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  deleteEmployee,
  listDepartments,
  listEmployees,
  listShifts,
  updateEmployee,
} from 'src/services/apiClient'

const employees = ref([])
const departments = ref([])
const shifts = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingEmployee = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const employeeToDelete = ref(null)
const deleting = ref(false)
const today = new Date().toISOString().slice(0, 10)
const salaryTypeOptions = ['diario', 'semanal', 'quincenal', 'mensual']
const defaultForm = {
  fecha_ingreso: null,
  departamento_id: null,
  turno_id: null,
  sueldo_base: null,
  tipo_sueldo: null,
  fecha_inicio: today,
  activo: true,
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}
const departmentOptions = computed(() =>
  departments.value
    .filter((department) => Number(department.activo) === 1)
    .map((department) => ({
      label: department.nombre_departamento,
      value: Number(department.id_departamento),
    })),
)
const shiftOptions = computed(() =>
  shifts.value
    .filter((shift) => Number(shift.activo) === 1)
    .map((shift) => ({
      label: shift.nombre_turno,
      value: Number(shift.id_turno),
    })),
)
const activeCount = computed(
  () => employees.value.filter((employee) => Number(employee.activo) === 1).length,
)
const configuredCount = computed(
  () =>
    employees.value.filter(
      (employee) =>
        Boolean(employee.departamento_id) ||
        Boolean(employee.turno_id) ||
        employee.sueldo_base !== null,
    ).length,
)
const columns = [
  { name: 'nombre', label: 'Empleado', field: 'nombre', align: 'left', sortable: true },
  {
    name: 'fecha_ingreso',
    label: 'Ingreso',
    field: 'fecha_ingreso',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  {
    name: 'departamento',
    label: 'Departamento',
    field: 'departamento',
    align: 'left',
    sortable: true,
    format: (value) => value || 'Sin configurar',
  },
  {
    name: 'turno',
    label: 'Turno',
    field: 'turno',
    align: 'left',
    sortable: true,
    format: (value, row) => value || (row.turno_id ? `Turno #${row.turno_id}` : 'Sin configurar'),
  },
  {
    name: 'sueldo_base',
    label: 'Sueldo',
    field: 'sueldo_base',
    align: 'right',
    sortable: true,
    format: (value, row) => formatSalary(value, row.tipo_sueldo),
  },
  { name: 'activo', label: 'Estatus', field: 'activo', align: 'center', sortable: true },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadPageData)

async function loadPageData() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [employeesPayload, departmentsPayload, shiftsPayload] = await Promise.all([
      listEmployees(),
      listDepartments(),
      listShifts(),
    ])
    employees.value = employeesPayload.employees || []
    departments.value = departmentsPayload.departments || []
    shifts.value = shiftsPayload.shifts || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadEmployeesOnly() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listEmployees()
    employees.value = payload.employees || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openEdit(employee) {
  editingEmployee.value = employee
  formError.value = ''
  form.value = {
    fecha_ingreso: employee.fecha_ingreso || null,
    departamento_id: employee.departamento_id ? Number(employee.departamento_id) : null,
    turno_id: employee.turno_id ? Number(employee.turno_id) : null,
    sueldo_base:
      employee.sueldo_base === null || employee.sueldo_base === undefined
        ? null
        : Number(employee.sueldo_base),
    tipo_sueldo: employee.tipo_sueldo || null,
    fecha_inicio: employee.configuracion_fecha_inicio || employee.fecha_ingreso || today,
    activo: Number(employee.activo) === 1,
  }
  dialogOpen.value = true
}

async function saveEmployee() {
  if (!editingEmployee.value) {
    return
  }

  saving.value = true
  formError.value = ''

  try {
    await updateEmployee(editingEmployee.value.id, form.value)
    dialogOpen.value = false
    await loadEmployeesOnly()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(employee) {
  employeeToDelete.value = employee
  deleteDialogOpen.value = true
}

async function deleteSelectedEmployee() {
  if (!employeeToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteEmployee(employeeToDelete.value.id)
    deleteDialogOpen.value = false
    employeeToDelete.value = null
    await loadEmployeesOnly()
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

function formatSalary(value, type) {
  if (value === null || value === undefined || value === '') {
    return 'Sin configurar'
  }

  const amount = Number(value)
  const formattedAmount = Number.isFinite(amount)
    ? amount.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
    : value
  const salaryType = type ? ` / ${type}` : ''

  return `${formattedAmount}${salaryType}`
}
</script>

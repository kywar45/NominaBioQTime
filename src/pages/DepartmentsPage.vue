<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Estructura interna</span>

        <p>Organiza areas y agrupaciones para reportes de nomina.</p>
      </div>
      <q-btn
        class="module-action"
        icon="domain_add"
        label="Nuevo departamento"
        outline
        @click="openCreate"
      />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="apartment" />
        <div>
          <span>Departamentos</span>
          <strong>{{ departments.length }}</strong>
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
        <q-icon name="description" />
        <div>
          <span>Con descripcion</span>
          <strong>{{ withDescriptionCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Areas registradas</h2>
          <p>Base para clasificar empleados y costos de nomina.</p>
        </div>
        <q-input
          v-model="filter"
          dense
          outlined
          placeholder="Buscar departamento"
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
        :rows="departments"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_departamento"
        flat
        hide-bottom
        class="module-table"
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
                aria-label="Editar departamento"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar departamento"
                :disable="Number(props.row.activo) !== 1"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="apartment" />
            <span>No hay departamentos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">
              {{ editingDepartment ? 'Editar departamento' : 'Nuevo departamento' }}
            </span>
            <h2>{{ form.nombre_departamento || 'Departamento' }}</h2>
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

        <q-form @submit.prevent="saveDepartment">
          <q-card-section class="department-form">
            <q-input
              v-model.trim="form.nombre_departamento"
              outlined
              label="Nombre del departamento"
              :disable="saving"
              :rules="[(value) => Boolean(value) || 'Captura el nombre']"
            />

            <q-input
              v-model.trim="form.descripcion"
              outlined
              type="textarea"
              label="Descripcion"
              autogrow
              :disable="saving"
            />

            <div class="shift-form__toggles">
              <q-toggle v-model="form.activo" label="Activo" :disable="saving" />
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
            <span class="module-kicker">Eliminar departamento</span>
            <h2>Desactivar departamento</h2>
            <p>
              El departamento "{{ departmentToDelete?.nombre_departamento }}" quedara inactivo, sin
              borrarse de la base.
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
            @click="deleteSelectedDepartment"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  createDepartment,
  deleteDepartment,
  listDepartments,
  updateDepartment,
} from 'src/services/apiClient'

const departments = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingDepartment = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const departmentToDelete = ref(null)
const deleting = ref(false)
const defaultForm = {
  nombre_departamento: '',
  descripcion: '',
  activo: true,
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}
const activeCount = computed(
  () => departments.value.filter((department) => Number(department.activo) === 1).length,
)
const withDescriptionCount = computed(
  () => departments.value.filter((department) => Boolean(department.descripcion)).length,
)
const columns = [
  {
    name: 'nombre_departamento',
    label: 'Departamento',
    field: 'nombre_departamento',
    align: 'left',
    sortable: true,
  },
  {
    name: 'descripcion',
    label: 'Descripcion',
    field: 'descripcion',
    align: 'left',
    sortable: true,
    format: (value) => value || 'Sin descripcion',
  },
  { name: 'activo', label: 'Estatus', field: 'activo', align: 'center', sortable: true },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadDepartments)

async function loadDepartments() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listDepartments()
    departments.value = payload.departments || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingDepartment.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

function openEdit(department) {
  editingDepartment.value = department
  formError.value = ''
  form.value = {
    nombre_departamento: department.nombre_departamento || '',
    descripcion: department.descripcion || '',
    activo: Number(department.activo) === 1,
  }
  dialogOpen.value = true
}

async function saveDepartment() {
  saving.value = true
  formError.value = ''

  try {
    if (editingDepartment.value) {
      await updateDepartment(editingDepartment.value.id_departamento, form.value)
    } else {
      await createDepartment(form.value)
    }

    dialogOpen.value = false
    await loadDepartments()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(department) {
  departmentToDelete.value = department
  deleteDialogOpen.value = true
}

async function deleteSelectedDepartment() {
  if (!departmentToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteDepartment(departmentToDelete.value.id_departamento)
    deleteDialogOpen.value = false
    departmentToDelete.value = null
    await loadDepartments()
  } catch (error) {
    errorMessage.value = error.message
    loading.value = false
  } finally {
    deleting.value = false
  }
}
</script>

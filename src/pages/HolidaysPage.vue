<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Calendario laboral</span>
        <h1>Dias festivos</h1>
        <p>Selecciona los dias no laborables para asistencia y nomina.</p>
      </div>
      <q-btn class="module-action" icon="event" label="Nuevo festivo" outline @click="openCreate" />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="event" />
        <div>
          <span>Festivos</span>
          <strong>{{ holidays.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="calendar_month" />
        <div>
          <span>Este ano</span>
          <strong>{{ currentYearCount }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="work_off" />
        <div>
          <span>No laborables</span>
          <strong>{{ nonWorkingCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Catalogo de dias festivos</h2>
          <p>Fechas que deben omitirse del calendario laboral.</p>
        </div>
        <q-input v-model="filter" dense outlined placeholder="Buscar festivo" class="module-search">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <q-table
        :rows="holidays"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_festivo"
        flat
        hide-bottom
        class="module-table module-table--scroll"
      >
        <template #body-cell-no_laborable="props">
          <q-td :props="props">
            <span :class="['status-pill', Number(props.value) === 1 ? 'status-pill--on' : '']">
              {{ Number(props.value) === 1 ? 'No laborable' : 'Laborable' }}
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
                aria-label="Editar dia festivo"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar dia festivo"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="event_busy" />
            <span>No hay dias festivos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">{{
              editingHoliday ? 'Editar festivo' : 'Nuevo festivo'
            }}</span>
            <h2>{{ form.nombre || 'Dia festivo' }}</h2>
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

        <q-form @submit.prevent="saveHoliday">
          <q-card-section class="holiday-form">
            <q-input
              v-model.trim="form.nombre"
              outlined
              label="Nombre del dia festivo"
              :disable="saving"
              :rules="[(value) => Boolean(value) || 'Captura el nombre']"
            />

            <q-input v-model="form.fecha" outlined readonly label="Fecha" :disable="saving">
              <template #append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="form.fecha" mask="YYYY-MM-DD" class="vacation-date-picker">
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

            <div class="shift-form__toggles">
              <q-toggle v-model="form.no_laborable" label="No laborable" :disable="saving" />
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
            <span class="module-kicker">Eliminar festivo</span>
            <h2>Eliminar registro</h2>
            <p>El dia festivo "{{ holidayToDelete?.nombre }}" se eliminara de la base.</p>
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
            @click="deleteSelectedHoliday"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { createHoliday, deleteHoliday, listHolidays, updateHoliday } from 'src/services/apiClient'

const holidays = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingHoliday = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const holidayToDelete = ref(null)
const deleting = ref(false)
const today = new Date().toISOString().slice(0, 10)
const currentYear = new Date().getFullYear()
const defaultForm = {
  nombre: '',
  fecha: today,
  no_laborable: true,
  notas: '',
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}
const currentYearCount = computed(
  () =>
    holidays.value.filter((holiday) => Number(String(holiday.fecha).slice(0, 4)) === currentYear)
      .length,
)
const nonWorkingCount = computed(
  () => holidays.value.filter((holiday) => Number(holiday.no_laborable) === 1).length,
)
const columns = [
  { name: 'nombre', label: 'Festivo', field: 'nombre', align: 'left', sortable: true },
  {
    name: 'fecha',
    label: 'Fecha',
    field: 'fecha',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  {
    name: 'no_laborable',
    label: 'Tipo',
    field: 'no_laborable',
    align: 'center',
    sortable: true,
  },
  {
    name: 'notas',
    label: 'Notas',
    field: 'notas',
    align: 'left',
    format: (value) => value || 'Sin notas',
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadHolidays)

async function loadHolidays() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listHolidays()
    holidays.value = payload.holidays || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingHoliday.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

function openEdit(holiday) {
  editingHoliday.value = holiday
  formError.value = ''
  form.value = {
    nombre: holiday.nombre || '',
    fecha: holiday.fecha || today,
    no_laborable: Number(holiday.no_laborable) === 1,
    notas: holiday.notas || '',
  }
  dialogOpen.value = true
}

async function saveHoliday() {
  saving.value = true
  formError.value = ''

  try {
    if (editingHoliday.value) {
      await updateHoliday(editingHoliday.value.id_festivo, form.value)
    } else {
      await createHoliday(form.value)
    }

    dialogOpen.value = false
    await loadHolidays()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(holiday) {
  holidayToDelete.value = holiday
  deleteDialogOpen.value = true
}

async function deleteSelectedHoliday() {
  if (!holidayToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteHoliday(holidayToDelete.value.id_festivo)
    deleteDialogOpen.value = false
    holidayToDelete.value = null
    await loadHolidays()
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
</script>

<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Control de jornada</span>

        <p>
          Define horarios, tolerancias, umbrales de horas extra y turnos que terminan al dia
          siguiente.
        </p>
      </div>
      <q-btn
        class="module-action"
        icon="add_alarm"
        label="Nuevo turno"
        outline
        @click="openCreate"
      />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="schedule" />
        <div>
          <span>Turnos</span>
          <strong>{{ shifts.length }}</strong>
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
        <q-icon name="dark_mode" />
        <div>
          <span>Nocturnos</span>
          <strong>{{ nightCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Catalogo de turnos</h2>
          <p>Min. para hora extra indica despues de cuantos minutos se considera tiempo extra.</p>
        </div>
        <q-input v-model="filter" dense outlined placeholder="Buscar turno" class="module-search">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <q-table
        :rows="shifts"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_turno"
        flat
        hide-bottom
        class="module-table"
      >
        <template #body-cell-color="props">
          <q-td :props="props">
            <span class="color-chip">
              <span class="color-chip__swatch" :style="{ background: props.value }" />
              {{ props.value }}
            </span>
          </q-td>
        </template>

        <template #body-cell-activo="props">
          <q-td :props="props">
            <span :class="['status-pill', Number(props.value) === 1 ? 'status-pill--on' : '']">
              {{ Number(props.value) === 1 ? 'Activo' : 'Inactivo' }}
            </span>
          </q-td>
        </template>

        <template #body-cell-turno_nocturno="props">
          <q-td :props="props">
            <span
              :class="[
                'status-pill',
                Number(props.value) === 1 ? 'status-pill--night' : 'status-pill--off',
              ]"
            >
              {{ Number(props.value) === 1 ? 'Si' : 'No' }}
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
                aria-label="Editar turno"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar turno"
                :disable="Number(props.row.activo) !== 1"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="event_busy" />
            <span>No hay turnos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">{{ editingShift ? 'Editar turno' : 'Nuevo turno' }}</span>
            <h2>{{ form.nombre_turno || 'Turno' }}</h2>
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

        <q-form @submit.prevent="saveShift">
          <q-card-section class="shift-form">
            <q-input
              v-model.trim="form.nombre_turno"
              outlined
              label="Nombre del turno"
              :disable="saving"
              :rules="[(value) => Boolean(value) || 'Captura el nombre']"
            />

            <div class="shift-form__grid">
              <q-input
                v-model="form.hora_inicio"
                outlined
                type="time"
                label="Hora de inicio"
                :disable="saving"
                :rules="[(value) => Boolean(value) || 'Captura la hora']"
              />
              <q-input
                v-model.number="form.horas_trabajo"
                outlined
                type="number"
                step="0.25"
                min="0"
                label="Horas de trabajo"
                :disable="saving"
                :rules="[(value) => Number(value) > 0 || 'Debe ser mayor a cero']"
              />
            </div>

            <div class="shift-form__grid shift-form__grid--three">
              <q-input
                v-model.number="form.min_comida"
                outlined
                type="number"
                min="0"
                label="Minutos comida"
                :disable="saving"
              />
              <q-input
                v-model.number="form.min_excepcion"
                outlined
                type="number"
                min="0"
                label="Tolerancia de llegada minutos"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model.number="form.min_horas_extras"
                outlined
                type="number"
                min="0"
                label="Minutos para hora extra"
                hint="Despues de cuantos minutos cuenta como hora extra."
                :disable="saving"
              />
              <div class="shift-color-picker">
                <span class="shift-color-label">Color</span>
                <label class="shift-color-preview">
                  <input v-model="form.color" type="color" :disabled="saving" />
                  <span class="shift-color-preview__swatch" :style="{ background: form.color }" />
                  <strong>{{ form.color }}</strong>
                </label>
              </div>
            </div>

            <div class="shift-form__grid">
              <div class="shift-form__toggles">
                <div>
                  <q-toggle
                    v-model="form.turno_nocturno"
                    label="Turno nocturno"
                    :disable="saving"
                  />
                  <span class="shift-form__hint">Finaliza al dia siguiente.</span>
                </div>
              </div>
              <div class="shift-form__toggles">
                <q-toggle v-model="form.activo" label="Activo" :disable="saving" />
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
            <span class="module-kicker">Eliminar turno</span>
            <h2>Desactivar turno</h2>
            <p>
              El turno "{{ shiftToDelete?.nombre_turno }}" quedara inactivo, sin borrarse de la
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
            @click="deleteSelectedShift"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { createShift, deleteShift, listShifts, updateShift } from 'src/services/apiClient'

const shifts = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingShift = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const shiftToDelete = ref(null)
const deleting = ref(false)
const defaultForm = {
  nombre_turno: '',
  hora_inicio: '08:00',
  horas_trabajo: 8,
  min_comida: 0,
  min_excepcion: 0,
  min_horas_extras: 0,
  color: '#1976D2',
  turno_nocturno: false,
  activo: true,
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}
const activeCount = computed(
  () => shifts.value.filter((shift) => Number(shift.activo) === 1).length,
)
const nightCount = computed(
  () => shifts.value.filter((shift) => Number(shift.turno_nocturno) === 1).length,
)
const columns = [
  { name: 'nombre_turno', label: 'Turno', field: 'nombre_turno', align: 'left', sortable: true },
  {
    name: 'hora_inicio',
    label: 'Inicio',
    field: 'hora_inicio',
    align: 'center',
    sortable: true,
    format: (value) => formatTime(value),
  },
  {
    name: 'horas_trabajo',
    label: 'Horas',
    field: 'horas_trabajo',
    align: 'center',
    sortable: true,
    format: (value) => `${formatNumber(value)} h`,
  },
  {
    name: 'min_comida',
    label: 'Comida',
    field: 'min_comida',
    align: 'center',
    sortable: true,
    format: (value) => `${Number(value || 0)} min`,
  },
  {
    name: 'min_excepcion',
    label: 'Tolerancia',
    field: 'min_excepcion',
    align: 'center',
    sortable: true,
    format: (value) => `${Number(value || 0)} min`,
  },
  {
    name: 'min_horas_extras',
    label: 'Min. para hora extra',
    field: 'min_horas_extras',
    align: 'center',
    sortable: true,
    format: (value) => `${Number(value || 0)} min`,
  },
  { name: 'color', label: 'Color', field: 'color', align: 'left' },
  {
    name: 'turno_nocturno',
    label: 'Nocturno',
    field: 'turno_nocturno',
    align: 'center',
    sortable: true,
  },
  { name: 'activo', label: 'Estatus', field: 'activo', align: 'center', sortable: true },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadShifts)

async function loadShifts() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listShifts()
    shifts.value = payload.shifts || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingShift.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

function openEdit(shift) {
  editingShift.value = shift
  formError.value = ''
  form.value = {
    nombre_turno: shift.nombre_turno || '',
    hora_inicio: formatTime(shift.hora_inicio),
    horas_trabajo: Number(shift.horas_trabajo || 0),
    min_comida: Number(shift.min_comida || 0),
    min_excepcion: Number(shift.min_excepcion || 0),
    min_horas_extras: Number(shift.min_horas_extras || 0),
    color: shift.color || '#1976D2',
    turno_nocturno: Number(shift.turno_nocturno) === 1,
    activo: Number(shift.activo) === 1,
  }
  dialogOpen.value = true
}

async function saveShift() {
  saving.value = true
  formError.value = ''

  try {
    if (editingShift.value) {
      await updateShift(editingShift.value.id_turno, form.value)
    } else {
      await createShift(form.value)
    }

    dialogOpen.value = false
    await loadShifts()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(shift) {
  shiftToDelete.value = shift
  deleteDialogOpen.value = true
}

async function deleteSelectedShift() {
  if (!shiftToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteShift(shiftToDelete.value.id_turno)
    deleteDialogOpen.value = false
    shiftToDelete.value = null
    await loadShifts()
  } catch (error) {
    errorMessage.value = error.message
    loading.value = false
  } finally {
    deleting.value = false
  }
}

function formatTime(value) {
  return value ? String(value).slice(0, 5) : 'Sin hora'
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

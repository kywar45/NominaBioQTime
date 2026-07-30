<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Politicas de calculo</span>
        <h1>Reglas</h1>
        <p>
          Configura sanciones y bonificaciones por empleado, turno, departamento o toda la nomina.
        </p>
      </div>
      <q-btn class="module-action" icon="rule" label="Nueva regla" outline @click="openCreate" />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="rule" />
        <div>
          <span>Reglas</span>
          <strong>{{ rules.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="remove_circle" />
        <div>
          <span>Sanciones</span>
          <strong>{{ sanctionCount }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="add_circle" />
        <div>
          <span>Bonificaciones</span>
          <strong>{{ bonusCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Reglas de negocio</h2>
          <p>Condiciones que transforman checadas en incidencias de nomina.</p>
        </div>
        <q-input v-model="filter" dense outlined placeholder="Buscar regla" class="module-search">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <q-table
        :rows="rules"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="id_regla"
        flat
        hide-bottom
        class="module-table module-table--scroll"
      >
        <template #body-cell-tipo="props">
          <q-td :props="props">
            <span :class="['status-pill', ruleTypeClass(props.value)]">
              {{ formatRuleType(props.value) }}
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

        <template #body-cell-actions="props">
          <q-td :props="props">
            <div class="table-actions">
              <q-btn
                flat
                round
                dense
                icon="edit"
                aria-label="Editar regla"
                @click="openEdit(props.row)"
              />
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar regla"
                :disable="Number(props.row.activo) !== 1"
                @click="confirmDelete(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="rule_folder" />
            <span>No hay reglas cargadas.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">{{ editingRule ? 'Editar regla' : 'Nueva regla' }}</span>
            <h2>{{ form.nombre || 'Regla' }}</h2>
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

        <q-form @submit.prevent="saveRule">
          <q-card-section class="rule-form">
            <q-input
              v-model.trim="form.nombre"
              outlined
              label="Nombre de la regla"
              :disable="saving"
              :rules="[(value) => Boolean(value) || 'Captura el nombre']"
            />

            <div class="shift-form__grid">
              <q-select
                v-model="form.tipo"
                outlined
                emit-value
                map-options
                label="Tipo"
                :options="ruleTypeOptions"
                :disable="saving"
              />
              <q-select
                v-model="form.frecuencia"
                outlined
                emit-value
                map-options
                label="Frecuencia"
                :options="frequencyOptions"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <q-select
                v-model="form.alcance"
                outlined
                emit-value
                map-options
                label="Aplicar a"
                :options="scopeOptions"
                :disable="saving"
                @update:model-value="form.target_id = null"
              />
              <q-select
                v-model="form.target_id"
                outlined
                emit-value
                map-options
                clearable
                label="Seleccion"
                :options="targetOptions"
                :disable="saving || form.alcance === 'todos'"
              />
            </div>

            <div class="shift-form__grid">
              <q-select
                v-model="form.condicion"
                outlined
                emit-value
                map-options
                label="Condicion"
                :options="conditionOptions"
                :disable="saving"
              />
              <q-select
                v-model="form.operador"
                outlined
                emit-value
                map-options
                label="Operador"
                :options="operatorOptions"
                :disable="saving || !needsConditionValue"
              />
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model.number="form.valor_condicion"
                outlined
                type="number"
                min="0"
                step="0.01"
                label="Valor de condicion"
                :disable="saving || !needsConditionValue"
              />
              <q-select
                v-model="form.tipo_valor"
                outlined
                emit-value
                map-options
                label="Impacto"
                :options="valueTypeOptions"
                :disable="saving"
              />
            </div>

            <q-input
              v-model.number="form.valor"
              outlined
              type="number"
              min="0"
              step="0.01"
              label="Valor a aplicar"
              :disable="saving"
            />

            <q-input
              v-model.trim="form.descripcion"
              outlined
              type="textarea"
              label="Notas"
              autogrow
              :disable="saving"
            />

            <div class="shift-form__toggles">
              <q-toggle v-model="form.activo" label="Regla activa" :disable="saving" />
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
            <span class="module-kicker">Eliminar regla</span>
            <h2>Desactivar regla</h2>
            <p>La regla "{{ ruleToDelete?.nombre }}" quedara inactiva, sin borrarse de la base.</p>
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
            @click="deleteSelectedRule"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  createRule,
  deleteRule,
  listDepartments,
  listEmployees,
  listRules,
  listShifts,
  updateRule,
} from 'src/services/apiClient'

const rules = ref([])
const employees = ref([])
const departments = ref([])
const shifts = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const editingRule = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteDialogOpen = ref(false)
const ruleToDelete = ref(null)
const deleting = ref(false)
const defaultForm = {
  nombre: '',
  descripcion: '',
  tipo: 'sancion',
  alcance: 'todos',
  target_id: null,
  condicion: 'retardo_minutos',
  operador: '>=',
  valor_condicion: 10,
  tipo_valor: 'monto',
  valor: 0,
  frecuencia: 'por_evento',
  activo: true,
}
const form = ref({ ...defaultForm })
const pagination = {
  rowsPerPage: 0,
}

const ruleTypeOptions = [
  { label: 'Sancion', value: 'sancion' },
  { label: 'Bonificacion', value: 'bonificacion' },
]
const scopeOptions = [
  { label: 'Todos', value: 'todos' },
  { label: 'Departamento', value: 'departamento' },
  { label: 'Turno', value: 'turno' },
  { label: 'Empleado', value: 'empleado' },
]
const conditionOptions = [
  { label: 'Retardo por minutos', value: 'retardo_minutos' },
  { label: 'Falta', value: 'falta' },
  { label: 'Hora extra por minutos', value: 'hora_extra_minutos' },
  { label: 'Asistencia perfecta', value: 'asistencia_perfecta' },
  { label: 'Manual', value: 'manual' },
]
const operatorOptions = [
  { label: 'Igual a', value: '=' },
  { label: 'Mayor o igual', value: '>=' },
  { label: 'Menor o igual', value: '<=' },
]
const valueTypeOptions = [
  { label: 'Monto', value: 'monto' },
  { label: 'Porcentaje', value: 'porcentaje' },
  { label: 'Dias', value: 'dias' },
  { label: 'Minutos', value: 'minutos' },
]
const frequencyOptions = [
  { label: 'Por evento', value: 'por_evento' },
  { label: 'Por dia', value: 'por_dia' },
  { label: 'Por periodo', value: 'por_periodo' },
]

const sanctionCount = computed(() => rules.value.filter((rule) => rule.tipo === 'sancion').length)
const bonusCount = computed(() => rules.value.filter((rule) => rule.tipo === 'bonificacion').length)
const needsConditionValue = computed(() =>
  ['retardo_minutos', 'hora_extra_minutos'].includes(form.value.condicion),
)
const targetOptions = computed(() => {
  if (form.value.alcance === 'departamento') {
    return departments.value
      .filter((department) => Number(department.activo) === 1)
      .map((department) => ({
        label: department.nombre_departamento,
        value: String(department.id_departamento),
      }))
  }

  if (form.value.alcance === 'turno') {
    return shifts.value
      .filter((shift) => Number(shift.activo) === 1)
      .map((shift) => ({
        label: shift.nombre_turno,
        value: String(shift.id_turno),
      }))
  }

  if (form.value.alcance === 'empleado') {
    return employees.value
      .filter((employee) => Number(employee.activo) === 1)
      .map((employee) => ({
        label: employee.nombre,
        value: String(employee.id),
      }))
  }

  return []
})
const columns = [
  { name: 'nombre', label: 'Regla', field: 'nombre', align: 'left', sortable: true },
  { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'left', sortable: true },
  {
    name: 'alcance',
    label: 'Aplica a',
    field: 'alcance',
    align: 'left',
    sortable: true,
    format: (value, row) => formatScope(value, row.target_nombre),
  },
  {
    name: 'condicion',
    label: 'Condicion',
    field: 'condicion',
    align: 'left',
    sortable: true,
    format: (value, row) => formatCondition(row),
  },
  {
    name: 'impacto',
    label: 'Impacto',
    field: 'valor',
    align: 'left',
    sortable: true,
    format: (value, row) => formatImpact(row),
  },
  { name: 'activo', label: 'Estatus', field: 'activo', align: 'center', sortable: true },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

watch(
  () => form.value.condicion,
  () => {
    if (!needsConditionValue.value) {
      form.value.operador = null
      form.value.valor_condicion = null
    } else {
      form.value.operador = form.value.operador || '>='
      form.value.valor_condicion = form.value.valor_condicion ?? 0
    }
  },
)

onMounted(loadPageData)

async function loadPageData() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [rulesPayload, employeesPayload, departmentsPayload, shiftsPayload] = await Promise.all([
      listRules(),
      listEmployees(),
      listDepartments(),
      listShifts(),
    ])
    rules.value = rulesPayload.rules || []
    employees.value = employeesPayload.employees || []
    departments.value = departmentsPayload.departments || []
    shifts.value = shiftsPayload.shifts || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadRulesOnly() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listRules()
    rules.value = payload.rules || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingRule.value = null
  formError.value = ''
  form.value = { ...defaultForm }
  dialogOpen.value = true
}

function openEdit(rule) {
  editingRule.value = rule
  formError.value = ''
  form.value = {
    nombre: rule.nombre || '',
    descripcion: rule.descripcion || '',
    tipo: rule.tipo || 'sancion',
    alcance: rule.alcance || 'todos',
    target_id: rule.target_id ? String(rule.target_id) : null,
    condicion: rule.condicion || 'retardo_minutos',
    operador: rule.operador || '>=',
    valor_condicion:
      rule.valor_condicion === null || rule.valor_condicion === undefined
        ? null
        : Number(rule.valor_condicion),
    tipo_valor: rule.tipo_valor || 'monto',
    valor: Number(rule.valor || 0),
    frecuencia: rule.frecuencia || 'por_evento',
    activo: Number(rule.activo) === 1,
  }
  dialogOpen.value = true
}

async function saveRule() {
  saving.value = true
  formError.value = ''

  try {
    if (editingRule.value) {
      await updateRule(editingRule.value.id_regla, form.value)
    } else {
      await createRule(form.value)
    }

    dialogOpen.value = false
    await loadRulesOnly()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function confirmDelete(rule) {
  ruleToDelete.value = rule
  deleteDialogOpen.value = true
}

async function deleteSelectedRule() {
  if (!ruleToDelete.value) {
    return
  }

  deleting.value = true
  loading.value = true
  errorMessage.value = ''

  try {
    await deleteRule(ruleToDelete.value.id_regla)
    deleteDialogOpen.value = false
    ruleToDelete.value = null
    await loadRulesOnly()
  } catch (error) {
    errorMessage.value = error.message
    loading.value = false
  } finally {
    deleting.value = false
  }
}

function ruleTypeClass(type) {
  return type === 'bonificacion' ? 'status-pill--on' : 'status-pill--danger'
}

function formatRuleType(type) {
  return type === 'bonificacion' ? 'Bonificacion' : 'Sancion'
}

function formatScope(scope, targetName) {
  const scopeLabel =
    {
      todos: 'Todos',
      departamento: 'Departamento',
      turno: 'Turno',
      empleado: 'Empleado',
    }[scope] || scope

  return scope === 'todos' ? scopeLabel : `${scopeLabel}: ${targetName || 'Sin seleccionar'}`
}

function formatCondition(rule) {
  const label =
    {
      retardo_minutos: 'Retardo',
      falta: 'Falta',
      hora_extra_minutos: 'Hora extra',
      asistencia_perfecta: 'Asistencia perfecta',
      manual: 'Manual',
    }[rule.condicion] || rule.condicion

  if (!['retardo_minutos', 'hora_extra_minutos'].includes(rule.condicion)) {
    return label
  }

  return `${label} ${rule.operador || ''} ${formatNumber(rule.valor_condicion)} min`
}

function formatImpact(rule) {
  const value = formatNumber(rule.valor)
  const valueLabel =
    {
      monto: `$${value}`,
      porcentaje: `${value}%`,
      dias: `${value} dia(s)`,
      minutos: `${value} min`,
    }[rule.tipo_valor] || value
  const frequency =
    {
      por_evento: 'por evento',
      por_dia: 'por dia',
      por_periodo: 'por periodo',
    }[rule.frecuencia] || rule.frecuencia

  return `${valueLabel} ${frequency}`
}

function formatNumber(value) {
  const numberValue = Number(value)

  if (!Number.isFinite(numberValue)) {
    return '0'
  }

  return numberValue.toLocaleString('es-MX', {
    minimumFractionDigits: Number.isInteger(numberValue) ? 0 : 2,
    maximumFractionDigits: 2,
  })
}
</script>

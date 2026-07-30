<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Proceso de pago</span>
        <h1>Nomina</h1>
        <p>Revisa checadas por empleado desde registros de asistencia.</p>
      </div>
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="calendar_view_week" />
        <div>
          <span>Semanales</span>
          <strong>{{ weeklyRows.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="date_range" />
        <div>
          <span>Quincenales</span>
          <strong>{{ biweeklyRows.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="schedule" />
        <div>
          <span>Periodo</span>
          <strong>{{ periodLabel }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Checadas de nomina</h2>
          <p>Entrada, comida, regreso y salida por dia.</p>
        </div>
        <div class="payroll-toolbar">
          <q-btn
            flat
            round
            dense
            icon="chevron_left"
            aria-label="Periodo anterior"
            :disable="loading || !activeTab"
            @click="movePeriod(-1)"
          />
          <q-input
            v-model="selectorDate"
            dense
            outlined
            type="date"
            :label="selectorLabel"
            :disable="loading || !activeTab"
            @update:model-value="selectPeriod"
          />
          <q-btn
            flat
            round
            dense
            icon="chevron_right"
            aria-label="Periodo siguiente"
            :disable="loading || !activeTab"
            @click="movePeriod(1)"
          />
          <q-btn
            class="module-action"
            icon="refresh"
            label="Actualizar"
            outline
            :loading="loading"
            @click="loadPayroll"
          />
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
      </div>

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <div v-if="visibleTabs.length" class="employee-tabs payroll-tabs">
        <q-tabs
          v-model="activeTab"
          dense
          align="left"
          indicator-color="primary"
          active-color="primary"
          class="employee-tabs__nav"
        >
          <q-tab
            v-for="tab in visibleTabs"
            :key="tab.name"
            :name="tab.name"
            :icon="tab.icon"
            :label="`${tab.label} (${tab.rows.length})`"
          />
        </q-tabs>

        <q-tab-panels v-model="activeTab" animated class="employee-tabs__panels">
          <q-tab-panel
            v-for="tab in visibleTabs"
            :key="tab.name"
            :name="tab.name"
            class="employee-tabs__panel"
          >
            <q-table
              :rows="tab.rows"
              :columns="columns"
              :filter="filter"
              :pagination="pagination"
              :loading="loading"
              row-key="id"
              flat
              hide-bottom
              class="module-table module-table--scroll employee-tabs__table payroll-table"
            >
              <template #header-cell="props">
                <q-th :props="props">
                  <div v-if="props.col.day" class="payroll-day-head">
                    <span>{{ props.col.weekday }}</span>
                    <strong>{{ props.col.day }}</strong>
                  </div>
                  <span v-else>{{ props.col.label }}</span>
                </q-th>
              </template>

              <template #body-cell="props">
                <q-td :props="props">
                  <div
                    v-if="props.col.day"
                    :class="[
                      'payroll-check-cell',
                      isRestDay(props.row, props.col) ? 'payroll-check-cell--free' : '',
                    ]"
                  >
                    <strong v-if="isRestDay(props.row, props.col)">Dia libre</strong>
                    <template v-else-if="props.value">
                      <span>E: {{ props.value.entrada || '--:--' }}</span>
                      <span>C: {{ props.value.comida || '--:--' }}</span>
                      <span>R: {{ props.value.regreso || '--:--' }}</span>
                      <span>S: {{ props.value.salida || '--:--' }}</span>
                      <small v-if="dayCalculation(props.row, props.col.day)?.minutos_retardo">
                        Rtd {{ dayCalculation(props.row, props.col.day).minutos_retardo }}m
                      </small>
                      <small v-if="dayCalculation(props.row, props.col.day)?.minutos_extra">
                        Ext {{ dayCalculation(props.row, props.col.day).minutos_extra }}m
                      </small>
                    </template>
                    <strong v-else>Falta</strong>
                  </div>
                  <span v-else>{{ props.value }}</span>
                </q-td>
              </template>

              <template #no-data>
                <div class="module-empty employee-tabs__empty">
                  <q-icon name="payments" />
                  <span>No hay empleados en este esquema.</span>
                </div>
              </template>
            </q-table>
          </q-tab-panel>
        </q-tab-panels>
      </div>

      <div v-else class="module-empty">
        <q-icon name="payments" />
        <span>No hay empleados activos con sueldo semanal o quincenal.</span>
      </div>
    </section>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { listPayroll } from 'src/services/apiClient'

const payroll = ref({
  days: [],
  groups: {},
  period: { start: '', end: '' },
})
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const activeTab = ref('')
const selectorDate = ref(todayString())
const range = ref({
  inicio: '',
  fin: '',
})
const pagination = {
  rowsPerPage: 0,
}

const weeklyRows = computed(() => payroll.value.groups.semanal || [])
const biweeklyRows = computed(() => payroll.value.groups.quincenal || [])
const visibleTabs = computed(() =>
  [
    {
      name: 'semanal',
      label: 'Sueldo semanal',
      icon: 'calendar_view_week',
      rows: weeklyRows.value,
    },
    {
      name: 'quincenal',
      label: 'Sueldo quincenal',
      icon: 'date_range',
      rows: biweeklyRows.value,
    },
  ].filter((tab) => tab.rows.length > 0),
)
const periodLabel = computed(() => {
  if (!payroll.value.period.start || !payroll.value.period.end) {
    return 'Sin periodo'
  }

  return `${formatShortDate(payroll.value.period.start)} - ${formatShortDate(payroll.value.period.end)}`
})
const selectorLabel = computed(() =>
  activeTab.value === 'quincenal' ? 'Seleccionar quincena' : 'Seleccionar semana',
)
const columns = computed(() => [
  {
    name: 'nombre',
    label: 'Empleado',
    field: 'nombre',
    align: 'left',
    sortable: true,
  },
  {
    name: 'departamento',
    label: 'Departamento',
    field: (row) => row.departamento || 'Sin departamento',
    align: 'left',
    sortable: true,
  },
  ...payroll.value.days.map((day) => ({
    name: `day_${day.date}`,
    label: day.date,
    field: (row) => row.checadas?.[day.date] || null,
    align: 'center',
    day: day.date,
    weekdayIndex: Number(day.weekday_index),
    weekday: day.weekday,
  })),
  {
    name: 'dias_pagados',
    label: 'D-P',
    field: (row) => row.calculo?.dias_pagados || 0,
    align: 'center',
    sortable: true,
  },
  {
    name: 'faltas',
    label: 'Faltas',
    field: (row) => row.calculo?.faltas || 0,
    align: 'center',
    sortable: true,
  },
  {
    name: 'retardos',
    label: 'Retardos',
    field: (row) => `${row.calculo?.minutos_retardo || 0} min`,
    align: 'center',
    sortable: true,
  },
  {
    name: 'extras',
    label: 'H-E',
    field: (row) => `${row.calculo?.minutos_extra || 0} min`,
    align: 'center',
    sortable: true,
  },
  {
    name: 'descuentos',
    label: 'Descuentos',
    field: (row) =>
      formatCurrency(
        Number(row.calculo?.descuento_faltas || 0) + Number(row.calculo?.descuento_retardos || 0),
      ),
    align: 'right',
    sortable: true,
  },
  {
    name: 'extras_pago',
    label: 'Pago extra',
    field: (row) => formatCurrency(row.calculo?.pago_horas_extra),
    align: 'right',
    sortable: true,
  },
  {
    name: 'sueldo_base',
    label: 'Sueldo',
    field: (row) => formatCurrency(row.calculo?.sueldo_base ?? row.sueldo_base),
    align: 'right',
    sortable: true,
  },
  {
    name: 'total',
    label: 'Total',
    field: (row) => formatCurrency(row.calculo?.total),
    align: 'right',
    sortable: true,
  },
])

onMounted(() => {
  activeTab.value = 'semanal'
})

watch(
  visibleTabs,
  (tabs) => {
    if (!tabs.length) {
      activeTab.value = ''
      return
    }

    if (!tabs.some((tab) => tab.name === activeTab.value)) {
      activeTab.value = tabs[0].name
    }
  },
  { immediate: true },
)

watch(activeTab, (scheme, previousScheme) => {
  if (!scheme || scheme === previousScheme) {
    return
  }

  applyPeriodFromDate(selectorDate.value)
  loadPayroll()
})

async function loadPayroll() {
  loading.value = true
  errorMessage.value = ''

  try {
    const payload = await listPayroll(range.value)
    payroll.value = {
      days: payload.days || [],
      groups: payload.groups || {},
      period: payload.period || { start: '', end: '' },
    }
    range.value = {
      inicio: payroll.value.period.start,
      fin: payroll.value.period.end,
    }
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function selectPeriod(value) {
  selectorDate.value = value || todayString()
  applyPeriodFromDate(selectorDate.value)
  loadPayroll()
}

function movePeriod(direction) {
  const current = parseDate(selectorDate.value)

  if (activeTab.value === 'quincenal') {
    selectorDate.value = formatDate(addQuincena(current, direction))
  } else {
    selectorDate.value = formatDate(addDays(current, direction * 7))
  }

  applyPeriodFromDate(selectorDate.value)
  loadPayroll()
}

function applyPeriodFromDate(value) {
  const date = parseDate(value || todayString())
  const period = activeTab.value === 'quincenal' ? quincenaRange(date) : weekRange(date)

  range.value = {
    inicio: formatDate(period.start),
    fin: formatDate(period.end),
  }
}

function weekRange(date) {
  const day = date.getDay()
  const mondayOffset = day === 0 ? -6 : 1 - day
  const start = addDays(date, mondayOffset)

  return {
    start,
    end: addDays(start, 6),
  }
}

function quincenaRange(date) {
  const year = date.getFullYear()
  const month = date.getMonth()
  const day = date.getDate()

  if (day <= 15) {
    return {
      start: new Date(year, month, 1),
      end: new Date(year, month, 15),
    }
  }

  return {
    start: new Date(year, month, 16),
    end: new Date(year, month + 1, 0),
  }
}

function addQuincena(date, direction) {
  const year = date.getFullYear()
  const month = date.getMonth()
  const day = date.getDate()

  if (direction > 0) {
    return day <= 15 ? new Date(year, month, 16) : new Date(year, month + 1, 1)
  }

  return day <= 15 ? new Date(year, month - 1, 16) : new Date(year, month, 1)
}

function addDays(date, amount) {
  const next = new Date(date)
  next.setDate(next.getDate() + amount)

  return next
}

function parseDate(value) {
  const [year, month, day] = String(value || todayString())
    .split('-')
    .map(Number)

  return new Date(year, month - 1, day)
}

function formatDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function todayString() {
  return formatDate(new Date())
}

function formatShortDate(value) {
  if (!value) {
    return ''
  }

  const [, month, day] = String(value).split('-')

  return `${day}/${month}`
}

function isRestDay(row, column) {
  if (row.dia_libre === null || row.dia_libre === undefined || column.weekdayIndex === undefined) {
    return false
  }

  return Number(row.dia_libre) === Number(column.weekdayIndex)
}

function dayCalculation(row, date) {
  return row.calculo?.dias?.[date] || null
}

function formatCurrency(value) {
  const amount = Number(value)

  if (!Number.isFinite(amount)) {
    return '$0.00'
  }

  return amount.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
}
</script>

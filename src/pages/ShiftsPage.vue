<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Control de jornada</span>
        <h1>Turnos</h1>
        <p>Define horarios, tolerancias y bloques de trabajo para calcular asistencia.</p>
      </div>
      <q-btn class="module-action" icon="add_alarm" label="Nuevo turno" outline />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="schedule" />
        <div>
          <span>Turnos</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="work_history" />
        <div>
          <span>Asignados</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="timer" />
        <div>
          <span>Tolerancias</span>
          <strong>0</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Catalogo de turnos</h2>
          <p>Horarios disponibles para empleados y departamentos.</p>
        </div>
      </div>

      <q-table :rows="rows" :columns="columns" row-key="id" flat hide-bottom class="module-table">
        <template #no-data>
          <div class="module-empty">
            <q-icon name="event_busy" />
            <span>No hay turnos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
const rows = []
const columns = [
  { name: 'name', label: 'Turno', field: 'name', align: 'left' },
  { name: 'start', label: 'Entrada', field: 'start', align: 'center' },
  { name: 'end', label: 'Salida', field: 'end', align: 'center' },
  { name: 'break', label: 'Descanso', field: 'break', align: 'center' },
  { name: 'status', label: 'Estatus', field: 'status', align: 'center' },
]
</script>

<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Politicas de calculo</span>
        <h1>Reglas</h1>
        <p>Configura criterios de retardos, faltas, horas extra y descuentos.</p>
      </div>
      <q-btn class="module-action" icon="rule" label="Nueva regla" outline />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="rule" />
        <div>
          <span>Reglas</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="payments" />
        <div>
          <span>Nomina</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="fact_check" />
        <div>
          <span>Asistencia</span>
          <strong>0</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Reglas de negocio</h2>
          <p>Condiciones que transforman checadas en incidencias de nomina.</p>
        </div>
      </div>

      <q-table :rows="rows" :columns="columns" row-key="id" flat hide-bottom class="module-table">
        <template #no-data>
          <div class="module-empty">
            <q-icon name="rule_folder" />
            <span>No hay reglas cargadas.</span>
          </div>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
const rows = []
const columns = [
  { name: 'name', label: 'Regla', field: 'name', align: 'left' },
  { name: 'type', label: 'Tipo', field: 'type', align: 'left' },
  { name: 'condition', label: 'Condicion', field: 'condition', align: 'left' },
  { name: 'impact', label: 'Impacto', field: 'impact', align: 'left' },
  { name: 'status', label: 'Estatus', field: 'status', align: 'center' },
]
</script>

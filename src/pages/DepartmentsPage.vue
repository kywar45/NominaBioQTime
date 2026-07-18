<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Estructura interna</span>
        <h1>Departamentos</h1>
        <p>Organiza areas, responsables y agrupaciones para reportes de nomina.</p>
      </div>
      <q-btn class="module-action" icon="domain_add" label="Nuevo departamento" outline />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="apartment" />
        <div>
          <span>Departamentos</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="supervisor_account" />
        <div>
          <span>Responsables</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="badge" />
        <div>
          <span>Empleados</span>
          <strong>0</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Areas registradas</h2>
          <p>Base para clasificar empleados y costos de nomina.</p>
        </div>
      </div>

      <q-table :rows="rows" :columns="columns" row-key="id" flat hide-bottom class="module-table">
        <template #no-data>
          <div class="module-empty">
            <q-icon name="apartment" />
            <span>No hay departamentos cargados.</span>
          </div>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
const rows = []
const columns = [
  { name: 'code', label: 'Codigo', field: 'code', align: 'left' },
  { name: 'name', label: 'Departamento', field: 'name', align: 'left' },
  { name: 'manager', label: 'Responsable', field: 'manager', align: 'left' },
  { name: 'employees', label: 'Empleados', field: 'employees', align: 'center' },
  { name: 'status', label: 'Estatus', field: 'status', align: 'center' },
]
</script>

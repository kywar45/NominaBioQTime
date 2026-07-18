<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Gestion de personal</span>
        <h1>Empleados</h1>
        <p>Consulta y administra los empleados que alimentan la nomina y el checador.</p>
      </div>
      <q-btn class="module-action" icon="person_add" label="Nuevo empleado" outline />
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="groups" />
        <div>
          <span>Total</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="verified" />
        <div>
          <span>Activos</span>
          <strong>0</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="fingerprint" />
        <div>
          <span>Con checador</span>
          <strong>0</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Directorio de empleados</h2>
          <p>Listado preparado para conectarse a los registros reales.</p>
        </div>
        <q-input dense outlined placeholder="Buscar empleado" class="module-search">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>

      <q-table :rows="rows" :columns="columns" row-key="id" flat hide-bottom class="module-table">
        <template #no-data>
          <div class="module-empty">
            <q-icon name="person_search" />
            <span>No hay empleados cargados.</span>
          </div>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
const rows = []
const columns = [
  { name: 'number', label: 'No.', field: 'number', align: 'left' },
  { name: 'name', label: 'Empleado', field: 'name', align: 'left' },
  { name: 'department', label: 'Departamento', field: 'department', align: 'left' },
  { name: 'shift', label: 'Turno', field: 'shift', align: 'left' },
  { name: 'status', label: 'Estatus', field: 'status', align: 'center' },
]
</script>

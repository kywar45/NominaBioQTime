<template>
  <q-page class="dashboard-page">
    <header class="dashboard-header">
      <div>
        <span class="dashboard-kicker">Panel de nomina</span>
        <h1>Inicio</h1>
        <p>{{ displayUser }}</p>
      </div>
      <q-btn class="logout-btn" icon="logout" label="Salir" outline @click="logout" />
    </header>

    <section class="summary-grid">
      <article class="summary-card">
        <q-icon name="badge" />
        <div>
          <span>Usuario</span>
          <strong>{{ displayUser }}</strong>
        </div>
      </article>
      <article class="summary-card">
        <q-icon name="widgets" />
        <div>
          <span>Modulos</span>
          <strong>{{ permissions.length }}</strong>
        </div>
      </article>
      <article class="summary-card">
        <q-icon name="verified_user" />
        <div>
          <span>Permisos activos</span>
          <strong>{{ activePermissionCount }}</strong>
        </div>
      </article>
    </section>

    <section class="permissions-panel">
      <div class="panel-heading">
        <div>
          <h2>Permisos cargados</h2>
          <p>Modulos disponibles para esta sesion.</p>
        </div>
      </div>

      <q-table
        :rows="permissions"
        :columns="columns"
        row-key="code"
        flat
        hide-bottom
        class="dashboard-table"
      >
        <template #body-cell-can_view="props">
          <q-td :props="props"><span :class="permissionClass(props.value)">Ver</span></q-td>
        </template>
        <template #body-cell-can_create="props">
          <q-td :props="props"><span :class="permissionClass(props.value)">Crear</span></q-td>
        </template>
        <template #body-cell-can_update="props">
          <q-td :props="props"><span :class="permissionClass(props.value)">Editar</span></q-td>
        </template>
        <template #body-cell-can_delete="props">
          <q-td :props="props"><span :class="permissionClass(props.value)">Eliminar</span></q-td>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const session = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('nomina_session') || 'null')
  } catch {
    return null
  }
})
const permissions = computed(() => session.value?.permissions || [])
const displayUser = computed(
  () => session.value?.user?.full_name || session.value?.user?.username || 'Usuario',
)
const activePermissionCount = computed(() =>
  permissions.value.reduce((total, permission) => {
    return (
      total +
      Number(Boolean(permission.can_view)) +
      Number(Boolean(permission.can_create)) +
      Number(Boolean(permission.can_update)) +
      Number(Boolean(permission.can_delete))
    )
  }, 0),
)
const columns = [
  { name: 'name', label: 'Modulo', field: 'name', align: 'left' },
  { name: 'can_view', label: 'Ver', field: 'can_view', align: 'center' },
  { name: 'can_create', label: 'Crear', field: 'can_create', align: 'center' },
  { name: 'can_update', label: 'Editar', field: 'can_update', align: 'center' },
  { name: 'can_delete', label: 'Eliminar', field: 'can_delete', align: 'center' },
]

function permissionClass(value) {
  return ['permission-pill', value ? 'permission-pill--on' : 'permission-pill--off']
}

function logout() {
  localStorage.removeItem('nomina_session')
  router.push('/login')
}
</script>

<style scoped>
.dashboard-header {
  display: flex;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.dashboard-header h1 {
  margin: 4px 0 0;
  font-size: clamp(2.2rem, 4vw, 4rem);
  font-weight: 800;
  line-height: 1;
  color: #ffffff;
}

.dashboard-header p {
  margin: 10px 0 0;
  color: rgba(219, 231, 243, 0.74);
  font-size: 0.95rem;
}

.dashboard-page {
  min-height: 100%;
  padding: clamp(22px, 3vw, 42px);
  color: #ffffff;
}

.dashboard-kicker {
  color: #2dff68;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.logout-btn {
  border-radius: 12px;
  color: #49a7ff;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.summary-card,
.permissions-panel {
  border: 1px solid rgba(120, 156, 190, 0.32);
  border-radius: 18px;
  background:
    linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.018)),
    rgba(7, 18, 29, 0.72);
  backdrop-filter: blur(20px) saturate(1.25);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    0 14px 38px rgba(0, 0, 0, 0.18);
}

.summary-card {
  display: flex;
  min-height: 86px;
  align-items: center;
  gap: 14px;
  padding: 16px;
}

.summary-card .q-icon {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid rgba(45, 255, 104, 0.24);
  border-radius: 50%;
  background: rgba(45, 255, 104, 0.08);
  color: #2dff68;
  font-size: 24px;
}

.summary-card span {
  display: block;
  color: rgba(219, 231, 243, 0.62);
  font-size: 0.8rem;
}

.summary-card strong {
  display: block;
  margin-top: 3px;
  color: #ffffff;
  font-size: 1.25rem;
  line-height: 1.1;
  overflow-wrap: anywhere;
}

.permissions-panel {
  overflow: hidden;
  padding: 18px;
}

.panel-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.panel-heading h2 {
  margin: 0;
  color: #ffffff;
  font-size: 1.2rem;
}

.panel-heading p {
  margin: 4px 0 0;
  color: rgba(219, 231, 243, 0.62);
  font-size: 0.86rem;
}

.dashboard-table {
  background: transparent;
  color: #e9f4ff;
}

.dashboard-table :deep(.q-table__title),
.dashboard-table :deep(th) {
  color: rgba(232, 241, 250, 0.72);
  font-size: 0.76rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.dashboard-table :deep(td) {
  color: rgba(232, 241, 250, 0.82);
  border-color: rgba(215, 235, 255, 0.07);
  font-weight: 650;
}

.dashboard-table :deep(th) {
  border-color: rgba(215, 235, 255, 0.08);
}

.permission-pill {
  display: inline-flex;
  min-width: 62px;
  min-height: 26px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid rgba(215, 235, 255, 0.12);
  color: rgba(219, 231, 243, 0.5);
  font-size: 0.72rem;
  font-weight: 800;
}

.permission-pill--on {
  border-color: rgba(45, 255, 104, 0.28);
  background: rgba(45, 255, 104, 0.09);
  color: #2dff68;
}

.permission-pill--off {
  background: rgba(255, 255, 255, 0.025);
}

@media (max-width: 900px) {
  .dashboard-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>

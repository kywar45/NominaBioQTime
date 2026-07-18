<template>
  <q-layout view="lHh Lpr lFf" class="nomina-layout">
    <q-header class="nomina-header">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title> NominaBioQTime </q-toolbar-title>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered class="nomina-drawer">
      <q-list>
        <q-item-label header> Modulos </q-item-label>

        <q-item v-for="item in menuItems" :key="item.to" clickable :to="item.to" exact>
          <q-item-section avatar>
            <q-icon :name="item.icon" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ item.label }}</q-item-label>
            <q-item-label caption>{{ item.caption }}</q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'

const leftDrawerOpen = ref(false)
const menuItems = [
  { to: '/dashboard', icon: 'dashboard', label: 'Inicio', caption: 'Resumen del sistema' },
  { to: '/empleados', icon: 'groups', label: 'Empleados', caption: 'Personal y datos laborales' },
  { to: '/turnos', icon: 'schedule', label: 'Turnos', caption: 'Horarios y jornadas' },
  { to: '/departamentos', icon: 'apartment', label: 'Departamentos', caption: 'Areas de trabajo' },
  { to: '/reglas', icon: 'rule', label: 'Reglas', caption: 'Politicas de nomina' },
]

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value
}
</script>

const routes = [
  {
    path: '/login',
    component: () => import('pages/LoginPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('pages/DashboardPage.vue') },
      { path: 'empleados', component: () => import('pages/EmployeesPage.vue') },
      { path: 'turnos', component: () => import('pages/ShiftsPage.vue') },
      { path: 'departamentos', component: () => import('pages/DepartmentsPage.vue') },
      { path: 'reglas', component: () => import('pages/RulesPage.vue') },
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
]

export default routes

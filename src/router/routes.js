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
      { path: 'vacaciones', component: () => import('pages/VacationsPage.vue') },
      { path: 'dias-festivos', component: () => import('pages/HolidaysPage.vue') },
      { path: 'prestamos', component: () => import('pages/LoansPage.vue') },
      { path: 'imss', component: () => import('pages/ImssPage.vue') },
      { path: 'nomina', component: () => import('pages/PayrollPage.vue') },
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

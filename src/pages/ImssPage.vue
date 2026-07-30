<template>
  <q-page class="module-page">
    <header class="module-header">
      <div>
        <span class="module-kicker">Seguridad social</span>
        <h1>IMSS</h1>
        <p>Configura IMSS y cuenta de deposito por empleado.</p>
      </div>
      <div class="module-header__actions">
        <q-btn
          class="module-action"
          icon="account_balance"
          label="Registrar banco"
          outline
          @click="openBankDialog"
        />
        <q-btn
          class="module-action"
          icon="domain_add"
          label="Registrar empresa"
          outline
          @click="openCompanyDialog"
        />
      </div>
    </header>

    <section class="module-stats">
      <article class="module-stat">
        <q-icon name="badge" />
        <div>
          <span>Empleados</span>
          <strong>{{ imssRows.length }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="health_and_safety" />
        <div>
          <span>Con IMSS</span>
          <strong>{{ withImssCount }}</strong>
        </div>
      </article>
      <article class="module-stat">
        <q-icon name="assignment_late" />
        <div>
          <span>Sin configurar</span>
          <strong>{{ pendingCount }}</strong>
        </div>
      </article>
    </section>

    <section class="module-panel">
      <div class="module-panel__heading">
        <div>
          <h2>Configuracion IMSS</h2>
          <p>Solo aparecen empleados con configuracion laboral activa.</p>
        </div>
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

      <q-banner v-if="errorMessage" rounded class="module-error">
        {{ errorMessage }}
      </q-banner>

      <q-table
        :rows="imssRows"
        :columns="columns"
        :filter="filter"
        :pagination="pagination"
        :loading="loading"
        row-key="empleado_id"
        flat
        hide-bottom
        class="module-table module-table--scroll"
      >
        <template #body-cell-posee_imss="props">
          <q-td :props="props">
            <span
              :class="[
                'status-pill',
                Number(props.row.posee_imss) === 1 ? 'status-pill--on' : 'status-pill--off',
              ]"
            >
              {{ Number(props.row.posee_imss) === 1 ? 'Si' : 'No' }}
            </span>
          </q-td>
        </template>

        <template #body-cell-configurado="props">
          <q-td :props="props">
            <span
              :class="[
                'status-pill',
                Number(props.row.configurado) === 1 ? 'status-pill--night' : 'status-pill--danger',
              ]"
            >
              {{ Number(props.row.configurado) === 1 ? 'Listo' : 'Pendiente' }}
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
                aria-label="Editar IMSS"
                @click="openEdit(props.row)"
              />
            </div>
          </q-td>
        </template>

        <template #no-data>
          <div class="module-empty">
            <q-icon name="health_and_safety" />
            <span>No hay empleados para configurar.</span>
          </div>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">Datos IMSS</span>
            <h2>{{ editingRow?.empleado || 'Empleado' }}</h2>
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

        <q-form @submit.prevent="saveConfig">
          <q-card-section class="imss-form">
            <div class="shift-form__toggles">
              <q-toggle
                v-model="form.posee_imss"
                label="Posee IMSS"
                color="positive"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <q-input
                v-model="form.fecha_alta"
                outlined
                type="date"
                label="Fecha de alta"
                :disable="saving || !form.posee_imss"
                :rules="[
                  (value) => !form.posee_imss || Boolean(value) || 'Captura la fecha de alta',
                ]"
              />
              <q-input
                v-model.trim="form.numero_seguro_social"
                outlined
                label="Numero de seguro social"
                :disable="saving || !form.posee_imss"
                :rules="[(value) => !form.posee_imss || Boolean(value) || 'Captura el NSS']"
              />
            </div>

            <q-input
              v-model.trim="form.cuenta_deposito"
              outlined
              label="Numero de cuenta para deposito"
              :disable="saving"
            />

            <div class="shift-form__grid">
              <q-select
                v-model="form.banco_id"
                outlined
                emit-value
                map-options
                clearable
                label="Banco"
                :options="bankOptions"
                :disable="saving"
              />
              <q-select
                v-model="form.empresa_id"
                outlined
                emit-value
                map-options
                clearable
                label="Empresa"
                :options="companyOptions"
                :disable="saving"
              />
            </div>

            <div class="shift-form__grid">
              <div class="employee-form__readonly">
                <div>
                  <span>Tipo de sueldo</span>
                  <strong>{{ formatSalaryType(editingRow?.tipo_sueldo_empleado) }}</strong>
                </div>
              </div>
            </div>

            <q-input
              v-model.trim="form.notas"
              outlined
              type="textarea"
              label="Notas"
              autogrow
              :disable="saving"
            />

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

    <q-dialog v-model="bankDialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">Catalogo</span>
            <h2>{{ editingBank ? 'Editar banco' : 'Bancos' }}</h2>
          </div>
          <q-btn
            flat
            round
            dense
            icon="close"
            aria-label="Cerrar"
            :disable="savingBank"
            v-close-popup
          />
        </q-card-section>

        <q-form ref="bankCatalogForm" @submit.prevent="saveBank">
          <q-card-section class="imss-form">
            <q-input
              v-model.trim="bankForm.nombre"
              outlined
              :label="editingBank ? 'Editar nombre del banco' : 'Nombre del banco'"
              :disable="savingBank"
              :rules="[(value) => Boolean(value) || 'Captura el nombre del banco']"
            />

            <div class="table-actions">
              <q-btn
                v-if="editingBank"
                flat
                type="button"
                icon="close"
                label="Cancelar edicion"
                :disable="savingBank"
                @click="resetBankForm"
              />
            </div>

            <q-table
              :rows="banks"
              :columns="bankColumns"
              :pagination="catalogPagination"
              row-key="id_banco"
              flat
              hide-bottom
              class="module-table module-table--scroll"
            >
              <template #body-cell-activo="props">
                <q-td :props="props">
                  <span
                    :class="[
                      'status-pill',
                      Number(props.row.activo) === 1 ? 'status-pill--on' : 'status-pill--off',
                    ]"
                  >
                    {{ Number(props.row.activo) === 1 ? 'Activo' : 'Inactivo' }}
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
                      aria-label="Editar banco"
                      :disable="savingBank"
                      @click="openBankEdit(props.row)"
                    />
                    <q-btn
                      flat
                      round
                      dense
                      icon="block"
                      aria-label="Desactivar banco"
                      :disable="savingBank || Number(props.row.activo) !== 1"
                      @click="deactivateBankRow(props.row)"
                    />
                  </div>
                </q-td>
              </template>

              <template #no-data>
                <div class="module-empty employee-tabs__empty">
                  <q-icon name="account_balance" />
                  <span>No hay bancos registrados.</span>
                </div>
              </template>
            </q-table>

            <q-banner v-if="bankError" rounded class="module-error">
              {{ bankError }}
            </q-banner>
          </q-card-section>

          <q-card-actions align="right" class="module-dialog__actions">
            <q-btn flat label="Cancelar" :disable="savingBank" v-close-popup />
            <q-btn unelevated icon="save" label="Guardar" type="submit" :loading="savingBank" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="companyDialogOpen" persistent>
      <q-card class="module-dialog">
        <q-card-section class="module-dialog__head">
          <div>
            <span class="module-kicker">Catalogo</span>
            <h2>{{ editingCompany ? 'Editar empresa' : 'Empresas' }}</h2>
          </div>
          <q-btn
            flat
            round
            dense
            icon="close"
            aria-label="Cerrar"
            :disable="savingCompany"
            v-close-popup
          />
        </q-card-section>

        <q-form ref="companyCatalogForm" @submit.prevent="saveCompany">
          <q-card-section class="imss-form">
            <q-input
              v-model.trim="companyForm.nombre"
              outlined
              :label="editingCompany ? 'Editar nombre de la empresa' : 'Nombre de la empresa'"
              :disable="savingCompany"
              :rules="[(value) => Boolean(value) || 'Captura el nombre de la empresa']"
            />

            <div class="table-actions">
              <q-btn
                v-if="editingCompany"
                flat
                type="button"
                icon="close"
                label="Cancelar edicion"
                :disable="savingCompany"
                @click="resetCompanyForm"
              />
            </div>

            <q-table
              :rows="companies"
              :columns="companyColumns"
              :pagination="catalogPagination"
              row-key="id_empresa"
              flat
              hide-bottom
              class="module-table module-table--scroll"
            >
              <template #body-cell-activo="props">
                <q-td :props="props">
                  <span
                    :class="[
                      'status-pill',
                      Number(props.row.activo) === 1 ? 'status-pill--on' : 'status-pill--off',
                    ]"
                  >
                    {{ Number(props.row.activo) === 1 ? 'Activa' : 'Inactiva' }}
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
                      aria-label="Editar empresa"
                      :disable="savingCompany"
                      @click="openCompanyEdit(props.row)"
                    />
                    <q-btn
                      flat
                      round
                      dense
                      icon="block"
                      aria-label="Desactivar empresa"
                      :disable="savingCompany || Number(props.row.activo) !== 1"
                      @click="deactivateCompanyRow(props.row)"
                    />
                  </div>
                </q-td>
              </template>

              <template #no-data>
                <div class="module-empty employee-tabs__empty">
                  <q-icon name="domain" />
                  <span>No hay empresas registradas.</span>
                </div>
              </template>
            </q-table>

            <q-banner v-if="companyError" rounded class="module-error">
              {{ companyError }}
            </q-banner>
          </q-card-section>

          <q-card-actions align="right" class="module-dialog__actions">
            <q-btn flat label="Cancelar" :disable="savingCompany" v-close-popup />
            <q-btn unelevated icon="save" label="Guardar" type="submit" :loading="savingCompany" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import {
  createBank,
  createCompany,
  deactivateBank,
  deactivateCompany,
  listBanks,
  listCompanies,
  listImssConfigs,
  updateBank,
  updateCompany,
  updateImssConfig,
} from 'src/services/apiClient'

const imssRows = ref([])
const banks = ref([])
const companies = ref([])
const filter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const dialogOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editingRow = ref(null)
const form = ref(defaultForm())
const bankDialogOpen = ref(false)
const savingBank = ref(false)
const bankError = ref('')
const editingBank = ref(null)
const bankCatalogForm = ref(null)
const bankForm = ref({ nombre: '' })
const companyDialogOpen = ref(false)
const savingCompany = ref(false)
const companyError = ref('')
const editingCompany = ref(null)
const companyCatalogForm = ref(null)
const companyForm = ref({ nombre: '' })
const pagination = {
  rowsPerPage: 0,
}
const catalogPagination = {
  rowsPerPage: 0,
}
const bankOptions = computed(() =>
  banks.value
    .filter((bank) => Number(bank.activo) === 1)
    .map((bank) => ({
      label: bank.nombre,
      value: Number(bank.id_banco),
    })),
)
const companyOptions = computed(() =>
  companies.value
    .filter((company) => Number(company.activo) === 1)
    .map((company) => ({
      label: company.nombre,
      value: Number(company.id_empresa),
    })),
)
const withImssCount = computed(
  () => imssRows.value.filter((row) => Number(row.posee_imss) === 1).length,
)
const pendingCount = computed(
  () => imssRows.value.filter((row) => Number(row.configurado) !== 1).length,
)
const columns = [
  {
    name: 'empleado',
    label: 'Empleado',
    field: 'empleado',
    align: 'left',
    sortable: true,
  },
  {
    name: 'posee_imss',
    label: 'IMSS',
    field: 'posee_imss',
    align: 'left',
    sortable: true,
  },
  {
    name: 'fecha_alta',
    label: 'Fecha alta',
    field: 'fecha_alta',
    align: 'left',
    sortable: true,
    format: (value) => formatDate(value),
  },
  {
    name: 'numero_seguro_social',
    label: 'NSS',
    field: 'numero_seguro_social',
    align: 'left',
    format: (value) => value || 'Sin NSS',
  },
  {
    name: 'banco',
    label: 'Banco',
    field: 'banco',
    align: 'left',
    sortable: true,
    format: (value) => value || 'Sin banco',
  },
  {
    name: 'cuenta_deposito',
    label: 'Cuenta deposito',
    field: 'cuenta_deposito',
    align: 'left',
    format: (value) => value || 'Sin cuenta',
  },
  {
    name: 'empresa',
    label: 'Empresa',
    field: 'empresa',
    align: 'left',
    sortable: true,
    format: (value) => value || 'Sin empresa',
  },
  {
    name: 'tipo_sueldo',
    label: 'Tipo sueldo',
    field: 'tipo_sueldo',
    align: 'left',
    sortable: true,
    format: (value) => formatSalaryType(value),
  },
  {
    name: 'configurado',
    label: 'Estatus',
    field: 'configurado',
    align: 'left',
    sortable: true,
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]
const bankColumns = [
  {
    name: 'nombre',
    label: 'Banco',
    field: 'nombre',
    align: 'left',
    sortable: true,
  },
  {
    name: 'activo',
    label: 'Estatus',
    field: 'activo',
    align: 'left',
    sortable: true,
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]
const companyColumns = [
  {
    name: 'nombre',
    label: 'Empresa',
    field: 'nombre',
    align: 'left',
    sortable: true,
  },
  {
    name: 'activo',
    label: 'Estatus',
    field: 'activo',
    align: 'left',
    sortable: true,
  },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(loadImss)

async function loadImss() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [imssPayload, banksPayload, companiesPayload] = await Promise.all([
      listImssConfigs(),
      listBanks(),
      listCompanies(),
    ])
    imssRows.value = imssPayload.imss || []
    banks.value = banksPayload.banks || []
    companies.value = companiesPayload.companies || []
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editingRow.value = row
  formError.value = ''
  form.value = {
    posee_imss: Number(row.posee_imss) === 1,
    fecha_alta: row.fecha_alta || '',
    numero_seguro_social: row.numero_seguro_social || '',
    banco_id: row.banco_id ? Number(row.banco_id) : null,
    cuenta_deposito: row.cuenta_deposito || '',
    empresa_id: row.empresa_id ? Number(row.empresa_id) : null,
    tipo_sueldo: row.tipo_sueldo_empleado || '',
    notas: row.notas || '',
  }
  dialogOpen.value = true
}

async function saveConfig() {
  if (!editingRow.value) {
    return
  }

  saving.value = true
  formError.value = ''

  try {
    await updateImssConfig(editingRow.value.empleado_id, {
      ...form.value,
      posee_imss: form.value.posee_imss ? 1 : 0,
    })
    dialogOpen.value = false
    await loadImss()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

function openBankDialog() {
  bankError.value = ''
  resetBankForm()
  bankDialogOpen.value = true
}

async function saveBank() {
  savingBank.value = true
  bankError.value = ''

  try {
    if (editingBank.value) {
      await updateBank(editingBank.value.id_banco, bankForm.value)
    } else {
      await createBank(bankForm.value)
    }

    const payload = await listBanks()
    banks.value = payload.banks || []
    resetBankForm()
  } catch (error) {
    bankError.value = error.message
  } finally {
    savingBank.value = false
  }
}

function openBankEdit(bank) {
  bankError.value = ''
  editingBank.value = bank
  bankForm.value = { nombre: bank.nombre || '' }
}

async function deactivateBankRow(bank) {
  if (!bank) {
    return
  }

  savingBank.value = true
  bankError.value = ''

  try {
    await deactivateBank(bank.id_banco)
    const payload = await listBanks()
    banks.value = payload.banks || []

    if (editingBank.value?.id_banco === bank.id_banco) {
      resetBankForm()
    }
  } catch (error) {
    bankError.value = error.message
  } finally {
    savingBank.value = false
  }
}

function resetBankForm() {
  editingBank.value = null
  bankForm.value = { nombre: '' }
  nextTick(() => bankCatalogForm.value?.resetValidation())
}

function openCompanyDialog() {
  companyError.value = ''
  resetCompanyForm()
  companyDialogOpen.value = true
}

async function saveCompany() {
  savingCompany.value = true
  companyError.value = ''

  try {
    if (editingCompany.value) {
      await updateCompany(editingCompany.value.id_empresa, companyForm.value)
    } else {
      await createCompany(companyForm.value)
    }

    const payload = await listCompanies()
    companies.value = payload.companies || []
    resetCompanyForm()
  } catch (error) {
    companyError.value = error.message
  } finally {
    savingCompany.value = false
  }
}

function openCompanyEdit(company) {
  companyError.value = ''
  editingCompany.value = company
  companyForm.value = { nombre: company.nombre || '' }
}

async function deactivateCompanyRow(company) {
  if (!company) {
    return
  }

  savingCompany.value = true
  companyError.value = ''

  try {
    await deactivateCompany(company.id_empresa)
    const payload = await listCompanies()
    companies.value = payload.companies || []

    if (editingCompany.value?.id_empresa === company.id_empresa) {
      resetCompanyForm()
    }
  } catch (error) {
    companyError.value = error.message
  } finally {
    savingCompany.value = false
  }
}

function resetCompanyForm() {
  editingCompany.value = null
  companyForm.value = { nombre: '' }
  nextTick(() => companyCatalogForm.value?.resetValidation())
}

function defaultForm() {
  return {
    posee_imss: false,
    fecha_alta: '',
    numero_seguro_social: '',
    banco_id: null,
    cuenta_deposito: '',
    empresa_id: null,
    tipo_sueldo: '',
    notas: '',
  }
}

function formatDate(value) {
  if (!value) {
    return 'Sin fecha'
  }

  const [year, month, day] = String(value).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day}/${month}/${year}`
}

function formatSalaryType(value) {
  return (
    {
      diario: 'Diario',
      semanal: 'Semanal',
      quincenal: 'Quincenal',
      mensual: 'Mensual',
    }[value] || 'Sin tipo'
  )
}
</script>

<template>
  <main class="login-page">
    <div class="login-watermark" aria-hidden="true">
      <img src="img/BioQTime.png" alt="" />
    </div>

    <section class="login-panel">
      <div class="login-brand" @click="registerHiddenClick">
        <div class="login-brand__logo">
          <img src="img/BioQTime.png" alt="BioQTime" />
        </div>
        <div>
          <p>Sistema de nomina y checador</p>
        </div>
      </div>

      <q-form class="login-form" @submit.prevent="submitLogin">
        <q-input
          v-model.trim="form.username"
          class="login-field"
          label="Usuario"
          autocomplete="username"
          outlined
          autofocus
          :disable="loading"
        />

        <q-input
          v-model="form.password"
          class="login-field"
          label="Contrasena"
          type="password"
          autocomplete="current-password"
          outlined
          :disable="loading"
        />

        <q-banner v-if="errorMessage" rounded class="login-error">
          {{ errorMessage }}
        </q-banner>

        <q-btn
          class="login-submit full-width"
          label="Iniciar sesion"
          type="submit"
          unelevated
          size="lg"
          :loading="loading"
        />
      </q-form>
    </section>

    <q-btn
      class="hidden-settings"
      flat
      round
      dense
      icon="settings"
      aria-label="Configurar API"
      @click="settingsOpen = true"
    />

    <q-dialog v-model="settingsOpen">
      <q-card class="settings-card">
        <q-card-section>
          <div class="text-h6">Ruta de API</div>
          <div class="text-body2 text-grey-7">
            Esta ruta se guarda en el servidor y no requiere recompilar.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-md">
          <q-input v-model.trim="apiUrl" label="URL base" outlined :disable="savingSettings" />
          <q-banner rounded class="bg-grey-2 text-grey-8"> Actual: {{ currentApiUrl }} </q-banner>
          <q-banner v-if="settingsMessage" rounded :class="settingsMessageClass">
            {{ settingsMessage }}
          </q-banner>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn
            flat
            label="Restablecer"
            color="grey-8"
            :disable="savingSettings"
            @click="resetApi"
          />
          <q-btn flat label="Cancelar" color="grey-8" :disable="savingSettings" v-close-popup />
          <q-btn
            unelevated
            label="Guardar"
            color="primary"
            :loading="savingSettings"
            @click="saveApi"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getApiBaseUrl,
  loadApiBaseUrl,
  resetApiBaseUrl,
  saveApiBaseUrl,
} from 'src/services/apiConfig'
import { login } from 'src/services/apiClient'

const router = useRouter()
const loading = ref(false)
const errorMessage = ref('')
const settingsOpen = ref(false)
const savingSettings = ref(false)
const settingsMessage = ref('')
const settingsMessageType = ref('positive')
const hiddenClicks = ref(0)
const apiUrl = ref(getApiBaseUrl())
const currentApiUrl = ref(getApiBaseUrl())
const form = reactive({
  username: '',
  password: '',
})

const settingsMessageClass = computed(() =>
  settingsMessageType.value === 'negative' ? 'bg-red-1 text-negative' : 'bg-green-1 text-positive',
)

onMounted(async () => {
  currentApiUrl.value = await loadApiBaseUrl({ force: true })
  apiUrl.value = currentApiUrl.value
})

function registerHiddenClick() {
  hiddenClicks.value += 1

  if (hiddenClicks.value >= 5) {
    settingsOpen.value = true
    hiddenClicks.value = 0
  }
}

async function saveApi() {
  savingSettings.value = true
  settingsMessage.value = ''

  try {
    apiUrl.value = await saveApiBaseUrl(apiUrl.value)
    currentApiUrl.value = apiUrl.value
    settingsMessageType.value = 'positive'
    settingsMessage.value = 'Ruta guardada en el servidor.'
    settingsOpen.value = false
  } catch (error) {
    settingsMessageType.value = 'negative'
    settingsMessage.value = error.message
  } finally {
    savingSettings.value = false
  }
}

async function resetApi() {
  savingSettings.value = true
  settingsMessage.value = ''

  try {
    apiUrl.value = await resetApiBaseUrl()
    currentApiUrl.value = apiUrl.value
    settingsMessageType.value = 'positive'
    settingsMessage.value = 'Ruta restablecida.'
  } catch (error) {
    settingsMessageType.value = 'negative'
    settingsMessage.value = error.message
  } finally {
    savingSettings.value = false
  }
}

async function submitLogin() {
  errorMessage.value = ''

  if (!form.username || !form.password) {
    errorMessage.value = 'Captura usuario y contrasena.'
    return
  }

  loading.value = true

  try {
    const session = await login(form.username, form.password)
    localStorage.setItem('nomina_session', JSON.stringify(session))
    await router.push('/dashboard')
  } catch (error) {
    errorMessage.value = error.message
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  position: relative;
  display: grid;
  min-height: 100vh;
  place-items: center;
  overflow: hidden;
  min-height: 100vh;
  background:
    linear-gradient(90deg, rgba(3, 10, 17, 0.86), rgba(5, 17, 29, 0.48) 56%, rgba(5, 13, 22, 0.86)),
    radial-gradient(circle at 74% 35%, rgba(73, 167, 255, 0.18), transparent 24%),
    url('https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1800&q=80');
  background-position: center;
  background-size: cover;
  color: #ffffff;
  padding: clamp(16px, 3vw, 32px);
}

.login-page::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    linear-gradient(rgba(255, 255, 255, 0.022) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.022) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.8), transparent 78%);
}

.login-watermark {
  position: absolute;
  right: 7vw;
  bottom: 9vh;
  width: min(34vw, 520px);
  opacity: 0.08;
  filter: grayscale(1) contrast(1.1);
  pointer-events: none;
}

.login-watermark img {
  width: 100%;
  display: block;
}

.login-panel {
  position: relative;
  z-index: 1;
  width: min(100%, 440px);
  border: 1px solid rgba(235, 246, 255, 0.24);
  border-radius: 24px;
  background:
    linear-gradient(
      140deg,
      rgba(255, 255, 255, 0.2),
      rgba(255, 255, 255, 0.04) 34%,
      rgba(255, 255, 255, 0.015) 68%
    ),
    rgba(255, 255, 255, 0.055);
  backdrop-filter: blur(28px) saturate(1.75) brightness(1.08);
  padding: clamp(24px, 3vw, 34px);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.34),
    inset 0 -22px 42px rgba(3, 10, 18, 0.14),
    0 22px 60px rgba(0, 0, 0, 0.22);
}

.login-brand {
  display: grid;
  justify-items: center;
  gap: 10px;
  margin-bottom: 24px;
  text-align: center;
  cursor: default;
  user-select: none;
}

.login-brand__logo {
  width: min(240px, 76%);
  max-width: none;
  filter: drop-shadow(0 14px 28px rgba(0, 0, 0, 0.24));
}

.login-brand__logo img {
  display: block;
  width: 100%;
}

.login-brand h1 {
  margin: 0;
  color: #ffffff;
  font-size: clamp(1.75rem, 3vw, 2.1rem);
  font-weight: 800;
  line-height: 1.15;
}

.login-brand p {
  margin: 6px 0 0;
  color: rgba(219, 231, 243, 0.78);
  font-size: 0.92rem;
}

.login-form {
  display: grid;
  gap: 14px;
}

.login-field :deep(.q-field__control) {
  min-height: 54px;
  border-radius: 16px;
  background: rgba(4, 14, 24, 0.42);
  color: #ffffff;
}

.login-field :deep(.q-field__control::before) {
  border-color: rgba(235, 246, 255, 0.2);
}

.login-field :deep(.q-field__control::after) {
  border-color: rgba(184, 187, 185, 0.48);
}

.login-field :deep(.q-field__native),
.login-field :deep(.q-field__label) {
  color: rgba(232, 241, 250, 0.86);
}

.login-error {
  border: 1px solid rgba(255, 98, 98, 0.36);
  background: rgba(255, 98, 98, 0.11);
  color: #ffdede;
}

.login-submit {
  min-height: 54px;
  border-radius: 16px;
  border: 1px solid rgba(126, 218, 255, 0.28);
  background:
    linear-gradient(145deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.035) 42%),
    linear-gradient(135deg, #2f2f2f, #454545);
  color: #f3fbff;
  font-weight: 900;
  letter-spacing: 0;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.22),
    0 14px 32px rgba(10, 160, 127, 0.18);
}

.login-submit:hover {
  filter: brightness(1.06);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.28),
    0 18px 38px rgba(73, 167, 255, 0.2);
}

.hidden-settings {
  position: fixed;
  right: 14px;
  bottom: 14px;
  color: white;
  opacity: 0.2;
  transition: opacity 0.2s ease;
}

.hidden-settings:hover,
.hidden-settings:focus {
  opacity: 0.9;
}

.settings-card {
  width: min(92vw, 460px);
  border-radius: 14px;
}

@media (max-width: 620px) {
  .login-brand__logo {
    width: 74%;
  }
}
</style>

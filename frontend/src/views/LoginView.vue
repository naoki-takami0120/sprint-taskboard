<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref('')
const loading = ref(false)

function redirectTarget() {
  const redirect = route.query.redirect

  const isSafe =
    typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')

  return isSafe ? redirect : { name: 'projects' }
}

async function submit() {
  loading.value = true
  errors.value = {}
  message.value = ''

  try {
    await auth.login(form.email, form.password)
    await router.push(redirectTarget())
  } catch (error) {
    if (error.status === 422) {
      errors.value = error.body.errors
    } else {
      message.value = error.message
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <h1>Sign in</h1>

    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="form.email" type="email" autocomplete="username" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Password
        <input v-model="form.password" type="password" autocomplete="current-password" required />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <p v-if="message" class="error" role="alert">{{ message }}</p>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Signing in...' : 'Sign in' }}
      </button>
    </form>
  </main>
</template>

<style scoped>
.login {
  max-width: 360px;
  margin: 4rem auto;
}
label {
  display: block;
  margin-top: 1rem;
}
input {
  display: block;
  width: 100%;
  padding: 0.5rem;
}
button {
  margin-top: 1.5rem;
  padding: 0.5rem 1rem;
}
.error {
  color: #c0392b;
  margin: 0.25rem 0 0;
}
</style>

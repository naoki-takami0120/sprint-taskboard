<script setup>
import { RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  try {
    await auth.logout()
  } catch {
    // The token is already cleared in the store, so a failed server call can be ignored.
  }
  router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="auth.isLoggedIn" class="header">
    <strong>Sprint Taskboard</strong>
    <span v-if="auth.user">{{ auth.user.name }}</span>
    <button type="button" @click="logout">Sign out</button>
  </header>

  <RouterView />
</template>

<style scoped>
.header {
  display: flex;
  gap: 1rem;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #ddd;
}
.header button {
  margin-left: auto;
}
</style>

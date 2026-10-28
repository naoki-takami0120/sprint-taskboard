import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { request } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const user = ref(null)
  const isLoggedIn = computed(() => token.value !== null)

  async function login(email, password) {
    const data = await request('/login', { method: 'POST', body: { email, password } })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('token', data.token)
  }

  async function logout() {
    try {
      await request('/logout', { method: 'POST', token: token.value })
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
    }
  }

  async function fetchUser() {
    const data = await request('/user', { token: token.value })
    user.value = data.user
  }

  return { token, user, isLoggedIn, login, logout, fetchUser }
})
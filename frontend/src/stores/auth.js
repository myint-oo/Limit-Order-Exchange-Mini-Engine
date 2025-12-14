import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!user.value)

  function setUser(userData) {
    user.value = userData
  }

  function clearAuth() {
    user.value = null
  }

  async function register(name, email, password, passwordConfirmation) {
    loading.value = true
    error.value = null

    try {
      await api.getCsrfCookie()
      const data = await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })

      setUser(data.user)
      return { success: true }
    } catch (err) {
      error.value = err.message
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  async function login(email, password) {
    loading.value = true
    error.value = null

    try {
      await api.getCsrfCookie()
      const user = await api.post('/login', { email, password })

      setUser(user)
      return { success: true }
    } catch (err) {
      error.value = err.message
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true

    try {
      await api.post('/logout', {})
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      clearAuth()
      loading.value = false
    }
  }

  async function fetchUser() {
    loading.value = true

    try {
      user.value = await api.get('/user')

      return true
    } catch (err) {
      console.error('Fetch user error:', err)
      clearAuth()
      return false
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(name, email) {
    loading.value = true
    error.value = null

    try {
      const data = await api.put('/user/profile', { name, email })
      setUser(data.user)
      return { success: true }
    } catch (err) {
      error.value = err.message
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    register,
    login,
    logout,
    fetchUser,
    updateProfile,
  }
})

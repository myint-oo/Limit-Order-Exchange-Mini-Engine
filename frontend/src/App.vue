<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-layout">
    <header v-if="authStore.isAuthenticated" class="main-header">
      <div class="header-content">
        <div class="logo-section">
          <span class="logo-text">📈 Order Exchange</span>
        </div>

        <nav class="main-nav">
          <RouterLink to="/">Dashboard</RouterLink>
          <RouterLink to="/profile">Profile</RouterLink>
        </nav>

        <div class="user-section">
          <span class="user-name">{{ authStore.user?.name }}</span>
          <button @click="handleLogout" class="btn-logout">Logout</button>
        </div>
      </div>
    </header>

    <main class="main-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.app-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #1a1a2e;
}

.main-header {
  background: #0f0f23;
  border-bottom: 1px solid #2a2a4a;
  padding: 0 2rem;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
}

.logo-section {
  display: flex;
  align-items: center;
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 700;
  color: #ffffff;
}

.main-nav {
  display: flex;
  gap: 0.5rem;
}

.main-nav a {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  color: #8888aa;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s, background-color 0.2s;
}

.main-nav a:hover {
  color: #ffffff;
  background-color: #2a2a4a;
}

.main-nav a.router-link-exact-active {
  color: rgb(37, 99, 235);
  background-color: rgba(37, 99, 235, 0.1);
}

.user-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-name {
  color: #ccccdd;
  font-size: 0.875rem;
}

.btn-logout {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: 1px solid #3a3a5a;
  background: transparent;
  color: #8888aa;
  font-size: 0.875rem;
  cursor: pointer;
  transition: border-color 0.2s, color 0.2s;
}

.btn-logout:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.main-content {
  flex: 1;
}
</style>

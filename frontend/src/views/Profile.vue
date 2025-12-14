<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { formatDate, formatNumber } from '@/services/helper'

const authStore = useAuthStore()

// Profile form
const name = ref('')
const email = ref('')
const balance = ref('')
const profileMessage = ref('')
const profileError = ref('')

onMounted(() => {
  if (authStore.user) {
    name.value = authStore.user.name
    email.value = authStore.user.email
    balance.value = authStore.user.balance
  }
  loadAssets()
  loadTrades()
})

async function updateProfile() {
  profileMessage.value = ''
  profileError.value = ''

  const result = await authStore.updateProfile(name.value, email.value)
  if (result.success) {
    profileMessage.value = 'Profile updated successfully'
  } else {
    profileError.value = result.error
  }
}

const assets = ref([])
const assetsLoading = ref(false)
async function loadAssets() {
  assetsLoading.value = true
  try {
    const response = await api.get('/assets')
    assets.value = response || []
  } catch (err) {
    console.error('Failed to load assets:', err)
  } finally {
    assetsLoading.value = false
  }
}

const trades = ref([])
const tradesLoading = ref(false)
async function loadTrades() {
  tradesLoading.value = true
  try {
    const response = await api.get('/trades')
    trades.value = response || []
  } catch (err) {
    console.error('Failed to load trades:', err)
  } finally {
    tradesLoading.value = false
  }
}
</script>

<template>
  <div class="profile-container">
    <div class="profile-grid">
      <div class="card">
        <h2>Update Profile</h2>
        
        <div v-if="profileMessage" class="success-message">{{ profileMessage }}</div>
        <div v-if="profileError" class="error-message">{{ profileError }}</div>

        <form @submit.prevent="updateProfile" class="form">
          <div class="form-group">
            <label for="name">Name</label>
            <input id="name" v-model="name" type="text" required />
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input id="email" v-model="email" type="email" required />
          </div>

          <button type="submit" class="btn-primary" :disabled="authStore.loading">
            {{ authStore.loading ? 'Saving...' : 'Save Changes' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

h1 {
  color: #ffffff;
  margin-bottom: 2rem;
}

.profile-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.card {
  background: #0f0f23;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #2a2a4a;
}

.card h2 {
  color: #ffffff;
  font-size: 1.25rem;
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #2a2a4a;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  color: #ccccdd;
  font-size: 0.875rem;
  font-weight: 500;
}

.form-group input {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  border: 1px solid #3a3a5a;
  background: #1a1a3a;
  color: #ffffff;
  font-size: 1rem;
}

.form-group input:focus {
  outline: none;
  border-color: rgb(37, 99, 235);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.btn-primary {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, rgb(37, 99, 235) 0%, rgb(29, 78, 216) 100%);
  color: #ffffff;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: 0.5rem;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.success-message {
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #4ade80;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.error-message {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  border-bottom: 1px solid #2a2a4a;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row .label {
  color: #8888aa;
}

.info-row .value {
  color: #ffffff;
  font-weight: 500;
}

.trades-section {
  margin-top: 1.5rem;
}

.trades-table {
  width: 100%;
  border-collapse: collapse;
}

.trades-table th,
.trades-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #2a2a4a;
}

.trades-table th {
  color: #8888aa;
  font-weight: 500;
  font-size: 0.875rem;
}

.trades-table td {
  color: #ffffff;
}

.trades-table .buy {
  color: #4ade80;
}

.trades-table .sell {
  color: #f87171;
}

.loading,
.empty-state {
  text-align: center;
  color: #8888aa;
  padding: 2rem;
}

/* Asset Cards */
.assets-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.balance-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, #1a1a3a 0%, #0f0f23 100%);
}

.balance-icon {
  font-size: 2.5rem;
}

.balance-info {
  display: flex;
  flex-direction: column;
}

.balance-label {
  color: #8888aa;
  font-size: 0.875rem;
}

.balance-value {
  color: #4ade80;
  font-size: 1.75rem;
  font-weight: 700;
}

.asset-grid {
  display: flex;
  gap: 1rem;
}

.asset-card {
  background: linear-gradient(135deg, #1a1a3a 0%, #151530 100%);
  border-radius: 10px;
  padding: 1rem;
  border: 1px solid #2a2a4a;
}

.asset-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.asset-symbol {
  font-size: .8rem;
  font-weight: 600;
  color: rgb(37, 99, 235);
  background: rgba(37, 99, 235, 0.1);
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
}

.asset-total {
  font-size: 1.2rem;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 0.75rem;
}

.asset-details {
  display: flex;
  gap: 1.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid #2a2a4a;
}

.asset-detail {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.75rem;
  color: #8888aa;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.detail-value {
  font-size: 0.95rem;
  font-weight: 500;
}

.detail-value.available {
  color: #4ade80;
}

.detail-value.locked {
  color: #fbbf24;
}
</style>

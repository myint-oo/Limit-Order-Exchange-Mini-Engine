<script setup>
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

function getIcon(type) {
  switch (type) {
    case 'success': return '✓'
    case 'error': return '✕'
    case 'warning': return '⚠'
    case 'info': 
    default: return 'ℹ'
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          :class="['toast', `toast-${toast.type}`]"
        >
          <span class="toast-icon">{{ getIcon(toast.type) }}</span>
          <span class="toast-message">{{ toast.message }}</span>
          <button class="toast-close" @click="toastStore.removeToast(toast.id)">×</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 400px;
}

.toast {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-radius: 8px;
  background: #1a1a3a;
  border: 1px solid #3a3a5a;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  color: #ffffff;
  font-size: 0.9rem;
}

.toast-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
}

.toast-message {
  flex: 1;
}

.toast-close {
  background: none;
  border: none;
  color: #8888aa;
  font-size: 1.25rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  transition: color 0.2s;
}

.toast-close:hover {
  color: #ffffff;
}

/* Types */
.toast-success {
  border-color: #22c55e;
  background: rgba(34, 197, 94, 0.15);
}

.toast-success .toast-icon {
  color: #4ade80;
}

.toast-error {
  border-color: #ef4444;
  background: rgba(239, 68, 68, 0.15);
}

.toast-error .toast-icon {
  color: #f87171;
}

.toast-warning {
  border-color: #f59e0b;
  background: rgba(245, 158, 11, 0.15);
}

.toast-warning .toast-icon {
  color: #fbbf24;
}

.toast-info {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.15);
}

.toast-info .toast-icon {
  color: #60a5fa;
}

/* Animations */
.toast-enter-active {
  animation: toast-in 0.3s ease-out;
}

.toast-leave-active {
  animation: toast-out 0.3s ease-in;
}

@keyframes toast-in {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes toast-out {
  from {
    opacity: 1;
    transform: translateX(0);
  }
  to {
    opacity: 0;
    transform: translateX(100%);
  }
}
</style>

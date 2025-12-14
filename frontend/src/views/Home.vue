<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTradingStore } from '@/stores/trading'
import { subscribeToUserChannel, subscribeToOrderBook, unsubscribeFromOrderBook, disconnectPusher } from '@/services/pusher'
import { formatNumber, formatDate } from '@/services/helper'

const authStore = useAuthStore()
const tradingStore = useTradingStore()

// Order form
const symbol = ref('BTC')
const side = ref('buy')
const price = ref('')
const amount = ref('')
const orderLoading = ref(false)
const orderMessage = ref('')
const orderError = ref('')

const symbols = ['BTC', 'ETH']

// Order history tab
const orderTab = ref('open') // 'open', 'filled', 'cancelled', 'all'

const total = computed(() => {
  if (price.value && amount.value) {
    return (parseFloat(price.value) * parseFloat(amount.value)).toFixed(8)
  }
  return '0.00000000'
})

// Commission (1.5% for seller)
const commissionPreview = computed(() => {
  if (side.value === 'sell' && price.value && amount.value) {
    const totalUsd = parseFloat(price.value) * parseFloat(amount.value)
    return (totalUsd * 0.015).toFixed(8)
  }
  return null
})

// Net proceeds for seller after commission
const netProceeds = computed(() => {
  if (side.value === 'sell' && total.value && commissionPreview.value) {
    return (parseFloat(total.value) - parseFloat(commissionPreview.value)).toFixed(8)
  }
  return null
})

// Filtered orders based on selected tab
const filteredOrders = computed(() => {
  if (orderTab.value === 'open') {
    return tradingStore.openOrders
  } else if (orderTab.value === 'filled') {
    return tradingStore.filledOrders
  } else if (orderTab.value === 'cancelled') {
    return tradingStore.cancelledOrders
  } else {
    return tradingStore.orders
  }
})

// Available USD balance (total - locked)
const availableUsdBalance = computed(() => {
  const balance = parseFloat(authStore.user?.balance || 0)
  const locked = parseFloat(authStore.user?.locked_balance || 0)
  return (balance - locked).toFixed(8)
})

onMounted(async () => {
  // Load initial data
  await tradingStore.refreshAll()

  // Subscribe to real-time updates
  if (authStore.user?.id) {
    subscribeToUserChannel(authStore.user.id, {
      onOrderMatched: handleOrderMatched,
    })
  }

  // Subscribe to order book updates for current symbol
  subscribeToOrderBook(symbol.value, {
    onOrderCreated: tradingStore.handleOrderCreated,
    onOrderCancelled: tradingStore.handleOrderCancelled,
    onTradeExecuted: tradingStore.handleTradeExecuted,
  })
})

onUnmounted(() => {
  unsubscribeFromOrderBook(symbol.value)
  disconnectPusher()
})

watch(symbol, (newSymbol, oldSymbol) => {
  // Unsubscribe from old symbol
  if (oldSymbol) {
    unsubscribeFromOrderBook(oldSymbol)
  }
  
  // Load order book and subscribe to new symbol
  tradingStore.loadOrderBook(newSymbol)
  subscribeToOrderBook(newSymbol, {
    onOrderCreated: tradingStore.handleOrderCreated,
    onOrderCancelled: tradingStore.handleOrderCancelled,
    onTradeExecuted: tradingStore.handleTradeExecuted,
  })
})

// Handle real-time order matched event
function handleOrderMatched(data) {
  const currentUserId = authStore.user?.id
  
  // Pass current user ID to filter orders correctly
  tradingStore.handleOrderMatched(data, currentUserId)
  
  // Update wallet based on whether current user is buyer or seller
  if (data.buyer && data.buyer.id === currentUserId) {
    authStore.updateBalance(data.buyer.balance)
    authStore.updateLockedBalance(data.buyer.locked_balance)
  } else if (data.seller && data.seller.id === currentUserId) {
    authStore.updateBalance(data.seller.balance)
    authStore.updateLockedBalance(data.seller.locked_balance)
  }
  
  orderMessage.value = `Order matched! Trade executed at $${data.trade.price}`
  setTimeout(() => {
    orderMessage.value = ''
  }, 5000)
}

async function submitOrder() {
  orderMessage.value = ''
  orderError.value = ''
  orderLoading.value = true

  try {
    const result = await tradingStore.placeOrder({
      symbol: symbol.value,
      side: side.value,
      price: parseFloat(price.value),
      amount: parseFloat(amount.value),
    })

    if (result.success) {
      if (result.matched) {
        orderMessage.value = 'Order matched and executed immediately!'
      } else {
        orderMessage.value = 'Order placed successfully!'
      }
      price.value = ''
      amount.value = ''
      authStore.fetchUser() // Refresh user balance
    } else {
      orderError.value = result.error
    }
  } catch (err) {
    orderError.value = err.message || 'Failed to place order'
  } finally {
    orderLoading.value = false
  }
}

async function cancelOrder(orderId) {
  const result = await tradingStore.cancelOrder(orderId)
  if (result.success) {
    authStore.fetchUser()
  } else {
    orderError.value = result.error
  }
}
</script>

<template>
  <div class="dashboard-container">
    <!-- Wallet Overview -->
    <div class="wallet-overview">
      <div class="wallet-card usd">
        <div class="wallet-icon">$</div>
        <div class="wallet-info">
          <span class="wallet-label">USD Balance</span>
          <span class="wallet-value">${{ formatNumber(authStore.user?.balance || 0) }}</span>
          <span class="wallet-available">Available: ${{ availableUsdBalance }}</span>
        </div>
      </div>
      <div v-for="asset in tradingStore.assets" :key="asset.symbol" class="wallet-card asset">
        <div class="wallet-icon">{{ asset.symbol === 'BTC' ? '₿' : 'Ξ' }}</div>
        <div class="wallet-info">
          <span class="wallet-label">{{ asset.symbol }}</span>
          <span class="wallet-value">{{ formatNumber(asset.amount) }}</span>
          <span class="wallet-available">Available: {{ formatNumber((parseFloat(asset.amount) - parseFloat(asset.locked_amount)).toFixed(8)) }}</span>
        </div>
      </div>
    </div>

    <div class="dashboard-grid">
      <!-- Order Form -->
      <div class="card order-form-card">
        <h2>Place Limit Order</h2>

        <div v-if="orderMessage" class="success-message">{{ orderMessage }}</div>
        <div v-if="orderError" class="error-message">{{ orderError }}</div>

        <form @submit.prevent="submitOrder" class="order-form">
          <div class="form-group">
            <label for="symbol">Symbol</label>
            <select id="symbol" v-model="symbol">
              <option v-for="symbol in symbols" :key="symbol" :value="symbol">{{ symbol }}/USD</option>
            </select>
          </div>

          <div class="side-toggle">
            <button
              type="button"
              :class="['side-btn', 'buy', { active: side === 'buy' }]"
              @click="side = 'buy'"
            >
              Buy
            </button>
            <button
              type="button"
              :class="['side-btn', 'sell', { active: side === 'sell' }]"
              @click="side = 'sell'"
            >
              Sell
            </button>
          </div>

          <div class="form-group">
            <label for="price">Price (USD)</label>
            <input
              id="price"
              v-model="price"
              type="number"
              step="0.00000001"
              min="0"
              placeholder="0.00000000"
              required
            />
          </div>

          <div class="form-group">
            <label for="amount">Amount ({{ symbol }})</label>
            <input
              id="amount"
              v-model="amount"
              type="number"
              step="0.00000001"
              min="0"
              placeholder="0.00000000"
              required
            />
          </div>

          <div class="total-row">
            <span>Total:</span>
            <span>${{ total }}</span>
          </div>

          <div v-if="side === 'sell' && commissionPreview" class="commission-info">
            <div class="commission-row">
              <span>Commission (1.5%):</span>
              <span>-${{ commissionPreview }}</span>
            </div>
            <div class="commission-row net">
              <span>Net Proceeds:</span>
              <span>${{ netProceeds }}</span>
            </div>
          </div>

          <button
            type="submit"
            :class="['btn-submit', side]"
            :disabled="orderLoading"
          >
            {{ orderLoading ? 'Placing...' : `${side === 'buy' ? 'Buy' : 'Sell'} ${symbol}` }}
          </button>
        </form>
      </div>

      <!-- Order Book -->
      <div class="card order-book-card">
        <h2>Order Book - {{ symbol }}/USD</h2>

        <div v-if="tradingStore.orderBookLoading" class="loading">Loading...</div>

        <div v-else class="order-book">
          <!-- Sell Orders (Asks) -->
          <div class="order-book-section">
            <div class="order-book-header">
              <span>Price</span>
              <span>Amount</span>
            </div>
            <div class="order-book-rows sells">
              <div
                v-for="(order, index) in [...tradingStore.orderBook?.sell_orders].reverse()"
                :key="'sell-' + index"
                class="order-row sell"
              >
                <span class="price">{{ formatNumber(order.price) }}</span>
                <span class="amount">{{ formatNumber(order.amount) }}</span>
              </div>
              <div v-if="tradingStore.orderBook.sell_orders.length === 0" class="empty">No sell orders</div>
            </div>
          </div>

          <div class="spread-divider">
            <span>Spread</span>
          </div>

          <!-- Buy Orders (Bids) -->
          <div class="order-book-section">
            <div class="order-book-rows buys">
              <div
                v-for="(order, index) in tradingStore.orderBook.buy_orders"
                :key="'buy-' + index"
                class="order-row buy"
              >
                <span class="price">{{ formatNumber(order.price) }}</span>
                <span class="amount">{{ formatNumber(order.amount) }}</span>
              </div>
              <div v-if="tradingStore.orderBook.buy_orders.length === 0" class="empty">No buy orders</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Order History with Tabs -->
      <div class="card order-history-card">
        <h2>Order History</h2>

        <div class="order-tabs">
          <button 
            :class="['tab-btn', { active: orderTab === 'open' }]"
            @click="orderTab = 'open'"
          >
            Open ({{ tradingStore.openOrders.length }})
          </button>
          <button 
            :class="['tab-btn', { active: orderTab === 'filled' }]"
            @click="orderTab = 'filled'"
          >
            Filled ({{ tradingStore.filledOrders.length }})
          </button>
          <button 
            :class="['tab-btn', { active: orderTab === 'cancelled' }]"
            @click="orderTab = 'cancelled'"
          >
            Cancelled ({{ tradingStore.cancelledOrders.length }})
          </button>
          <button 
            :class="['tab-btn', { active: orderTab === 'all' }]"
            @click="orderTab = 'all'"
          >
            All ({{ tradingStore.orders.length }})
          </button>
        </div>

        <div v-if="tradingStore.ordersLoading" class="loading">Loading...</div>

        <div v-else-if="filteredOrders.length === 0" class="empty-state">
          No {{ orderTab }} orders
        </div>

        <table v-else class="orders-table">
          <thead>
            <tr>
              <th>Symbol</th>
              <th>Side</th>
              <th>Price</th>
              <th>Amount</th>
              <th>Status</th>
              <th v-if="orderTab === 'open' || orderTab === 'all'">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in filteredOrders" :key="order.id">
              <td>{{ order.symbol }}</td>
              <td :class="order.side">{{ order.side?.toUpperCase() }}</td>
              <td>{{ formatNumber(order.price) }}</td>
              <td>{{ formatNumber(order.amount) }}</td>
              <td style="text-transform: uppercase;">{{ order.status }}</td>
              <td v-if="orderTab === 'open' || orderTab === 'all'">
                <button 
                  v-if="order.status === 'open'" 
                  class="btn-cancel" 
                  @click="cancelOrder(order.id)"
                >
                  Cancel
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Trade History -->
      <div class="card trade-history-card">
        <h2>Trade History</h2>

        <div v-if="tradingStore.tradesLoading" class="loading">Loading...</div>

        <div v-else-if="tradingStore.trades.length === 0" class="empty-state">
          No trades yet
        </div>

        <table v-else class="trades-table">
          <thead>
            <tr>
              <th>Symbol</th>
              <th>Price</th>
              <th>Amount</th>
              <th>Buyer</th>
              <th>Seller</th>
              <th>Fee</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="trade in tradingStore.trades" :key="trade.id">
              <td>{{ trade.symbol }}</td>
              <td>{{ formatNumber(trade.price) }}</td>
              <td>{{ formatNumber(trade.amount) }}</td>
              <td>{{ trade.buyer_name || 'N/A' }}</td>
              <td>{{ trade.seller_name || 'N/A' }}</td>
              <td class="fee">${{ formatNumber(trade.fee) }}</td>
              <td class="date">{{ formatDate(trade.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

/* Wallet Overview */
.wallet-overview {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.wallet-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, #1a1a3a 0%, #0f0f23 100%);
  border: 1px solid #2a2a4a;
  border-radius: 12px;
  padding: 1rem 1.5rem;
  min-width: 220px;
  flex: 1;
}

.wallet-card.usd {
  border-color: #22c55e;
  background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, #0f0f23 100%);
}

.wallet-icon {
  font-size: 2rem;
}

.wallet-info {
  display: flex;
  flex-direction: column;
}

.wallet-label {
  color: #8888aa;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.wallet-value {
  color: #ffffff;
  font-size: 1.25rem;
  font-weight: 700;
}

.wallet-available {
  color: #4ade80;
  font-size: 0.75rem;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 320px 1fr 1.5fr;
  grid-template-rows: auto auto;
  gap: 1.5rem;
}

.order-form-card {
  grid-row: 1 / 3;
}

.order-book-card {
  grid-column: 2;
  grid-row: 1 / 3;
}

.order-history-card {
  grid-column: 3;
  grid-row: 1 / 3;
  max-height: 700px;
  overflow-y: auto;
}

.trade-history-card {
  grid-column: 1 / -1;
}

/* Order Tabs */
.order-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.tab-btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: 1px solid #3a3a5a;
  background: transparent;
  color: #8888aa;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  border-color: #5a5a7a;
  color: #ffffff;
}

.tab-btn.active {
  background: rgba(37, 99, 235, 0.2);
  border-color: rgb(37, 99, 235);
  color: #60a5fa;
}

@media (max-width: 1400px) {
  .dashboard-grid {
    grid-template-columns: 320px 1fr;
    grid-template-rows: auto auto auto;
  }
  .order-form-card {
    grid-row: 1 / 3;
  }
  .order-book-card {
    grid-column: 2;
    grid-row: 1;
  }
  .order-history-card {
    grid-column: 2;
    grid-row: 2;
    max-height: 400px;
  }
}

@media (max-width: 900px) {
  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto;
  }
  .order-form-card {
    grid-column: 1;
    grid-row: auto;
  }
  .order-book-card {
    grid-column: 2;
    grid-row: auto;
  }
  .order-history-card {
    grid-column: 1 / -1;
    grid-row: auto;
    max-height: 400px;
  }
}

@media (max-width: 768px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
  .order-form-card,
  .order-book-card,
  .order-history-card {
    grid-column: 1;
    grid-row: auto;
  }
}

.card {
  background: #0f0f23;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #2a2a4a;
}

.card h2 {
  color: #ffffff;
  font-size: 1.125rem;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #2a2a4a;
}

.card h3 {
  color: #ccccdd;
  font-size: 0.875rem;
  margin-bottom: 0.75rem;
}

.order-form {
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
  color: #8888aa;
  font-size: 0.875rem;
}

.form-group input,
.form-group select {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  border: 1px solid #3a3a5a;
  background: #1a1a3a;
  color: #ffffff;
  font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: rgb(37, 99, 235);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

.side-toggle {
  display: flex;
  gap: 0.5rem;
}

.side-btn {
  flex: 1;
  padding: 0.75rem;
  border-radius: 8px;
  border: 1px solid #3a3a5a;
  background: transparent;
  color: #8888aa;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.side-btn.buy.active {
  background: rgba(34, 197, 94, 0.2);
  border-color: #22c55e;
  color: #4ade80;
}

.side-btn.sell.active {
  background: rgba(239, 68, 68, 0.2);
  border-color: #ef4444;
  color: #f87171;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  color: #ffffff;
  font-weight: 500;
}

.commission-info {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 8px;
  padding: 0.75rem;
}

.commission-row {
  display: flex;
  justify-content: space-between;
  color: #8888aa;
  font-size: 0.875rem;
  padding: 0.25rem 0;
}

.commission-row.net {
  color: #ffffff;
  font-weight: 500;
  border-top: 1px solid rgba(59, 130, 246, 0.3);
  margin-top: 0.5rem;
  padding-top: 0.5rem;
}

.btn-submit {
  padding: 1rem;
  border-radius: 8px;
  border: none;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-submit.buy {
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  color: #ffffff;
}

.btn-submit.sell {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: #ffffff;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.balance-info {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #2a2a4a;
}

.balance-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  color: #ccccdd;
  font-size: 0.875rem;
}

.balance-value {
  color: #ffffff;
  font-family: monospace;
}

.balance-value .locked {
  color: #f59e0b;
  font-size: 0.75rem;
}

/* Order Book Styles */
.order-book {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.order-book-header {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  color: #8888aa;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.order-book-rows {
  display: flex;
  flex-direction: column;
  max-height: 200px;
  overflow-y: auto;
}

.order-row {
  display: flex;
  justify-content: space-between;
  padding: 0.375rem 0;
  font-size: 0.875rem;
  font-family: monospace;
}

.order-row.buy .price {
  color: #4ade80;
}

.order-row.sell .price {
  color: #f87171;
}

.order-row .amount {
  color: #ccccdd;
}

.spread-divider {
  text-align: center;
  padding: 0.5rem;
  color: #8888aa;
  font-size: 0.75rem;
  border-top: 1px solid #2a2a4a;
  border-bottom: 1px solid #2a2a4a;
}

/* Orders Table */
.orders-table,
.trades-table {
  width: 100%;
  border-collapse: collapse;
}

.orders-table th,
.orders-table td,
.trades-table th,
.trades-table td {
  padding: 0.75rem 0.5rem;
  text-align: left;
  border-bottom: 1px solid #2a2a4a;
}

.orders-table th,
.trades-table th {
  color: #8888aa;
  font-weight: 500;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.orders-table td,
.trades-table td {
  color: #ffffff;
  font-size: 0.875rem;
}

.orders-table td.buy,
.trades-table td.buy {
  color: #4ade80;
}

.orders-table td.sell,
.trades-table td.sell {
  color: #f87171;
}

.trades-table td.date {
  color: #8888aa;
  font-size: 0.75rem;
}

.trades-table td.fee {
  color: #f59e0b;
}

.btn-cancel {
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  border: 1px solid #ef4444;
  background: transparent;
  color: #f87171;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: rgba(239, 68, 68, 0.2);
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

.loading,
.empty-state,
.empty {
  text-align: center;
  color: #8888aa;
  padding: 1rem;
}
</style>

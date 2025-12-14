import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useTradingStore = defineStore('trading', () => {
  // State
  const assets = ref([])
  const orders = ref([])
  const trades = ref([])
  const orderBook = ref({ buy_orders: [], sell_orders: [] })
  const currentSymbol = ref('BTC')
  
  // Loading states
  const assetsLoading = ref(false)
  const ordersLoading = ref(false)
  const tradesLoading = ref(false)
  const orderBookLoading = ref(false)

  // Computed
  const openOrders = computed(() => 
    orders.value.filter(o => o.status === 'open')
  )

  const filledOrders = computed(() => 
    orders.value.filter(o => o.status === 'filled')
  )

  const cancelledOrders = computed(() => 
    orders.value.filter(o => o.status === 'cancelled')
  )

  // Get asset by symbol
  function getAsset(symbol) {
    return assets.value.find(a => a.symbol === symbol)
  }

  // Get available asset balance (amount - locked)
  function getAvailableAssetBalance(symbol) {
    const asset = getAsset(symbol)
    if (!asset) return '0.00000000'
    return (parseFloat(asset.amount) - parseFloat(asset.locked_amount)).toFixed(8)
  }

  // Actions
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

  async function loadOrders(status = null) {
    ordersLoading.value = true
    try {
      const endpoint = status ? `/orders?status=${status}` : '/orders'
      const response = await api.get(endpoint)
      orders.value = response?.data || response || []
    } catch (err) {
      console.error('Failed to load orders:', err)
    } finally {
      ordersLoading.value = false
    }
  }

  // Load all orders (for order history)
  async function loadAllOrders() {
    return loadOrders() // No status filter = all orders
  }

  async function loadOpenOrders() {
    return loadOrders('open')
  }

  async function loadTrades() {
    tradesLoading.value = true
    try {
      const response = await api.get('/trades')
      trades.value = response?.data || response || []
    } catch (err) {
      console.error('Failed to load trades:', err)
    } finally {
      tradesLoading.value = false
    }
  }

  async function loadOrderBook(symbol = null) {
    const sym = symbol || currentSymbol.value
    orderBookLoading.value = true
    try {
      const response = await api.get(`/orderbook?symbol=${sym}`)
      orderBook.value = response
      currentSymbol.value = sym
    } catch (err) {
      console.error('Failed to load order book:', err)
    } finally {
      orderBookLoading.value = false
    }
  }

  async function placeOrder(orderData) {
    try {
      const response = await api.post('/orders', orderData)
      
      // Add order to local state immediately for instant UI update
      if (response?.data?.order) {
        const newOrder = response.data.order
        if (!orders.value.find(o => o.id === newOrder.id)) {
          orders.value.unshift(newOrder)
        }
      }
      
      // Refresh data after placing order
      await Promise.all([
        loadAssets(),
        loadAllOrders(),
        loadOrderBook(),
      ])

      return { 
        success: true, 
        data: response,
        matched: !!response?.trade 
      }
    } catch (err) {
      return { 
        success: false, 
        error: err.message || 'Failed to place order' 
      }
    }
  }

  async function cancelOrder(orderId) {
    try {
      await api.delete(`/orders/${orderId}`)
      
      // Remove from local state immediately
      const index = orders.value.findIndex(o => o.id === orderId)
      if (index !== -1) {
        orders.value[index].status = 'cancelled'
      }

      // Refresh data
      await Promise.all([
        loadAssets(),
        loadOrderBook(),
      ])

      return { success: true }
    } catch (err) {
      return { 
        success: false, 
        error: err.message || 'Failed to cancel order' 
      }
    }
  }

  // Handle real-time order matched event (private channel - for the user involved)
  function handleOrderMatched(data, currentUserId = null) {
    console.log('Order matched event received:', data)

    // Only update orders that belong to the current user
    if (data.buy_order && data.buy_order.user_id === currentUserId) {
      updateOrderInList(data.buy_order)
    }
    if (data.sell_order && data.sell_order.user_id === currentUserId) {
      updateOrderInList(data.sell_order)
    }

    // Add trade to history if not exists
    if (data.trade && !trades.value.find(t => t.id === data.trade.id)) {
      trades.value.unshift(data.trade)
    }

    // Refresh assets
    loadAssets()
    
    return data
  }

  // Update or add order in orders list
  function updateOrderInList(orderData) {
    if (!orderData || !orderData.id) return
    
    const existingIndex = orders.value.findIndex(o => o.id === orderData.id)
    if (existingIndex !== -1) {
      // Update existing order
      orders.value[existingIndex] = { ...orders.value[existingIndex], ...orderData }
    } else {
      // Add new order
      orders.value.unshift(orderData)
    }
  }

  // Handle order created event (public channel - for order book)
  function handleOrderCreated(data) {
    console.log('Order created event received:', data)
    
    if (!data.order) return

    const order = data.order
    
    // Only add to order book if it matches current symbol
    if (order.symbol !== currentSymbol.value) return
    
    // Add to the appropriate side
    if (order.side === 'buy') {
      // Insert in sorted order (highest price first)
      const buyOrders = [...orderBook.value.buy_orders]
      const insertIndex = buyOrders.findIndex(o => parseFloat(o.price) < parseFloat(order.price))
      if (insertIndex === -1) {
        buyOrders.push(order)
      } else {
        buyOrders.splice(insertIndex, 0, order)
      }
      orderBook.value.buy_orders = buyOrders
    } else {
      // Insert in sorted order (lowest price first)
      const sellOrders = [...orderBook.value.sell_orders]
      const insertIndex = sellOrders.findIndex(o => parseFloat(o.price) > parseFloat(order.price))
      if (insertIndex === -1) {
        sellOrders.push(order)
      } else {
        sellOrders.splice(insertIndex, 0, order)
      }
      orderBook.value.sell_orders = sellOrders
    }
  }

  // Handle order cancelled event (public channel - for order book)
  function handleOrderCancelled(data) {
    console.log('Order cancelled event received:', data)
    
    if (!data.order_id) return
    
    // Only process if it matches current symbol
    if (data.symbol !== currentSymbol.value) return
    
    // Remove from the appropriate side
    if (data.side === 'buy') {
      orderBook.value.buy_orders = orderBook.value.buy_orders.filter(o => o.id !== data.order_id)
    } else {
      orderBook.value.sell_orders = orderBook.value.sell_orders.filter(o => o.id !== data.order_id)
    }
  }

  // Handle trade executed event (public channel - for order book)
  function handleTradeExecuted(data) {
    console.log('Trade executed event received:', data)
    
    // Remove both matched orders from order book
    if (data.buy_order_id) {
      orderBook.value.buy_orders = orderBook.value.buy_orders.filter(o => o.id !== data.buy_order_id)
    }
    if (data.sell_order_id) {
      orderBook.value.sell_orders = orderBook.value.sell_orders.filter(o => o.id !== data.sell_order_id)
    }
  }

  function updateOrderStatus(orderId, status) {
    const order = orders.value.find(o => o.id === orderId)
    if (order) {
      order.status = status
    }
  }

  // Update asset from real-time event
  function updateAsset(symbol, updates) {
    const asset = assets.value.find(a => a.symbol === symbol)
    if (asset) {
      Object.assign(asset, updates)
    } else {
      assets.value.push(updates)
    }
  }

  // Refresh all data
  async function refreshAll() {
    await Promise.all([
      loadAssets(),
      loadAllOrders(),
      loadTrades(),
      loadOrderBook(),
    ])
  }

  return {
    // State
    assets,
    orders,
    trades,
    orderBook,
    currentSymbol,

    // Loading states
    assetsLoading,
    ordersLoading,
    tradesLoading,
    orderBookLoading,

    // Computed
    openOrders,
    filledOrders,
    cancelledOrders,

    // Getters
    getAsset,
    getAvailableAssetBalance,

    // Actions
    loadAssets,
    loadOrders,
    loadAllOrders,
    loadOpenOrders,
    loadTrades,
    loadOrderBook,
    placeOrder,
    cancelOrder,
    refreshAll,

    // Real-time handlers
    handleOrderMatched,
    handleOrderCreated,
    handleOrderCancelled,
    handleTradeExecuted,
    updateOrderStatus,
    updateOrderInList,
    updateAsset,
  }
})

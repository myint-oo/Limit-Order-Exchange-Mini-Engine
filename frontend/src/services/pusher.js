import Pusher from 'pusher-js'

const PUSHER_KEY = import.meta.env.VITE_PUSHER_APP_KEY || ''
const PUSHER_CLUSTER = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1'
const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

let pusherInstance = null
let userChannel = null

/**
 * Get CSRF token from cookies
 */
function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match) {
    return decodeURIComponent(match[1])
  }
  return null
}

/**
 * Initialize Pusher with user authentication for private channels.
 */
export function initializePusher() {
  if (pusherInstance) {
    return pusherInstance
  }

  if (!PUSHER_KEY) {
    console.warn('Pusher key not configured. Real-time updates disabled.')
    return null
  }

  const csrfToken = getCsrfToken()
  
  pusherInstance = new Pusher(PUSHER_KEY, {
    cluster: PUSHER_CLUSTER,
    forceTLS: true,
    // Use custom authorizer to include credentials (cookies)
    authorizer: (channel) => ({
      authorize: async (socketId, callback) => {
        try {
          const csrfToken = getCsrfToken()
          const response = await fetch(`${API_URL}/api/broadcasting/auth`, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest',
              ...(csrfToken && { 'X-XSRF-TOKEN': csrfToken }),
            },
            body: new URLSearchParams({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })

          if (!response.ok) {
            const error = await response.text()
            callback(new Error(`Auth failed: ${response.status}`), null)
            return
          }

          const data = await response.json()
          callback(null, data)
        } catch (error) {
          callback(error, null)
        }
      },
    }),
  })

  // Enable logging in development
  if (import.meta.env.DEV) {
    Pusher.logToConsole = true
  }

  return pusherInstance
}

/**
 * Subscribe to a user's private channel for order updates.
 * @param {number} userId - The authenticated user's ID
 * @param {Object} handlers - Event handlers
 * @param {Function} handlers.onOrderMatched - Called when an order is matched
 */
export function subscribeToUserChannel(userId, handlers = {}) {
  const pusher = initializePusher()
  if (!pusher) return null

  // Unsubscribe from previous channel if exists
  if (userChannel) {
    userChannel.unbind_all()
    pusher.unsubscribe(userChannel.name)
  }

  const channelName = `private-user.${userId}`
  userChannel = pusher.subscribe(channelName)

  // Handle subscription success
  userChannel.bind('pusher:subscription_succeeded', () => {
    console.log(`Successfully subscribed to ${channelName}`)
  })

  // Handle subscription error
  userChannel.bind('pusher:subscription_error', (error) => {
    console.error(`Failed to subscribe to ${channelName}:`, error)
  })

  // Bind order matched event
  if (handlers.onOrderMatched) {
    userChannel.bind('order.matched', handlers.onOrderMatched)
  }

  return userChannel
}

// Store for order book channels
const orderbookChannels = {}

/**
 * Subscribe to a symbol's public order book channel.
 * @param {string} symbol - The trading symbol (e.g., 'BTC', 'ETH')
 * @param {Object} handlers - Event handlers
 * @param {Function} handlers.onOrderCreated - Called when a new order is added
 * @param {Function} handlers.onOrderCancelled - Called when an order is cancelled
 * @param {Function} handlers.onTradeExecuted - Called when a trade is executed
 */
export function subscribeToOrderBook(symbol, handlers = {}) {
  const pusher = initializePusher()
  if (!pusher) return null

  const channelName = `orderbook.${symbol}`

  // Unsubscribe from previous channel if exists
  if (orderbookChannels[symbol]) {
    orderbookChannels[symbol].unbind_all()
    pusher.unsubscribe(channelName)
  }

  const channel = pusher.subscribe(channelName)
  orderbookChannels[symbol] = channel

  // Handle subscription success
  channel.bind('pusher:subscription_succeeded', () => {
    console.log(`Successfully subscribed to ${channelName}`)
  })

  // Bind events
  if (handlers.onOrderCreated) {
    channel.bind('order.created', handlers.onOrderCreated)
  }

  if (handlers.onOrderCancelled) {
    channel.bind('order.cancelled', handlers.onOrderCancelled)
  }

  if (handlers.onTradeExecuted) {
    channel.bind('trade.executed', handlers.onTradeExecuted)
  }

  return channel
}

/**
 * Unsubscribe from a symbol's order book channel.
 * @param {string} symbol - The trading symbol
 */
export function unsubscribeFromOrderBook(symbol) {
  const pusher = initializePusher()
  if (!pusher) return

  const channelName = `orderbook.${symbol}`
  
  if (orderbookChannels[symbol]) {
    orderbookChannels[symbol].unbind_all()
    pusher.unsubscribe(channelName)
    delete orderbookChannels[symbol]
  }
}

/**
 * Unsubscribe from user channel and disconnect Pusher.
 */
export function disconnectPusher() {
  if (userChannel && pusherInstance) {
    userChannel.unbind_all()
    pusherInstance.unsubscribe(userChannel.name)
    userChannel = null
  }

  // Unsubscribe from all orderbook channels
  Object.keys(orderbookChannels).forEach(symbol => {
    if (orderbookChannels[symbol]) {
      orderbookChannels[symbol].unbind_all()
      pusherInstance?.unsubscribe(`orderbook.${symbol}`)
    }
  })

  if (pusherInstance) {
    pusherInstance.disconnect()
    pusherInstance = null
  }
}

/**
 * Get current Pusher connection state.
 */
export function getPusherState() {
  if (!pusherInstance) {
    return 'disconnected'
  }
  return pusherInstance.connection.state
}

export default {
  initializePusher,
  subscribeToUserChannel,
  subscribeToOrderBook,
  unsubscribeFromOrderBook,
  disconnectPusher,
  getPusherState,
}

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match) {
    return decodeURIComponent(match[1])
  }
  return null
}

function buildHeaders() {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  const csrfToken = getCsrfToken()
  if (csrfToken) {
    headers['X-XSRF-TOKEN'] = csrfToken
  }

  return headers
}

async function getCsrfCookie() {
  await fetch(`${API_URL}/sanctum/csrf-cookie`, {
    credentials: 'include',
  })
}

async function request(endpoint, options = {}) {
  const { ...fetchOptions } = options

  const response = await fetch(`${API_URL}/api${endpoint}`, {
    ...fetchOptions,
    credentials: 'include',
    headers: {
      ...buildHeaders(),
      ...fetchOptions.headers,
    },
  })

  // Handle 204 No Content
  if (response.status === 204) {
    return null
  }

  const data = await response.json()

  if (!response.ok) {
    const error = new Error(data.message || 'Request failed')
    error.status = response.status
    error.data = data
    throw error
  }

  return data
}

export const api = {
  getCsrfCookie,

  get: (endpoint, options = {}) =>
    request(endpoint, { ...options, method: 'GET' }),

  post: (endpoint, body, options = {}) =>
    request(endpoint, { ...options, method: 'POST', body: JSON.stringify(body) }),

  put: (endpoint, body, options = {}) =>
    request(endpoint, { ...options, method: 'PUT', body: JSON.stringify(body) }),

  patch: (endpoint, body, options = {}) =>
    request(endpoint, { ...options, method: 'PATCH', body: JSON.stringify(body) }),

  delete: (endpoint, options = {}) =>
    request(endpoint, { ...options, method: 'DELETE' }),
}

export default api
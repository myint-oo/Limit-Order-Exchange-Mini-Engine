export function formatDate(dateString) {
  return new Date(dateString).toLocaleString()
}

export function formatNumber(num) {
  return parseFloat(num).toFixed(8)
}
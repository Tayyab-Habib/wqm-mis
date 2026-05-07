/**
 * Format a date string to DD/MM/YYYY
 * @param {string|Date} date
 * @returns {string}
 */
export function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day}/${month}/${year}`
}

/**
 * Determine RAG status based on a value and thresholds
 * @param {number} value
 * @param {number} greenMax
 * @param {number} amberMax
 * @returns {'green'|'amber'|'red'}
 */
export function ragStatus(value, greenMax, amberMax) {
  if (value <= greenMax) return 'green'
  if (value <= amberMax) return 'amber'
  return 'red'
}

/**
 * Debounce a function
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function}
 */
export function debounce(fn, delay = 300) {
  let timer
  return (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}

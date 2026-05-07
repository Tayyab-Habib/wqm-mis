/**
 * Check if a value is non-empty
 * @param {*} value
 * @returns {boolean}
 */
export function required(value) {
  return value !== null && value !== undefined && String(value).trim() !== ''
}

/**
 * Check if a value is a valid number within optional bounds
 * @param {*} value
 * @param {{ min?: number, max?: number }} options
 * @returns {boolean}
 */
export function isNumericInRange(value, { min = -Infinity, max = Infinity } = {}) {
  const n = Number(value)
  return !isNaN(n) && n >= min && n <= max
}

/**
 * Validate a Pakistani CNIC (13 digits, no dashes)
 * @param {string} value
 * @returns {boolean}
 */
export function isCNIC(value) {
  return /^\d{13}$/.test(String(value))
}

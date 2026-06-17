/**
 * Format angka ke format Rupiah Indonesia
 * @param {number} value
 * @returns {string}  e.g. "Rp 15.000"
 */
export function formatRupiah(value) {
  if (value === null || value === undefined || isNaN(value)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

/**
 * Format tanggal ke dd/MM/yyyy
 * @param {string|Date} date
 * @returns {string}
 */
export function formatDate(date) {
  if (!date) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(date))
}

/**
 * Format tanggal + waktu ke dd/MM/yyyy HH:mm
 */
export function formatDateTime(date) {
  if (!date) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

/**
 * Check apakah tanggal kadaluarsa dalam N hari (default 30)
 */
export function isNearExpiry(dateStr, days = 30) {
  if (!dateStr) return false
  const expiry = new Date(dateStr)
  const now = new Date()
  const diffDays = Math.ceil((expiry - now) / (1000 * 60 * 60 * 24))
  return diffDays <= days && diffDays >= 0
}

/**
 * Check apakah tanggal sudah kadaluarsa
 */
export function isExpired(dateStr) {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

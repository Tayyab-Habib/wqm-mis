/**
 * Export Helpers
 * Utilities for exporting data to CSV and Excel formats
 */

/**
 * Convert array of objects to CSV string
 * @param {Array} data - Array of objects to convert
 * @param {Array} headers - Optional array of header labels
 * @returns {string} CSV string
 */
export function arrayToCSV(data, headers = null) {
  if (!data || data.length === 0) return ''

  // Get headers from first object if not provided
  const keys = headers || Object.keys(data[0])
  
  // Create header row
  const headerRow = keys.join(',')
  
  // Create data rows
  const dataRows = data.map(row => {
    return keys.map(key => {
      const value = row[key]
      // Handle values that contain commas, quotes, or newlines
      if (value === null || value === undefined) return ''
      const stringValue = String(value)
      if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
        return `"${stringValue.replace(/"/g, '""')}"`
      }
      return stringValue
    }).join(',')
  })
  
  return [headerRow, ...dataRows].join('\n')
}

/**
 * Download CSV file
 * @param {string} csvContent - CSV content string
 * @param {string} filename - Filename without extension
 */
export function downloadCSV(csvContent, filename) {
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  link.setAttribute('href', url)
  link.setAttribute('download', `${filename}.csv`)
  link.style.visibility = 'hidden'
  
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  URL.revokeObjectURL(url)
}

/**
 * Export data to CSV
 * @param {Array} data - Array of objects to export
 * @param {string} filename - Filename without extension
 * @param {Array} headers - Optional array of header labels
 */
export function exportToCSV(data, filename, headers = null) {
  const csv = arrayToCSV(data, headers)
  downloadCSV(csv, filename)
}

/**
 * Export table data to Excel-compatible CSV
 * Handles special formatting for Excel
 * @param {Array} data - Array of objects to export
 * @param {string} filename - Filename without extension
 * @param {Object} options - Export options
 */
export function exportToExcel(data, filename, options = {}) {
  const {
    headers = null,
    sheetName = 'Sheet1',
    includeTimestamp = true
  } = options
  
  // Add BOM for Excel UTF-8 support
  const BOM = '\uFEFF'
  const csv = arrayToCSV(data, headers)
  
  const finalFilename = includeTimestamp 
    ? `${filename}_${new Date().toISOString().split('T')[0]}`
    : filename
  
  const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  link.setAttribute('href', url)
  link.setAttribute('download', `${finalFilename}.csv`)
  link.style.visibility = 'hidden'
  
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  URL.revokeObjectURL(url)
}

/**
 * Format data for export by flattening nested objects
 * @param {Array} data - Array of objects with potentially nested data
 * @param {Object} fieldMap - Map of field paths to export column names
 * @returns {Array} Flattened array of objects
 */
export function flattenForExport(data, fieldMap) {
  return data.map(item => {
    const flattened = {}
    Object.entries(fieldMap).forEach(([exportKey, sourcePath]) => {
      // Handle dot notation for nested properties
      const value = sourcePath.split('.').reduce((obj, key) => obj?.[key], item)
      flattened[exportKey] = value ?? '—'
    })
    return flattened
  })
}

/**
 * Export with custom formatting
 * @param {Array} data - Raw data array
 * @param {string} filename - Export filename
 * @param {Object} config - Configuration object
 */
export function exportWithFormatting(data, filename, config = {}) {
  const {
    fieldMap = null,
    headers = null,
    includeTimestamp = true,
  } = config
  
  // Flatten data if fieldMap provided
  const exportData = fieldMap ? flattenForExport(data, fieldMap) : data
  
  // Export to Excel
  exportToExcel(exportData, filename, { headers, includeTimestamp })
}

/**
 * Export data as a real .xlsx file using SpreadsheetML (Office Open XML)
 * No external library required — Excel, LibreOffice, and Google Sheets all open this natively.
 * @param {Array} data - Array of flat objects to export
 * @param {string} filename - Filename WITHOUT extension (e.g. "wss_water_scheme_detail")
 * @param {Object} options
 * @param {boolean} options.includeTimestamp - Append date to filename (default: false)
 */
export function exportToXLSX(data, filename, options = {}) {
  const { includeTimestamp = false } = options

  if (!data || data.length === 0) {
    alert('No data to export.')
    return
  }

  const headers = Object.keys(data[0])

  // Escape XML special characters
  function esc(val) {
    if (val === null || val === undefined) return ''
    return String(val)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;')
  }

  // Build header row
  const headerRow = headers
    .map(h => `<Cell ss:StyleID="header"><Data ss:Type="String">${esc(h)}</Data></Cell>`)
    .join('')

  // Build data rows
  const dataRows = data.map(row => {
    const cells = headers.map(h => {
      const val = row[h]
      const isNumber = val !== '' && val !== '—' && !isNaN(Number(val)) && val !== null && val !== undefined
      const type = isNumber ? 'Number' : 'String'
      const displayVal = isNumber ? Number(val) : esc(val)
      return `<Cell><Data ss:Type="${type}">${displayVal}</Data></Cell>`
    }).join('')
    return `<Row>${cells}</Row>`
  }).join('')

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:x="urn:schemas-microsoft-com:office:excel">
  <Styles>
    <Style ss:ID="header">
      <Font ss:Bold="1" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#1A2E4A" ss:Pattern="Solid"/>
      <Alignment ss:Horizontal="Center"/>
    </Style>
  </Styles>
  <Worksheet ss:Name="Sheet1">
    <Table>
      <Row>${headerRow}</Row>
      ${dataRows}
    </Table>
  </Worksheet>
</Workbook>`

  const finalFilename = includeTimestamp
    ? `${filename}_${new Date().toISOString().split('T')[0]}`
    : filename

  const blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)

  link.setAttribute('href', url)
  link.setAttribute('download', `${finalFilename}.xlsx`)
  link.style.visibility = 'hidden'

  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  URL.revokeObjectURL(url)
}

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { assetService } from '../../../services/assetService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()
const isAdmin   = computed(() => userStore.isSuperAdmin)

// ─── Tabs ──────────────────────────────────────────────────────────────────
const activeMainTab = ref('consumables')   // consumables | inventory
const activeSubTab  = ref('register')       // register | stock-out

// ─── Register: state ───────────────────────────────────────────────────────
const stockItems   = ref([])
const loading      = ref(false)
const errorMsg     = ref('')
const searchText   = ref('')
const catFilter    = ref('')
const ragFilter    = ref('')
const labFilter    = ref('')   // system-admin only — filters table by laboratory_id

// ─── Stock Out: state ──────────────────────────────────────────────────────
const stockOutTxns      = ref([])
const stockOutLoading   = ref(false)
const stockOutError     = ref('')
const dateFrom          = ref('')
const dateTo            = ref('')
const txnItemFilter     = ref('')
const txnTypeFilter     = ref('')
const txnLabFilter      = ref('')
const txnRefSearch      = ref('')

// ─── Modals ────────────────────────────────────────────────────────────────
const showAddMenu          = ref(false)
const showNewItemModal     = ref(false)
const showExistingItemModal = ref(false)
const showLogIssueModal    = ref(false)

const newItemForm = ref({
  name: '', category: '', unit: 'Kit',
  opening_balance: '', min_threshold: '',
  expiry_date: '', supplier: '', remarks: ''
})
const newItemErrors = ref({})
const newItemSaving = ref(false)

const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null
function showToast(type, message, duration = 4000) {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message }
  toastTimer = setTimeout(() => { toast.value.show = false }, duration)
}

const existingItemForm = ref({
  item_id: '', qty_to_add: '', date: '',
  supplier: '', new_expiry_date: '', invoice_ref: '', remarks: ''
})

const logIssueForm = ref({
  item_id: '', qty: '', date: '', type: 'analysis',
  recipient_lab_id: '', recipient_name: '', recipient_role: '', sample_ref: '', remarks: ''
})
const logIssueErrors = ref({})
const logIssueSaving = ref(false)

// ─── Expand/collapse per-item batches ──────────────────────────────────────
// Each row in the Stock Register can expand to show its individual IN batches
// (one per material_log with status='in'). Batches synthesize a BT/YY/### id
// from chronological IN-log order so users can reference them.
const expandedItems = ref(new Set())
function isExpanded(itemId) { return expandedItems.value.has(itemId) }
function toggleExpand(itemId) {
  const next = new Set(expandedItems.value)
  next.has(itemId) ? next.delete(itemId) : next.add(itemId)
  expandedItems.value = next
}

function batchesFor(item) {
  // "Batches" = inflow events. Stock-out logs deplete existing batches but
  // aren't themselves a new batch, so we filter to status='in' only.
  const logs = (item.rawLogs || []).filter(l => l.status === 'in')
  const sorted = [...logs].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
  return sorted.map((log, idx) => {
    const yr  = log.created_at ? new Date(log.created_at).getFullYear().toString().slice(-2) : '26'
    const seq = String(idx + 1).padStart(3, '0')
    const qty = Number(log.quantity ?? 0)
    const expiry = log.date_of_expiry
    const expDate = expiry ? new Date(expiry) : null
    const valid   = expDate && !Number.isNaN(expDate.getTime())
    const expCrit = valid && expDate < new Date()
    const expWarn = valid && expDate < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)

    // Per-batch RAG: expired > zero qty > below threshold > near-expiry > adequate.
    let rag = 'Adequate', ragClass = 'r-green'
    if (expCrit)                                     { rag = 'Expired';   ragClass = 'r-red'   }
    else if (qty <= 0)                               { rag = 'Critical';  ragClass = 'r-red'   }
    else if (item.threshold > 0 && qty < item.threshold) { rag = 'Depleting'; ragClass = 'r-amber' }
    else if (expWarn)                                { rag = 'Depleting'; ragClass = 'r-amber' }

    return {
      id:          log.id,
      batchId:     `BT/${yr}/${seq}`,
      description: log.remarks || log.dispatch_reference || (idx === 0 ? 'Old stock' : 'New receipt'),
      unit:        log.unit || item.unit || '-',
      qty,
      expiry:      formatExpiry(expiry),
      expWarn, expCrit,
      rag, ragClass,
    }
  })
}

// ─── Per-row actions dropdown ──────────────────────────────────────────────
// The menu uses <Teleport to="body"> + position:fixed so it can't get clipped
// by .tbl-wrap's overflow:hidden. We compute coordinates from the button's
// bounding rect each time the menu opens.
const actionMenuOpenFor = ref(null)
const actionMenuItem = ref(null)
// When flipping above the button we anchor `bottom` instead of `top` so the
// menu always sits flush against the button regardless of its actual height.
const actionMenuPos = ref({ top: null, bottom: null, left: 0 })
const MENU_WIDTH = 170
// Rough height used only to decide WHETHER to flip — not for positioning.
const MENU_HEIGHT_ESTIMATE = 130

function toggleActionMenu(item, ev) {
  if (actionMenuOpenFor.value === item.id) {
    closeActionMenu()
    return
  }
  if (ev?.currentTarget) {
    const rect = ev.currentTarget.getBoundingClientRect()
    const spaceBelow = window.innerHeight - rect.bottom
    const flipUp = spaceBelow < MENU_HEIGHT_ESTIMATE + 8
    const left = Math.max(8, rect.right - MENU_WIDTH)
    actionMenuPos.value = flipUp
      ? { top: null, bottom: (window.innerHeight - rect.top + 4), left }
      : { top: rect.bottom + 4, bottom: null, left }
  }
  actionMenuItem.value = item
  actionMenuOpenFor.value = item.id
}

function closeActionMenu() {
  actionMenuOpenFor.value = null
  actionMenuItem.value = null
}

// ─── Trail modal ───────────────────────────────────────────────────────────
const showTrailModal = ref(false)
const trailItem = ref(null)
const trailTypeFilter = ref('')
const trailDateFrom = ref('')
const trailDateTo = ref('')
const trailSearch = ref('')

async function openTrail(item) {
  // Reset filters + open immediately with the current snapshot so the modal
  // doesn't feel sluggish, then re-fetch in the background to surface any
  // logs created since the page loaded (e.g. a stock-out logged a moment ago).
  trailItem.value = item
  trailTypeFilter.value = ''
  trailDateFrom.value = ''
  trailDateTo.value = ''
  trailSearch.value = ''
  showTrailModal.value = true
  closeActionMenu()

  try {
    await loadStock()
    const fresh = stockItems.value.find(i => i.id === item.id)
    if (fresh) trailItem.value = fresh
  } catch (e) {
    // Stock-load errors are already surfaced via errorMsg by loadStock();
    // don't let them break the modal experience.
  }
}

const trailRows = computed(() => {
  if (!trailItem.value) return []
  const logs = trailItem.value.rawLogs || []
  const yr = new Date().getFullYear().toString().slice(-2)
  let inCounter = 0, outCounter = 0
  // Sort oldest → newest so SI/SO numbering is chronological
  return [...logs]
    .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    .map(log => {
      const isIn = log.status === 'in'
      const seq = isIn ? ++inCounter : ++outCounter
      const txnId = `${isIn ? 'SI' : 'SO'}/${yr}/${String(seq).padStart(3, '0')}`
      const qty = Number(log.quantity ?? 0)
      let party = '-'
      if (isIn) {
        const supplier = trailItem.value.supplier || 'Stock added'
        party = log.dispatch_reference ? `${supplier} — ${log.dispatch_reference}` : supplier
      } else if (log.recipient_lab?.name) {
        const ref = log.dispatch_reference || log.sample_ref
        party = `${log.recipient_lab.name}${ref ? ' [' + ref + ']' : ''} — ${TYPE_LABELS[log.type] || log.type || 'Transfer'}`
      } else if (log.recipient_name) {
        const ref = log.sample_ref
        party = `${log.recipient_name}${ref ? ' [' + ref + ']' : ''} — ${TYPE_LABELS[log.type] || log.type || 'Analysis'}`
      } else {
        party = TYPE_LABELS[log.type] || log.type || (isIn ? 'Stock In' : 'Stock Out')
      }
      return {
        id: log.id,
        txnId,
        date: formatExpiry(log.created_at),
        rawDate: log.created_at,
        type: isIn ? 'IN' : 'OUT',
        qty: isIn ? qty : -Math.abs(qty),
        party,
        remarks: log.remarks || '—',
      }
    })
})

const filteredTrailRows = computed(() => trailRows.value.filter(r => {
  if (trailTypeFilter.value && r.type !== trailTypeFilter.value) return false
  if (trailDateFrom.value && new Date(r.rawDate) < new Date(trailDateFrom.value)) return false
  if (trailDateTo.value && new Date(r.rawDate) > new Date(trailDateTo.value)) return false
  if (trailSearch.value) {
    const q = trailSearch.value.toLowerCase()
    if (!(`${r.txnId} ${r.party}`.toLowerCase().includes(q))) return false
  }
  return true
}))

const trailTotalIn  = computed(() => trailRows.value.filter(r => r.type === 'IN').reduce((s, r) => s + r.qty, 0))
const trailTotalOut = computed(() => trailRows.value.filter(r => r.type === 'OUT').reduce((s, r) => s + r.qty, 0))
const trailTxnCount = computed(() => trailRows.value.length)

function clearTrailFilters() {
  trailTypeFilter.value = ''
  trailDateFrom.value = ''
  trailDateTo.value = ''
  trailSearch.value = ''
}

// ─── Raise Demand modal ────────────────────────────────────────────────────
const showRaiseDemandModal = ref(false)
const raiseDemandItem = ref(null)
const raiseDemandForm = ref({ quantity: '', urgency: 'routine', required_by: '', justification: '' })
const raiseDemandErrors = ref({})
const raiseDemandSaving = ref(false)

// `kind` is 'stock' (material/consumable) or 'inventory' (asset/non-consumable).
// Stock-tab rows carry `materialId`; Inventory-tab rows carry `assetId`. We tag
// the item with its kind here so the submit handler can pick the right
// inventoryable_type + id without re-resolving from row shape.
function openRaiseDemand(item, kind = 'stock') {
  raiseDemandItem.value = { ...item, kind }
  raiseDemandForm.value = { quantity: '', urgency: 'routine', required_by: '', justification: '' }
  raiseDemandErrors.value = {}
  showRaiseDemandModal.value = true
  closeActionMenu()
}

async function submitRaiseDemand() {
  raiseDemandErrors.value = {}
  const f = raiseDemandForm.value
  const errs = {}
  if (!f.quantity || Number(f.quantity) <= 0) errs.quantity = ['Qty Requested must be greater than 0']
  else if (!Number.isInteger(Number(f.quantity))) errs.quantity = ['Quantity must be a whole number (no decimals)']
  if (!f.urgency) errs.urgency = ['Urgency is required']
  if (Object.keys(errs).length) { raiseDemandErrors.value = errs; return }

  const item = raiseDemandItem.value
  const isInventory = item?.kind === 'inventory'
  // Backend's IssueTypeEnum maps STOCK→'material', INVENTORY→'asset'.
  // Stock rows pass the material master id; inventory rows pass the asset master id.
  const inventoryableType = isInventory ? 'asset' : 'material'
  const inventoryableId   = isInventory ? item?.assetId : item?.materialId

  const payload = {
    urgency: f.urgency,
    justification: f.justification?.trim() || null,
    details: [{
      inventoryable_type: inventoryableType,
      inventoryable_id:   inventoryableId,
      quantity:           parseInt(f.quantity, 10),
      unit:               item?.unit || 'Pcs',
    }],
  }

  raiseDemandSaving.value = true
  try {
    await assetService.createInventory(payload)
    showRaiseDemandModal.value = false
    showToast('success', `Demand raised for "${item?.name}"`)
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      raiseDemandErrors.value = err.response.data?.errors || {}
      showToast('error', 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to raise demands')
    } else {
      showToast('error', err?.response?.data?.message || 'Failed to submit demand')
    }
  } finally {
    raiseDemandSaving.value = false
  }
}

// ─── Edit Item modal ───────────────────────────────────────────────────────
const showEditItemModal = ref(false)
const editItemForm = ref({
  id: null, lm_id: null, name: '', category: '', unit: 'Kit',
  available_quantity: '', threshold: '', date_of_expiry: '', supplier: '', remarks: ''
})
const editItemErrors = ref({})
const editItemSaving = ref(false)

function openEditItem(item) {
  editItemForm.value = {
    id: item.materialId,
    lm_id: item.id, // laboratory_material id — backend syncs the per-lab qty/threshold against this row
    name: item.name,
    category: item.cat || '',
    unit: item.unit || 'Kit',
    available_quantity: item.qty ?? '',
    threshold: item.threshold ?? '',
    date_of_expiry: item.earliestExpiryISO ? String(item.earliestExpiryISO).slice(0, 10) : '',
    supplier: item.supplier || '',
    remarks: '',
  }
  editItemErrors.value = {}
  showEditItemModal.value = true
  closeActionMenu()
}

async function saveEditItem() {
  editItemErrors.value = {}
  const f = editItemForm.value
  const errs = {}
  if (!f.name?.trim()) errs.name = ['Item Name is required']
  if (f.available_quantity === '' || Number(f.available_quantity) < 0) errs.quantity = ['Quantity must be 0 or greater']
  if (f.threshold === '' || Number(f.threshold) < 0) errs.threshold = ['Threshold must be 0 or greater']
  if (Object.keys(errs).length) { editItemErrors.value = errs; return }

  // `date_of_expiry` lives on per-batch log rows. We pass it through so the
  // backend can update the latest log row for this lab allocation.
  // `laboratory_material_id` tells the backend which per-lab allocation row to
  // sync qty + threshold (and expiry) against — without it, the listing (which
  // reads from `laboratory_materials`) would keep showing the old value.
  const qty = Number(f.available_quantity).toFixed(2)
  const payload = {
    name:                    f.name.trim(),
    category:                f.category || null,
    unit:                    f.unit,
    quantity:                qty,
    available_quantity:      qty,
    threshold:               Number(f.threshold).toFixed(2),
    laboratory_material_id:  f.lm_id,
    date_of_expiry:          f.date_of_expiry || null,
    supplier:           f.supplier?.trim() || null,
    status:             'active',
  }

  editItemSaving.value = true
  try {
    await assetService.updateMaterial(f.id, payload)
    showEditItemModal.value = false
    showToast('success', `"${payload.name}" updated`)
    await loadStock()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response.data?.errors || {}
      editItemErrors.value = errors
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — Only system administrators can edit stock items')
    } else if (status === 401) {
      showToast('error', '401 — Session expired, please log in again')
    } else {
      showToast('error', err?.response?.data?.message || `Failed to update item (HTTP ${status || 'unknown'})`)
    }
  } finally {
    editItemSaving.value = false
  }
}

// Labs list for the Recipient Lab dropdown (Transfer / Inter-lab Issuance only)
const labs = ref([])
async function loadLabs() {
  try {
    const res = await dropdownService.getLaboratories()
    labs.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to load labs:', e)
    labs.value = []
  }
}
const showRecipientLab = computed(() =>
  logIssueForm.value.type === 'transfer' || logIssueForm.value.type === 'inter_lab_issuance'
)

// ─── Helpers ───────────────────────────────────────────────────────────────
const CAT_ORDER = ['chem', 'micro', 'glass', 'safety', 'other']
const CAT_LABELS = {
  chem:   'CHEMICAL REAGENTS',
  micro:  'MICROBIOLOGICAL MEDIA & REAGENTS',
  glass:  'GLASSWARE & PLASTICWARE',
  safety: 'PROTECTIVE & SAFETY SUPPLIES',
  other:  'OTHER',
}
const CAT_ICONS = {
  chem:   '🧪',
  micro:  '🔴',
  glass:  '🧫',
  safety: '🖐',
  other:  '📦',
}

function deriveCategory(name = '') {
  const n = String(name).toLowerCase()
  if (/coliform|macconkey|broth|media|agar|micro/.test(n)) return 'micro'
  if (/bottle|glass|membrane|filter|flask|beaker|pipette/.test(n)) return 'glass'
  if (/glove|coat|mask|goggle|safety|protective/.test(n))   return 'safety'
  return 'chem'
}

function statusToRag(status, qty, threshold) {
  const s = String(status || '').toLowerCase()
  if (s === 'expired') return { rag: 'Expired',   ragClass: 'r-red' }
  if (s === 'depleted' || Number(qty) <= 0) return { rag: 'Critical', ragClass: 'r-red' }
  if (s === 'below_threshold' || (Number(threshold) > 0 && Number(qty) < Number(threshold)))
    return { rag: 'Depleting', ragClass: 'r-amber' }
  return { rag: 'Adequate', ragClass: 'r-green' }
}

function formatExpiry(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  const day = String(d.getDate()).padStart(2, '0')
  const mon = d.toLocaleString('en-US', { month: 'short' })
  const yr  = String(d.getFullYear()).slice(-2)
  return `${day}-${mon}-${yr}`
}

function mapMaterial(m) {
  const logs = m.material_logs || m.laboratory_material_logs || []
  const qty  = parseFloat(m.available_quantity ?? m.quantity ?? 0)
  const earliestExpiry = logs.reduce((earliest, log) => {
    const v = log.date_of_expiry || log.expiry_date
    if (!v) return earliest
    return !earliest || new Date(v) < new Date(earliest) ? v : earliest
  }, m.date_of_expiry || m.expiry_date || null)

  const expDate = earliestExpiry ? new Date(earliestExpiry) : null
  const valid   = expDate && !Number.isNaN(expDate.getTime())
  const expWarn = valid && expDate < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
  const expCrit = valid && expDate < new Date()
  // Date-based expiry trumps stock-based status: if the earliest batch is past
  // its expiry, the item is Expired regardless of qty/threshold.
  let { rag, ragClass } = statusToRag(m.status, qty, m.threshold)
  if (expCrit) { rag = 'Expired'; ragClass = 'r-red' }

  // `/laboratory/materials/all` returns laboratory_materials rows: `m.id` is the
  // lab-allocation id, NOT the master material id. The backend stock-out
  // endpoint validates against `materials.id`, so we capture the master id
  // separately and surface it as `materialId` for the issue-log payload.
  return {
    id:         m.id,
    materialId: m.material?.id ?? m.material_id ?? m.id,
    laboratoryId:   m.laboratory_id ?? m.laboratory?.id ?? null,
    laboratoryName: m.laboratory?.name || '',
    cat:    m.category || m.material?.category || m.type || deriveCategory(m.name || m.material?.name),
    name:   m.name || m.material?.name || '-',
    unit:   m.unit || '-',
    qty,
    threshold: parseFloat(m.threshold ?? 0),
    supplier: m.material?.supplier || m.supplier || '',
    earliestExpiryISO: earliestExpiry,
    batches: `${logs.length || 1} ${logs.length === 1 ? 'batch' : 'batches'}`,
    expiry:  formatExpiry(earliestExpiry),
    expWarn, expCrit,
    rag, ragClass,
    rawLogs: logs,
  }
}

const TYPE_LABELS = {
  analysis: 'Analysis',
  write_off: 'Write-off',
  transfer: 'Transfer',
  calibration: 'Calibration',
  inter_lab_issuance: 'Inter-lab Issuance',
}

function mapStockOutTxn(log, parentMaterial, idx) {
  // Round to integer for display — matches inventoryOutLogs and the new
  // integer-only validation on the Log Stock-Out / Log Inventory-Out forms.
  const qty = Math.round(Number(log.quantity ?? log.qty ?? 0))
  const when = log.created_at || log.date
  const yr  = when ? new Date(when).getFullYear().toString().slice(-2) : ''
  const typeRaw = log.type || 'analysis'
  return {
    id:           log.id,
    txnId:        log.transaction_id || `SO/${yr}/${String(idx + 1).padStart(3, '0')}`,
    date:         formatExpiry(when),
    itemName:     parentMaterial?.name || parentMaterial?.material?.name || log.item_name || '-',
    category:     (CAT_LABELS[deriveCategory(parentMaterial?.name || parentMaterial?.material?.name)] || '-')
                    .replace(/ & .*/, ' Reagent')
                    .replace('CHEMICAL REAGENT', 'Chemical Reagent')
                    .replace('MICROBIOLOGICAL MEDIA', 'Microbiological')
                    .replace('GLASSWARE', 'Glassware')
                    .replace('PROTECTIVE', 'Safety'),
    unit:         log.unit || parentMaterial?.unit || '-',
    qtyOut:       qty < 0 ? qty : -Math.abs(qty),
    type:         TYPE_LABELS[typeRaw] || typeRaw,
    recipientLab: log.recipient_lab?.name || '-',
    recipientName: log.recipient_name || log.issued_to || '-',
    recipientCode: log.recipient_code || '',
    recipientRole: log.recipient_role || '-',
    remarks:      log.remarks || '—',
  }
}

// ─── Loaders ───────────────────────────────────────────────────────────────
async function loadStock() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await assetService.getMaterials()
    const data = res.data?.data || res.data || []
    stockItems.value = Array.isArray(data) ? data.map(mapMaterial) : []
  } catch (e) {
    console.error(e)
    errorMsg.value = 'Failed to load stock inventory.'
    stockItems.value = []
  } finally {
    loading.value = false
  }
}

async function loadStockOut() {
  stockOutLoading.value = true
  stockOutError.value = ''
  try {
    const res = await assetService.getMaterials()
    const data = res.data?.data || res.data || []
    const txns = []
    let counter = 0
    if (Array.isArray(data)) {
      for (const mat of data) {
        // Backend serializes the eager-loaded relation as `laboratory_material_logs`.
        // We also accept `material_logs` as a fallback in case a future resource
        // wrapper renames it.
        const logs = mat.laboratory_material_logs || mat.material_logs || []
        for (const log of logs) {
          // The real signal is `status` — written from MaterialLogStatusEnum.
          // 'out' = stock-out transaction. 'waste' is also a deduction, treat
          // it the same so users see it on this tab.
          const isOut = log.status === 'out' || log.status === 'waste'
          if (isOut) txns.push(mapStockOutTxn(log, mat, counter++))
        }
      }
    }
    stockOutTxns.value = txns
  } catch (e) {
    console.error(e)
    stockOutError.value = 'Failed to load stock-out transactions.'
    stockOutTxns.value = []
  } finally {
    stockOutLoading.value = false
  }
}

// ─── Register: derived ─────────────────────────────────────────────────────
const filteredItems = computed(() => stockItems.value.filter(it => {
  const matchSearch = !searchText.value || it.name.toLowerCase().includes(searchText.value.toLowerCase())
  const matchCat    = !catFilter.value  || it.cat === catFilter.value
  const matchRag    = !ragFilter.value  || it.rag === ragFilter.value
  const matchLab    = !labFilter.value  || String(it.laboratoryId) === String(labFilter.value)
  return matchSearch && matchCat && matchRag && matchLab
}))

const groupedItems = computed(() => {
  const groups = {}
  filteredItems.value.forEach(it => {
    if (!groups[it.cat]) groups[it.cat] = []
    groups[it.cat].push(it)
  })
  return groups
})

// Summary cards reflect the current lab filter (and other filters) so admins
// switching labs see counts for the lab they're inspecting, not the whole DB.
const summaryAdequate  = computed(() => filteredItems.value.filter(i => i.rag === 'Adequate').length)
const summaryDepleting = computed(() => filteredItems.value.filter(i => i.rag === 'Depleting').length)
const summaryCritical  = computed(() => filteredItems.value.filter(i => i.rag === 'Critical' || i.qty === 0).length)
const summaryExpired   = computed(() => filteredItems.value.filter(i => i.rag === 'Expired').length)

// ─── Stock Out: derived ────────────────────────────────────────────────────
const filteredTxns = computed(() => stockOutTxns.value.filter(t => {
  if (dateFrom.value && new Date(t.date) < new Date(dateFrom.value)) return false
  if (dateTo.value   && new Date(t.date) > new Date(dateTo.value))   return false
  if (txnItemFilter.value && t.itemName !== txnItemFilter.value) return false
  if (txnTypeFilter.value && t.type     !== txnTypeFilter.value) return false
  if (txnLabFilter.value  && t.recipientLab !== txnLabFilter.value) return false
  if (txnRefSearch.value) {
    const q = txnRefSearch.value.toLowerCase()
    if (!(`${t.txnId} ${t.recipientCode}`.toLowerCase().includes(q))) return false
  }
  return true
}))

const totalOutUnits     = computed(() => filteredTxns.value.reduce((s, t) => s + Number(t.qtyOut || 0), 0))
const totalTxnCount     = computed(() => filteredTxns.value.length)
const totalWriteOffs    = computed(() => filteredTxns.value.filter(t => /write[\s-]?off/i.test(t.type)).length)
const lastIssuedDate    = computed(() => {
  if (!filteredTxns.value.length) return '—'
  const sorted = [...filteredTxns.value].sort((a, b) => new Date(b.date) - new Date(a.date))
  return sorted[0]?.date || '—'
})

const txnItemOptions = computed(() => Array.from(new Set(stockOutTxns.value.map(t => t.itemName))).filter(Boolean))
const txnTypeOptions = computed(() => Array.from(new Set(stockOutTxns.value.map(t => t.type))).filter(Boolean))
const txnLabOptions  = computed(() => Array.from(new Set(stockOutTxns.value.map(t => t.recipientLab))).filter(Boolean))

// ─── Inventory (Non-consumables) — SRS §2.7-2 ───────────────────────────────
const inventoryItems    = ref([])
const inventoryLoading  = ref(false)
const inventoryError    = ref('')
const invSearchText     = ref('')
const invCategoryFilter = ref('')
const invConditionFilter = ref('')

const showAddInventoryModal = ref(false)
const newInventoryForm = ref({
  name: '', category: '', item_code: '', quantity: 1, unit: 'Pcs',
  condition: 'good', date_of_purchase: '', purchase_value: '',
  location: '', last_verified: '', remarks: '',
})
const newInventoryErrors = ref({})
const newInventorySaving = ref(false)

function mapAsset(labAsset) {
  // /laboratory/assets/all returns laboratory_assets rows with the master asset
  // eager-loaded under `asset`. SRS §2.7-2 fields live on the master asset.
  const a = labAsset.asset || {}
  return {
    id:            labAsset.id,
    assetId:       a.id || labAsset.asset_id,
    kind:          a.kind || 'inventory',
    name:          a.name || '-',
    category:      a.category || '-',
    item_code:     a.item_code || '-',
    quantity:      parseFloat(labAsset.quantity ?? a.quantity ?? 0),
    unit:          labAsset.unit || '-',
    condition:     a.condition || 'good',
    date_of_purchase: a.date_of_purchase ? formatExpiry(a.date_of_purchase) : '-',
    purchase_value:   a.purchase_value ? Number(a.purchase_value).toLocaleString() : '-',
    // Each laboratory_assets row is owned by one lab. After a transfer the
    // same master asset can exist at multiple labs, so the row's lab name
    // is the authoritative location for that row. The master's `location`
    // field is a finer-grained sub-label (e.g. "Room 204, Shelf B") and
    // is used only as a fallback when the lab name is absent.
    location:      labAsset.laboratory?.name || a.location || '-',
    last_verified: a.last_verified ? formatExpiry(a.last_verified) : '—',
    remarks:       a.remarks || '',
    rawLogs:       labAsset.laboratory_asset_logs || [],
  }
}

async function loadInventory() {
  inventoryLoading.value = true
  inventoryError.value = ''
  try {
    const res = await assetService.getAssets()
    const data = res.data?.data || res.data || []
    inventoryItems.value = Array.isArray(data)
      ? data
          // Filter on the EAGER-LOADED master asset's kind, not the lab-asset row.
          .filter(a => ((a.asset?.kind) || 'inventory') === 'inventory')
          .map(mapAsset)
      : []
  } catch (e) {
    console.error(e)
    inventoryError.value = 'Failed to load inventory items.'
    inventoryItems.value = []
  } finally {
    inventoryLoading.value = false
  }
}

const filteredInventory = computed(() => inventoryItems.value.filter(it => {
  if (invSearchText.value && !it.name.toLowerCase().includes(invSearchText.value.toLowerCase())) return false
  if (invCategoryFilter.value && it.category !== invCategoryFilter.value) return false
  if (invConditionFilter.value && it.condition !== invConditionFilter.value) return false
  return true
}))

const inventoryCategoryOptions = computed(() => Array.from(new Set(inventoryItems.value.map(i => i.category))).filter(c => c && c !== '-'))

const invSummaryGood      = computed(() => inventoryItems.value.filter(i => i.condition === 'good').length)
const invSummaryAttention = computed(() => inventoryItems.value.filter(i => i.condition === 'fair' || i.condition === 'poor').length)
const invSummaryCondemned = computed(() => inventoryItems.value.filter(i => i.condition === 'condemned').length)
const invSummaryMissing   = computed(() => inventoryItems.value.filter(i => parseFloat(i.quantity) === 0).length)

// ─── Inventory sub-tabs (Register / Inventory Out) — SRS §2.7-2 ────────────
const activeInventorySubTab = ref('register')   // register | out

function switchInventorySubTab(tab) {
  activeInventorySubTab.value = tab
}

const DISPOSAL_LABELS = {
  condemned:    'Condemned',
  missing_lost: 'Missing / Lost',
  transferred:  'Transferred',
  disposed:     'Disposed',
  donated:      'Donated',
}

// Flat list of OUT log entries across all inventory items in this lab.
const inventoryOutLogs = computed(() => {
  const out = []
  let counter = 0
  for (const item of inventoryItems.value) {
    for (const log of item.rawLogs || []) {
      if (log.status !== 'out') continue
      const when = log.created_at
      const yr = when ? new Date(when).getFullYear().toString().slice(-2) : ''
      out.push({
        id:           log.id,
        txnId:        log.dispatch_reference || `IO/${yr}/${String(++counter).padStart(3, '0')}`,
        rawDate:      when,
        date:         formatExpiry(when),
        assetCode:    item.item_code || '-',
        itemName:     item.name,
        category:     item.category,
        // Round to integer for display. New entries are integer-only (FE + BE
        // validation), but legacy rows may still carry a decimal qty — round
        // those rather than expose .xx fragments in the table.
        qty:          -Math.abs(Math.round(Number(log.quantity ?? 0))),
        type:         log.type || 'condemned',
        typeLabel:    DISPOSAL_LABELS[log.type] || log.type || 'Disposal',
        recipientLab: log.recipient_lab?.name || '-',
        recipientName: log.recipient_name || '-',
        recipientRole: log.recipient_role || '-',
        remarks:      log.remarks || '—',
      })
    }
  }
  return out.sort((a, b) => new Date(b.rawDate) - new Date(a.rawDate))
})

// Filters for Inventory Out
const invOutDateFrom = ref('')
const invOutDateTo = ref('')
const invOutCategoryFilter = ref('')
const invOutTypeFilter = ref('')
const invOutSearch = ref('')

const filteredInventoryOut = computed(() => inventoryOutLogs.value.filter(r => {
  if (invOutDateFrom.value && new Date(r.rawDate) < new Date(invOutDateFrom.value)) return false
  if (invOutDateTo.value && new Date(r.rawDate) > new Date(invOutDateTo.value)) return false
  if (invOutCategoryFilter.value && r.category !== invOutCategoryFilter.value) return false
  if (invOutTypeFilter.value && r.type !== invOutTypeFilter.value) return false
  if (invOutSearch.value) {
    const q = invOutSearch.value.toLowerCase()
    if (!(`${r.assetCode} ${r.itemName}`.toLowerCase().includes(q))) return false
  }
  return true
}))

const invOutSummaryTotal       = computed(() => inventoryOutLogs.value.length)
const invOutSummaryCondemned   = computed(() => inventoryOutLogs.value.filter(r => r.type === 'condemned').length)
const invOutSummaryMissing     = computed(() => inventoryOutLogs.value.filter(r => r.type === 'missing_lost').length)
const invOutSummaryTransferred = computed(() => inventoryOutLogs.value.filter(r => r.type === 'transferred').length)

// ─── Log Out modal (inventory disposal) ────────────────────────────────────
const showLogOutModal = ref(false)
const logOutForm = ref({
  asset_id: '', qty: '', date: '', type: 'condemned',
  recipient_lab_id: '', recipient_name: '', recipient_role: '', asset_ref: '', remarks: '',
})
const logOutErrors = ref({})
const logOutSaving = ref(false)

const showInvRecipientLab = computed(() => logOutForm.value.type === 'transferred')

function openLogOutModal() {
  logOutForm.value = { asset_id: '', qty: '', date: '', type: 'condemned', recipient_lab_id: '', recipient_name: '', recipient_role: '', asset_ref: '', remarks: '' }
  logOutErrors.value = {}
  showLogOutModal.value = true
}

async function saveLogOut() {
  logOutErrors.value = {}
  const f = logOutForm.value
  const errs = {}
  if (!f.asset_id) errs.asset_id = ['Asset is required']
  if (!f.qty || Number(f.qty) <= 0) errs.quantity = ['Quantity must be greater than 0']
  else if (!Number.isInteger(Number(f.qty))) errs.quantity = ['Quantity must be a whole number (no decimals)']
  if (!f.type) errs.type = ['Disposal type is required']
  if (f.type === 'transferred' && !f.recipient_lab_id) errs.recipient_lab_id = ['Recipient lab is required for transfers']
  if (Object.keys(errs).length) { logOutErrors.value = errs; return }

  const selected = inventoryItems.value.find(i => i.id === f.asset_id)
  const recipientLabId = f.type === 'transferred' ? (f.recipient_lab_id || null) : null
  const payload = {
    asset_id:           selected?.assetId ?? f.asset_id,
    quantity:           parseInt(f.qty, 10),
    unit:               selected?.unit || 'Pcs',
    date:               f.date || null,
    type:               f.type,
    recipient_name:     f.recipient_name?.trim() || null,
    recipient_role:     f.recipient_role?.trim() || null,
    recipient_lab_id:   recipientLabId,
    asset_ref:          f.asset_ref?.trim() || null,
    remarks:            f.remarks?.trim() || null,
  }

  logOutSaving.value = true
  try {
    await assetService.logInventoryOut(payload)
    showLogOutModal.value = false
    showToast('success', `Inventory-out logged for "${selected?.name || 'item'}"`)
    await loadInventory()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response.data?.errors || {}
      logOutErrors.value = errors
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to log inventory-out')
    } else {
      showToast('error', err?.response?.data?.message || `Failed to log inventory-out (HTTP ${status || 'unknown'})`)
    }
  } finally {
    logOutSaving.value = false
  }
}

async function saveNewInventoryItem() {
  newInventoryErrors.value = {}
  const f = newInventoryForm.value
  const errs = {}
  if (!f.name?.trim())            errs.name = ['Item Name is required']
  if (!f.category?.trim())        errs.category = ['Category is required']
  if (f.quantity === '' || Number(f.quantity) < 0) errs.quantity = ['Quantity must be 0 or greater']
  if (!f.unit?.trim())            errs.unit = ['Unit is required']
  if (Object.keys(errs).length) { newInventoryErrors.value = errs; return }

  // AssetStatusEnum cases use capitalised values ('Active', 'Under_service', etc).
  // Sending lowercase 'active' fails Rule::in validation.
  const payload = {
    name:             f.name.trim(),
    kind:             'inventory',
    category:         f.category.trim(),
    item_code:        f.item_code?.trim() || null,
    quantity:         Number(f.quantity).toFixed(2),
    unit:             f.unit,
    status:           'Active',
    condition:        f.condition,
    date_of_purchase: f.date_of_purchase || null,
    purchase_value:   f.purchase_value ? Number(f.purchase_value).toFixed(2) : null,
    location:         f.location?.trim() || null,
    last_verified:    f.last_verified || null,
    remarks:          f.remarks?.trim() || null,
  }

  newInventorySaving.value = true
  try {
    await assetService.createAsset(payload)
    showAddInventoryModal.value = false
    showToast('success', `✅ Inventory item "${payload.name}" added`)
    newInventoryForm.value = { name: '', category: '', item_code: '', quantity: 1, unit: 'Pcs', condition: 'good', date_of_purchase: '', purchase_value: '', location: '', last_verified: '', remarks: '' }
    await loadInventory()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response.data?.errors || {}
      newInventoryErrors.value = errors
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to add inventory')
    } else {
      showToast('error', err?.response?.data?.message || `Failed to save inventory item (HTTP ${status || 'unknown'})`)
    }
  } finally {
    newInventorySaving.value = false
  }
}

function switchMainTab(tab) {
  activeMainTab.value = tab
  if (tab === 'inventory' && !inventoryItems.value.length && !inventoryLoading.value) loadInventory()
}

// ─── Actions ───────────────────────────────────────────────────────────────
function openNewItemModal()      { showAddMenu.value = false; showNewItemModal.value = true }
function openExistingItemModal() { showAddMenu.value = false; showExistingItemModal.value = true }

function switchSubTab(tab) {
  activeSubTab.value = tab
  if (tab === 'stock-out' && !stockOutTxns.value.length && !stockOutLoading.value) loadStockOut()
}

// ─── Save handlers ─────────────────────────────────────────────────────────
async function saveNewItem() {
  newItemErrors.value = {}

  // Required-field guard so the user gets feedback before the API rejects.
  const f = newItemForm.value
  const localErrors = {}
  if (!f.name?.trim())               localErrors.name = ['Item Name is required']
  if (f.opening_balance === '' || f.opening_balance === null) localErrors.quantity = ['Opening Balance is required']
  if (Number(f.opening_balance) < 0) localErrors.quantity = ['Opening Balance must be 0 or greater']
  if (f.min_threshold === '' || f.min_threshold === null)     localErrors.threshold = ['Min. Threshold is required']
  if (Number(f.min_threshold) < 0)   localErrors.threshold = ['Min. Threshold must be 0 or greater']
  if (!f.expiry_date)                localErrors.date_of_expiry = ['Expiry Date is required']
  else if (new Date(f.expiry_date) < new Date(new Date().toDateString()))
                                     localErrors.date_of_expiry = ['Expiry Date must be today or later']
  if (Object.keys(localErrors).length) { newItemErrors.value = localErrors; return }

  // Map UI fields → backend contract. `remarks` still has no DB home, so we
  // drop it for now (logged manually via material_logs if needed).
  const payload = {
    name:           f.name.trim(),
    category:       f.category || null,
    quantity:       Number(f.opening_balance).toFixed(2),
    unit:           f.unit,
    threshold:      Number(f.min_threshold).toFixed(2),
    supplier:       f.supplier?.trim() || null,
    date_of_expiry: f.expiry_date,
    status:         'active',
  }

  newItemSaving.value = true
  try {
    const res = await assetService.createMaterial(payload)
    const created = res.data?.data
    showNewItemModal.value = false
    showToast('success', `✅ Stock item "${created?.name || payload.name}" created successfully`)
    // reset form
    newItemForm.value = { name: '', category: '', unit: 'Kit', opening_balance: '', min_threshold: '', expiry_date: '', supplier: '', remarks: '' }
    await loadStock()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response.data?.errors || {}
      newItemErrors.value = errors
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to add stock')
    } else {
      const msg = err?.response?.data?.message || `Failed to save stock item (HTTP ${status || 'unknown'})`
      showToast('error', msg)
    }
  } finally {
    newItemSaving.value = false
  }
}

function saveExistingQty(){ showExistingItemModal.value = false }

async function saveLogIssue() {
  logIssueErrors.value = {}
  const f = logIssueForm.value

  // Client-side guard
  const localErrors = {}
  if (!f.item_id)               localErrors.material_id = ['Item is required']
  if (!f.qty || Number(f.qty) <= 0) localErrors.quantity = ['Quantity must be greater than 0']
  else if (!Number.isInteger(Number(f.qty))) localErrors.quantity = ['Quantity must be a whole number (no decimals)']
  if (!f.type)                  localErrors.type = ['Type is required']
  if (Object.keys(localErrors).length) { logIssueErrors.value = localErrors; return }

  const selectedItem = stockItems.value.find(i => i.id === f.item_id)
  // Recipient Lab only makes sense for Transfer / Inter-lab Issuance — null
  // it out for Analysis / Write-off / Calibration so we don't store a stale
  // selection if the user changed the type after picking a lab.
  const recipientLabId = (f.type === 'transfer' || f.type === 'inter_lab_issuance')
    ? (f.recipient_lab_id || null)
    : null
  const payload = {
    material_id:      selectedItem?.materialId ?? f.item_id,
    quantity:         parseInt(f.qty, 10),
    unit:             selectedItem?.unit || '',
    date:             f.date || null,
    type:             f.type,
    recipient_name:   f.recipient_name?.trim() || null,
    recipient_role:   f.recipient_role?.trim() || null,
    recipient_lab_id: recipientLabId,
    sample_ref:       f.sample_ref?.trim() || null,
    remarks:          f.remarks?.trim() || null,
  }

  logIssueSaving.value = true
  try {
    await assetService.logStockOut(payload)
    showLogIssueModal.value = false
    showToast('success', `✅ Stock-out logged for "${selectedItem?.name || 'item'}"`)
    logIssueForm.value = { item_id: '', qty: '', date: '', type: 'analysis', recipient_lab_id: '', recipient_name: '', recipient_role: '', sample_ref: '', remarks: '' }
    await Promise.all([loadStock(), loadStockOut()])
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      logIssueErrors.value = err.response.data?.errors || {}
      const msg = err.response.data?.message || 'Please fix the highlighted fields'
      showToast('error', msg)
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to log stock-out')
    } else {
      showToast('error', err?.response?.data?.message || 'Failed to log stock-out')
    }
  } finally {
    logIssueSaving.value = false
  }
}

// ─── Clear-filters helpers ─────────────────────────────────────────────────
// One per toolbar so users can wipe filter state with a single click instead
// of clearing each control individually.
function clearStockFilters() {
  catFilter.value = ''
  ragFilter.value = ''
  searchText.value = ''
}
function clearStockOutFilters() {
  dateFrom.value = ''
  dateTo.value = ''
  txnItemFilter.value = ''
  txnTypeFilter.value = ''
  txnLabFilter.value = ''
  txnRefSearch.value = ''
}
function clearInventoryFilters() {
  invCategoryFilter.value = ''
  invConditionFilter.value = ''
  invSearchText.value = ''
}
function clearInventoryOutFilters() {
  invOutDateFrom.value = ''
  invOutDateTo.value = ''
  invOutCategoryFilter.value = ''
  invOutTypeFilter.value = ''
  invOutSearch.value = ''
}

onMounted(() => { loadStock(); loadLabs() })
onUnmounted(() => { if (toastTimer) clearTimeout(toastTimer) })
</script>

<template>
  <div class="asset-management-container">
    <!-- Main Tabs -->
    <div class="main-tabs">
      <div class="tab" :class="{ active: activeMainTab === 'consumables' }" @click="switchMainTab('consumables')">
        <span class="tab-icon">📦</span> Stock (Consumables)
      </div>
      <div class="tab" :class="{ active: activeMainTab === 'inventory' }" @click="switchMainTab('inventory')">
        <span class="tab-icon">🗄</span> Inventory (Non-consumables)
      </div>
    </div>

    <!-- ═══════════════════════ Consumables ═══════════════════════ -->
    <div v-if="activeMainTab === 'consumables'" class="tab-content">

      <!-- Sub-Tabs -->
      <div class="sub-tabs">
        <div class="sub-tab" :class="{ active: activeSubTab === 'register' }" @click="switchSubTab('register')">
          <span class="st-icon"></span> Register
        </div>
        <div class="sub-tab" :class="{ active: activeSubTab === 'stock-out' }" @click="switchSubTab('stock-out')">
          <span class="stock-dot"></span> Stock Out
        </div>
      </div>

      <!-- ─────────── Register View ─────────── -->
      <div v-if="activeSubTab === 'register'">

        <!-- Summary cards -->
        <div class="cards summary-cards">
          <div class="card card-blue">
            <div class="c-lbl">TOTAL ITEMS</div>
            <div class="c-val c-blue-val">{{ filteredItems.length }}</div>
          </div>
          <div class="card card-green">
            <div class="c-lbl">ADEQUATE</div>
            <div class="c-val c-green-val">{{ summaryAdequate }}</div>
          </div>
          <div class="card card-amber">
            <div class="c-lbl">DEPLETING</div>
            <div class="c-val c-amber-val">{{ summaryDepleting }}</div>
          </div>
          <div class="card card-red">
            <div class="c-lbl">CRITICAL / ZERO</div>
            <div class="c-val c-red-val">{{ summaryCritical }}</div>
          </div>
          <div class="card card-red">
            <div class="c-lbl">EXPIRED</div>
            <div class="c-val c-red-val">{{ summaryExpired }}</div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
          <select v-model="catFilter" class="form-select select-w">
            <option value="">All Categories</option>
            <option value="chem">Chemical Reagents</option>
            <option value="micro">Microbiological Media &amp; Reagents</option>
            <option value="glass">Glassware &amp; Plasticware</option>
            <option value="safety">Protective &amp; Safety Supplies</option>
            <option value="other">Other</option>
          </select>

          <!-- Admin-only: filter the table by laboratory -->
          <select v-if="isAdmin" v-model="labFilter" class="form-select select-w">
            <option value="">All Labs</option>
            <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
          <select v-model="ragFilter" class="form-select select-w-sm">
            <option value="">All RAG</option>
            <option>Adequate</option>
            <option>Depleting</option>
            <option>Critical</option>
            <option>Expired</option>
          </select>
          <div class="search-input-wrapper">
            <span class="search-icon"></span>
            <input type="text" v-model="searchText" placeholder="Item name..." class="form-input search-input" />
          </div>
          <button class="btn btn-sec outline clear-btn" type="button" @click="clearStockFilters">✕ Clear</button>

          <div class="tsp"></div>

          <div class="dropdown-wrapper">
            <button class="btn btn-pri add-btn" @click="showAddMenu = !showAddMenu">
              + Add Item <span class="chevron"></span>
            </button>
            <div class="dropdown-menu" v-if="showAddMenu">
              <div class="dropdown-item" @click="openNewItemModal"></div>
              <div class="dropdown-item" @click="openExistingItemModal"></div>
            </div>
            <div class="dropdown-backdrop" v-if="showAddMenu" @click="showAddMenu = false"></div>
          </div>
        </div>

        <!-- Skeleton (initial load) -->
        <div v-if="loading" class="tbl-wrap">
          <table class="table">
            <thead>
              <tr>
                <th style="width: 48px;"></th>
                <th style="width: 60px; text-align: center;">S#</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Total Qty</th>
                <th style="text-align: center;">Batches</th>
                <th style="text-align: center;">Earliest Expiry</th>
                <th style="text-align: center;">RAG</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in 6" :key="'sk-' + i" class="item-row skeleton-row">
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-lg"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Error -->
        <div v-else-if="errorMsg" class="empty-state error">
          {{ errorMsg }}
          <button class="btn btn-sec outline retry-btn" @click="loadStock">Retry</button>
        </div>

        <!-- Table -->
        <div v-else class="tbl-wrap">
          <table class="table">
            <thead>
              <tr>
                <th style="width: 48px;"></th>
                <th style="width: 60px; text-align: center;">S#</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Location</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Total Qty</th>
                <th style="text-align: center;">Batches</th>
                <th style="text-align: center;">Earliest Expiry</th>
                <th style="text-align: center;">RAG</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="cat in CAT_ORDER" :key="cat">
                <template v-if="groupedItems[cat] && groupedItems[cat].length > 0">
                  <tr class="category-header-row">
                    <td colspan="10">
                      <span class="cat-title">
                        <span class="cat-icon">{{ CAT_ICONS[cat] }}</span>{{ CAT_LABELS[cat] }}
                      </span>
                    </td>
                  </tr>
                  <template v-for="(item, idx) in groupedItems[cat]" :key="item.id">
                    <tr class="item-row">
                      <td style="text-align: center;">
                        <button class="btn-expand" :class="{ expanded: isExpanded(item.id) }" type="button" @click="toggleExpand(item.id)"></button>
                      </td>
                      <td style="text-align: center; color: #334155;">{{ idx + 1 }}</td>
                      <td style="text-align: left;">
                        <b class="item-name">{{ item.name }}</b>
                      </td>
                      <td style="text-align: left; color: #475569; font-size: 12px;">
                        {{ item.laboratoryName || '—' }}
                      </td>
                      <td style="text-align: center; color: #475569;">{{ item.unit }}</td>
                      <td style="text-align: center;">
                        <b :class="{'text-red': item.qty === 0, 'text-dark': item.qty > 0}">{{ item.qty }}</b>
                      </td>
                      <td style="text-align: center;">
                        <span class="batch-badge">{{ item.batches }}</span>
                      </td>
                      <td style="text-align: center; font-weight: 600;" :class="{
                        'text-red': item.expCrit,
                        'text-amber': item.expWarn && !item.expCrit,
                        'text-dark': !item.expWarn && !item.expCrit
                      }">
                        {{ item.expiry }}
                        <span v-if="item.expWarn || item.expCrit" class="warn-icon"></span>
                      </td>
                      <td style="text-align: center;">
                        <span class="badge" :class="item.ragClass">{{ item.rag }}</span>
                      </td>
                      <td style="text-align: center;" class="action-cell">
                        <button class="btn-actions" type="button" @click.stop="toggleActionMenu(item, $event)">
                          <span class="dots">⋮</span> Actions
                        </button>
                      </td>
                    </tr>
                    <!-- Expanded batches (one per IN log) -->
                    <template v-if="isExpanded(item.id)">
                      <tr v-for="batch in batchesFor(item)" :key="`b-${batch.id}`" class="batch-row">
                        <td></td>
                        <td></td>
                        <td style="text-align: left; padding-left: 24px;">
                          <span class="batch-prefix">└</span>
                          <span class="batch-id">{{ batch.batchId }}</span>
                          <span class="batch-desc"> — {{ batch.description }}</span>
                        </td>
                        <td></td>
                        <td style="text-align: center; color: #475569;">{{ batch.unit }}</td>
                        <td style="text-align: center;">{{ batch.qty }}</td>
                        <td style="text-align: center; color: #94a3b8;">—</td>
                        <td style="text-align: center; font-weight: 600;" :class="{
                          'text-red': batch.expCrit,
                          'text-amber': batch.expWarn && !batch.expCrit,
                          'text-dark': !batch.expWarn && !batch.expCrit
                        }">
                          {{ batch.expiry }}
                          <span v-if="batch.expWarn || batch.expCrit" class="warn-icon"></span>
                        </td>
                        <td style="text-align: center;">
                          <span class="badge" :class="batch.ragClass">{{ batch.rag }}</span>
                        </td>
                        <td></td>
                      </tr>
                    </template>
                  </template>
                </template>
              </template>
              <tr v-if="!filteredItems.length">
                <td colspan="10" class="text-center empty-row">No stock items found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─────────── Stock Out View ─────────── -->
      <div v-else-if="activeSubTab === 'stock-out'">

        <!-- Summary cards (4) -->
        <div class="cards summary-cards stock-out-cards">
          <div class="card card-red-strong">
            <div class="c-lbl">TOTAL OUT (THIS PERIOD)</div>
            <div class="c-val c-red-strong-val">{{ totalOutUnits }} units</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">TRANSACTIONS</div>
            <div class="c-val c-blue-val">{{ totalTxnCount }}</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">WRITE-OFFS</div>
            <div class="c-val c-blue-val">{{ totalWriteOffs }}</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">LAST ISSUED</div>
            <div class="c-val c-mono-val">{{ lastIssuedDate }}</div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar txn-toolbar">
          <input type="date" v-model="dateFrom" class="form-input date-input" />
          <input type="date" v-model="dateTo"   class="form-input date-input" />
          <select v-model="txnItemFilter" class="form-select select-md">
            <option value="">All Items</option>
            <option v-for="opt in txnItemOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
          <select v-model="txnTypeFilter" class="form-select select-sm">
            <option value="">All Types</option>
            <option v-for="opt in txnTypeOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
          <select v-model="txnLabFilter" class="form-select select-md">
            <option value="">All Recipient Labs</option>
            <option v-for="opt in txnLabOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
          <div class="search-input-wrapper">
            <span class="search-icon"></span>
            <input type="text" v-model="txnRefSearch" placeholder="Sample ID / Ref..." class="form-input search-input" />
          </div>
          <button class="btn btn-sec outline clear-btn" type="button" @click="clearStockOutFilters">✕ Clear</button>

          <div class="tsp"></div>

          <button v-write="'add_material_logs'" class="btn btn-pri add-btn" @click="showLogIssueModal = true">+ Log Issue</button>
        </div>

        <!-- Skeleton loading -->
        <div v-if="stockOutLoading" class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Txn ID</th>
                <th style="text-align: left;">Date</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Qty OUT</th>
                <th style="text-align: center;">Type</th>
                <th style="text-align: left;">Recipient Lab</th>
                <th style="text-align: left;">Recipient Name</th>
                <th style="text-align: left;">Recipient Role</th>
                <th style="text-align: left;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in 6" :key="'sk-so-' + i" class="item-row skeleton-row">
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-lg"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else-if="stockOutError" class="empty-state error">
          {{ stockOutError }}
          <button class="btn btn-sec outline retry-btn" @click="loadStockOut">Retry</button>
        </div>

        <!-- Transactions Table -->
        <div v-else class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Txn ID</th>
                <th style="text-align: left;">Date</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Qty OUT</th>
                <th style="text-align: center;">Type</th>
                <th style="text-align: left;">Recipient Lab</th>
                <th style="text-align: left;">Recipient Name</th>
                <th style="text-align: left;">Recipient Role</th>
                <th style="text-align: left;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(t, idx) in filteredTxns" :key="t.id || t.txnId" class="item-row">
                <td style="text-align: center;">{{ idx + 1 }}</td>
                <td class="txn-id-cell">{{ t.txnId }}</td>
                <td>{{ t.date }}</td>
                <td><b class="item-name">{{ t.itemName }}</b></td>
                <td>{{ t.category }}</td>
                <td style="text-align: center;">{{ t.unit }}</td>
                <td style="text-align: center;">
                  <b class="qty-out">{{ t.qtyOut }}</b>
                </td>
                <td style="text-align: center;">
                  <span class="type-badge" :class="`type-${String(t.type).toLowerCase().replace(/[^a-z]/g, '')}`">
                    {{ t.type }}
                  </span>
                </td>
                <td>{{ t.recipientLab }}</td>
                <td>
                  <div class="recipient-cell">
                    <span class="rcp-name">{{ t.recipientName }}</span>
                    <span v-if="t.recipientCode" class="rcp-code">[{{ t.recipientCode }}]</span>
                  </div>
                </td>
                <td>{{ t.recipientRole }}</td>
                <td class="muted">{{ t.remarks }}</td>
              </tr>
              <tr v-if="!filteredTxns.length">
                <td colspan="12" class="text-center empty-row">No stock-out transactions found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Inventory (Non-consumables) — SRS §2.7-2 ═══════════════════════ -->
    <div v-if="activeMainTab === 'inventory'" class="tab-content">

      <!-- Sub-Tabs -->
      <div class="sub-tabs">
        <div class="sub-tab" :class="{ active: activeInventorySubTab === 'register' }" @click="switchInventorySubTab('register')">
          <span class="st-icon"></span> Register
        </div>
        <div class="sub-tab" :class="{ active: activeInventorySubTab === 'out' }" @click="switchInventorySubTab('out')">
          <span class="stock-dot"></span> Inventory Out
        </div>
      </div>

      <!-- ─────────── Register View ─────────── -->
      <div v-if="activeInventorySubTab === 'register'">
        <!-- Summary cards -->
        <div class="cards summary-cards stock-out-cards">
          <div class="card card-blue">
            <div class="c-lbl">TOTAL ITEMS</div>
            <div class="c-val c-blue-val">{{ inventoryItems.length }}</div>
          </div>
          <div class="card card-green">
            <div class="c-lbl">GOOD CONDITION</div>
            <div class="c-val c-green-val">{{ invSummaryGood }}</div>
          </div>
          <div class="card card-amber">
            <div class="c-lbl">NEEDS ATTENTION</div>
            <div class="c-val c-amber-val">{{ invSummaryAttention }}</div>
          </div>
          <div class="card card-red">
            <div class="c-lbl">CONDEMNED / MISSING</div>
            <div class="c-val c-red-val">{{ invSummaryCondemned + invSummaryMissing }}</div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
          <select v-model="invCategoryFilter" class="form-select select-w-sm">
            <option value="">All Categories</option>
            <option v-for="cat in inventoryCategoryOptions" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <select v-model="invConditionFilter" class="form-select select-w-sm">
            <option value="">All Conditions</option>
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="poor">Poor</option>
            <option value="condemned">Condemned</option>
          </select>
          <div class="search-input-wrapper">
            <span class="search-icon"></span>
            <input type="text" v-model="invSearchText" placeholder="Item name..." class="form-input search-input" />
          </div>
          <button class="btn btn-sec outline clear-btn" type="button" @click="clearInventoryFilters">✕ Clear</button>
          <div class="tsp"></div>
          <button class="btn btn-pri add-btn" @click="showAddInventoryModal = true">+ Add Item</button>
        </div>

        <!-- Loading / Error -->
        <!-- Skeleton loading -->
        <div v-if="inventoryLoading" class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: left;">Item Code</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Condition</th>
                <th style="text-align: center;">Purchase Date</th>
                <th style="text-align: right;">Purchase Value</th>
                <th style="text-align: left;">Location</th>
                <th style="text-align: center;">Last Verified</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in 6" :key="'sk-inv-' + i" class="item-row skeleton-row">
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-lg"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else-if="inventoryError" class="empty-state error">
          {{ inventoryError }}
          <button class="btn btn-sec outline retry-btn" @click="loadInventory">Retry</button>
        </div>

        <!-- Table -->
        <div v-else class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: left;">Item Code</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">Unit</th>
                <th style="text-align: center;">Condition</th>
                <th style="text-align: center;">Purchase Date</th>
                <th style="text-align: right;">Purchase Value</th>
                <th style="text-align: left;">Location</th>
                <th style="text-align: center;">Last Verified</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in filteredInventory" :key="item.id" class="item-row">
                <td style="text-align: center;">{{ idx + 1 }}</td>
                <td><b class="item-name">{{ item.name }}</b></td>
                <td>{{ item.category }}</td>
                <td class="txn-id-cell">{{ item.item_code }}</td>
                <td style="text-align: center;"><b>{{ item.quantity }}</b></td>
                <td style="text-align: center;">{{ item.unit }}</td>
                <td style="text-align: center;">
                  <span class="badge" :class="{
                    'r-green': item.condition === 'good',
                    'r-amber': item.condition === 'fair' || item.condition === 'poor',
                    'r-red':   item.condition === 'condemned'
                  }">{{ item.condition }}</span>
                </td>
                <td style="text-align: center;">{{ item.date_of_purchase }}</td>
                <td style="text-align: right;">{{ item.purchase_value }}</td>
                <td>{{ item.location }}</td>
                <td style="text-align: center;">{{ item.last_verified }}</td>
                <td style="text-align: center;" class="action-cell">
                  <button class="btn-actions" type="button" @click.stop="toggleActionMenu(item, $event)">
                    <span class="dots">⋮</span> Actions
                  </button>
                </td>
              </tr>
              <tr v-if="!filteredInventory.length">
                <td colspan="12" class="text-center empty-row">No inventory items found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─────────── Inventory Out View ─────────── -->
      <div v-else-if="activeInventorySubTab === 'out'">

        <!-- Summary cards (4) -->
        <div class="cards summary-cards stock-out-cards">
          <div class="card card-red-strong">
            <div class="c-lbl">TOTAL OUT (ALL TIME)</div>
            <div class="c-val c-red-strong-val">{{ invOutSummaryTotal }} items</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">CONDEMNED</div>
            <div class="c-val c-blue-val">{{ invOutSummaryCondemned }}</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">MISSING / LOST</div>
            <div class="c-val c-blue-val">{{ invOutSummaryMissing }}</div>
          </div>
          <div class="card card-blue">
            <div class="c-lbl">TRANSFERRED OUT</div>
            <div class="c-val c-blue-val">{{ invOutSummaryTransferred }}</div>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar txn-toolbar">
          <input type="date" v-model="invOutDateFrom" class="form-input date-input" />
          <input type="date" v-model="invOutDateTo" class="form-input date-input" />
          <select v-model="invOutCategoryFilter" class="form-select select-md">
            <option value="">All Categories</option>
            <option v-for="cat in inventoryCategoryOptions" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <select v-model="invOutTypeFilter" class="form-select select-md">
            <option value="">All Types</option>
            <option value="condemned">Condemned</option>
            <option value="missing_lost">Missing / Lost</option>
            <option value="transferred">Transferred</option>
            <option value="disposed">Disposed</option>
            <option value="donated">Donated</option>
          </select>
          <div class="search-input-wrapper">
            <span class="search-icon"></span>
            <input type="text" v-model="invOutSearch" placeholder="Asset code / Item..." class="form-input search-input" />
          </div>
          <button class="btn btn-sec outline clear-btn" type="button" @click="clearInventoryOutFilters">✕ Clear</button>
          <div class="tsp"></div>
          <button class="btn btn-sec outline" type="button">↓ Export</button>
          <button v-write="'add_asset_logs'" class="btn btn-pri add-btn" @click="openLogOutModal">+ Log Out</button>
        </div>

        <!-- Skeleton loading -->
        <div v-if="inventoryLoading" class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Txn ID</th>
                <th style="text-align: left;">Date</th>
                <th style="text-align: left;">Asset Code</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">Disposal Type</th>
                <th style="text-align: left;">Recipient Lab</th>
                <th style="text-align: left;">Recipient Name</th>
                <th style="text-align: left;">Recipient Role</th>
                <th style="text-align: left;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in 6" :key="'sk-io-' + i" class="item-row skeleton-row">
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-lg"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-xs"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
                <td><span class="sk-bar sk-bar-sm"></span></td>
                <td><span class="sk-bar sk-bar-md"></span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else-if="inventoryError" class="empty-state error">
          {{ inventoryError }}
          <button class="btn btn-sec outline retry-btn" @click="loadInventory">Retry</button>
        </div>

        <!-- Transactions Table -->
        <div v-else class="tbl-wrap">
          <table class="table txn-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">S#</th>
                <th style="text-align: left;">Txn ID</th>
                <th style="text-align: left;">Date</th>
                <th style="text-align: left;">Asset Code</th>
                <th style="text-align: left;">Item Name</th>
                <th style="text-align: left;">Category</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">Disposal Type</th>
                <th style="text-align: left;">Recipient Lab</th>
                <th style="text-align: left;">Recipient Name</th>
                <th style="text-align: left;">Recipient Role</th>
                <th style="text-align: left;">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in filteredInventoryOut" :key="r.id" class="item-row">
                <td style="text-align: center;">{{ idx + 1 }}</td>
                <td class="txn-id-cell">{{ r.txnId }}</td>
                <td>{{ r.date }}</td>
                <td class="txn-id-cell">{{ r.assetCode }}</td>
                <td><b class="item-name">{{ r.itemName }}</b></td>
                <td>{{ r.category }}</td>
                <td style="text-align: center;"><b class="qty-out">{{ r.qty }}</b></td>
                <td style="text-align: center;">
                  <span class="type-badge" :class="`type-${r.type.replace(/[^a-z]/g, '')}`">{{ r.typeLabel }}</span>
                </td>
                <td>{{ r.recipientLab }}</td>
                <td>{{ r.recipientName }}</td>
                <td>{{ r.recipientRole }}</td>
                <td class="muted">{{ r.remarks }}</td>
              </tr>
              <tr v-if="!filteredInventoryOut.length">
                <td colspan="12" class="text-center empty-row">No inventory-out transactions found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Add Inventory Item Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showAddInventoryModal" @click.self="showAddInventoryModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>+ Add Inventory Item</h3>
          <button class="close-btn" type="button" @click="showAddInventoryModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Non-consumable asset (furniture, fixtures, office equipment, vehicles).</p>

          <div class="form-group full-width">
            <label>Item Name <span class="req">*</span></label>
            <input type="text" v-model="newInventoryForm.name" placeholder="e.g. Office Desk" class="form-input focus-bold" :class="{ 'has-error': newInventoryErrors.name }" />
            <div v-if="newInventoryErrors.name" class="field-error">{{ newInventoryErrors.name[0] }}</div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Category <span class="req">*</span></label>
              <select v-model="newInventoryForm.category" class="form-select" :class="{ 'has-error': newInventoryErrors.category }">
                <option value="">&mdash; Select Category &mdash;</option>
                <option value="Furniture">Furniture</option>
                <option value="Fixtures">Fixtures</option>
                <option value="Office Equipment">Office Equipment</option>
                <option value="Vehicles">Vehicles</option>
                <option value="Other">Other</option>
              </select>
              <div v-if="newInventoryErrors.category" class="field-error">{{ newInventoryErrors.category[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Item Code (unique)</label>
              <input type="text" v-model="newInventoryForm.item_code" placeholder="e.g. FRN-001" class="form-input" :class="{ 'has-error': newInventoryErrors.item_code }" />
              <div v-if="newInventoryErrors.item_code" class="field-error">{{ newInventoryErrors.item_code[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Quantity <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" v-model="newInventoryForm.quantity" class="form-input" :class="{ 'has-error': newInventoryErrors.quantity }" />
              <div v-if="newInventoryErrors.quantity" class="field-error">{{ newInventoryErrors.quantity[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Unit <span class="req">*</span></label>
              <select v-model="newInventoryForm.unit" class="form-select" :class="{ 'has-error': newInventoryErrors.unit }">
                <option value="Pcs">Pcs</option>
                <option value="Set">Set</option>
                <option value="Pair">Pair</option>
                <option value="Other">Other</option>
              </select>
              <div v-if="newInventoryErrors.unit" class="field-error">{{ newInventoryErrors.unit[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Condition</label>
              <select v-model="newInventoryForm.condition" class="form-select">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="condemned">Condemned</option>
              </select>
            </div>
            <div class="form-group half-width">
              <label>Date of Purchase</label>
              <input type="date" v-model="newInventoryForm.date_of_purchase" class="form-input" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Purchase Value</label>
              <input type="number" step="0.01" min="0" v-model="newInventoryForm.purchase_value" placeholder="e.g. 15000" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Last Verified</label>
              <input type="date" v-model="newInventoryForm.last_verified" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Location (Lab)</label>
            <select v-model="newInventoryForm.location" class="form-select">
              <option value="">&mdash; Select Laboratory &mdash;</option>
              <option v-for="lab in labs" :key="lab.id" :value="lab.name">{{ lab.name }}</option>
            </select>
          </div>

          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea v-model="newInventoryForm.remarks" placeholder="Optional notes" class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button v-write="'add_inventories'" class="btn btn-pri" type="button" :disabled="newInventorySaving" @click="saveNewInventoryItem">
            <span class="btn-icon">💾</span> {{ newInventorySaving ? 'Saving…' : 'Save Item' }}
          </button>
          <button class="btn btn-sec outline" type="button" :disabled="newInventorySaving" @click="showAddInventoryModal = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Log Out (Inventory Disposal) Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showLogOutModal" @click.self="showLogOutModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>🔴 Log Inventory Out</h3>
          <button class="close-btn" type="button" @click="showLogOutModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Records a disposal event (Condemned, Missing/Lost, Transferred, etc.) for a non-consumable asset.</p>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Asset <span class="req">*</span></label>
              <select v-model="logOutForm.asset_id" class="form-select" :class="{ 'has-error': logOutErrors.asset_id }">
                <option value="">&mdash; Select Asset &mdash;</option>
                <option v-for="item in inventoryItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.item_code !== '-' ? item.item_code : item.unit }})</option>
              </select>
              <div v-if="logOutErrors.asset_id" class="field-error">{{ logOutErrors.asset_id[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Qty <span class="req">*</span></label>
              <input type="number" step="1" min="1" v-model="logOutForm.qty" placeholder="e.g. 1" class="form-input" :class="{ 'has-error': logOutErrors.quantity }" />
              <div v-if="logOutErrors.quantity" class="field-error">{{ logOutErrors.quantity[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Date</label>
              <input type="date" v-model="logOutForm.date" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Disposal Type <span class="req">*</span></label>
              <select v-model="logOutForm.type" class="form-select" :class="{ 'has-error': logOutErrors.type }">
                <option value="condemned">Condemned</option>
                <option value="missing_lost">Missing / Lost</option>
                <option value="transferred">Transferred</option>
                <option value="disposed">Disposed</option>
                <option value="donated">Donated</option>
              </select>
              <div v-if="logOutErrors.type" class="field-error">{{ logOutErrors.type[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width" v-if="showInvRecipientLab">
              <label>Recipient Lab <span class="req">*</span></label>
              <select v-model="logOutForm.recipient_lab_id" class="form-select" :class="{ 'has-error': logOutErrors.recipient_lab_id }">
                <option value="">&mdash; Select Lab &mdash;</option>
                <option v-for="lab in labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option>
              </select>
              <div v-if="logOutErrors.recipient_lab_id" class="field-error">{{ logOutErrors.recipient_lab_id[0] }}</div>
            </div>
            <div class="form-group" :class="showInvRecipientLab ? 'half-width' : 'full-width'">
              <label>Recipient Name</label>
              <input type="text" v-model="logOutForm.recipient_name" placeholder="e.g. M. Irfan" class="form-input" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Recipient Role</label>
              <input type="text" v-model="logOutForm.recipient_role" placeholder="e.g. Lab Manager" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Asset Ref / Dispatch Ref</label>
              <input type="text" v-model="logOutForm.asset_ref" placeholder="e.g. DR/26/0012" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea v-model="logOutForm.remarks" placeholder="e.g. Beyond economic repair — Board survey completed" class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button v-write="'add_asset_logs'" class="btn btn-pri" type="button" :disabled="logOutSaving" @click="saveLogOut">
            <span class="btn-icon">💾</span> {{ logOutSaving ? 'Saving…' : 'Save' }}
          </button>
          <button class="btn btn-sec outline" type="button" :disabled="logOutSaving" @click="showLogOutModal = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ New Item Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showNewItemModal" @click.self="showNewItemModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>+ Add Stock Item</h3>
          <button class="close-btn" type="button" @click="showNewItemModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">New item will be added to the Stock Register under the selected category.</p>

          <div class="form-group full-width">
            <label>Item Name <span class="req">*</span></label>
            <input type="text" v-model="newItemForm.name" placeholder="e.g. Manganese Reagent Kit" class="form-input focus-bold" :class="{ 'has-error': newItemErrors.name }" />
            <div v-if="newItemErrors.name" class="field-error">{{ newItemErrors.name[0] }}</div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Category <span class="req">*</span></label>
              <select v-model="newItemForm.category" class="form-select">
                <option value="">&mdash; Select Category &mdash;</option>
                <option value="chem">🧪 Chemical Reagents</option>
                <option value="micro">🔴 Microbiological Media &amp; Reagents</option>
                <option value="glass">🧫 Glassware &amp; Plasticware</option>
                <option value="safety">🖐 Protective &amp; Safety Supplies</option>
                <option value="other">📦 Other</option>
              </select>
            </div>
            <div class="form-group half-width">
              <label>Unit <span class="req">*</span></label>
              <select v-model="newItemForm.unit" class="form-select">
                <option value="Kit">Kit</option>
                <option value="Box">Box</option>
                <option value="Pack">Pack</option>
                <option value="Bottle">Bottle</option>
                <option value="Litre">Litre</option>
                <option value="Pcs">Pcs</option>
                <option value="Set">Set</option>
                <option value="Roll">Roll</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Opening Balance <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" v-model="newItemForm.opening_balance" placeholder="e.g. 10" class="form-input" :class="{ 'has-error': newItemErrors.quantity }" />
              <div v-if="newItemErrors.quantity" class="field-error">{{ newItemErrors.quantity[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Min. Threshold <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" v-model="newItemForm.min_threshold" placeholder="e.g. 5" class="form-input" :class="{ 'has-error': newItemErrors.threshold }" />
              <div v-if="newItemErrors.threshold" class="field-error">{{ newItemErrors.threshold[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Expiry Date <span class="req">*</span></label>
              <input type="date" v-model="newItemForm.expiry_date" class="form-input" :class="{ 'has-error': newItemErrors.date_of_expiry }" />
              <div v-if="newItemErrors.date_of_expiry" class="field-error">{{ newItemErrors.date_of_expiry[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Supplier</label>
              <input type="text" v-model="newItemForm.supplier" placeholder="e.g. Merck Pakistan" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Remarks (optional)</label>
            <textarea v-model="newItemForm.remarks" placeholder="Storage conditions, notes..." class="form-textarea" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button v-write="'add_material'" class="btn btn-pri" type="button" :disabled="newItemSaving" @click="saveNewItem">
            <span class="btn-icon">💾</span> {{ newItemSaving ? 'Saving…' : 'Save Item' }}
          </button>
          <button class="btn btn-sec outline" type="button" :disabled="newItemSaving" @click="showNewItemModal = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Per-row Actions menu (Teleported) ═══════════════════════ -->
    <Teleport to="body">
      <template v-if="actionMenuOpenFor !== null && actionMenuItem">
        <div class="action-menu-backdrop" @click="closeActionMenu"></div>
        <div class="action-menu floating-menu"
             :style="{
               top:    actionMenuPos.top    != null ? actionMenuPos.top    + 'px' : 'auto',
               bottom: actionMenuPos.bottom != null ? actionMenuPos.bottom + 'px' : 'auto',
               left:   actionMenuPos.left + 'px',
               width:  MENU_WIDTH + 'px'
             }"
             @click.stop>
          <!-- Trail / Edit Item are stock-only (they read from material logs and
               post to the material endpoints). Inventory rows currently only
               support Raise Demand; equipment edit lives on Equipment Register. -->
          <div v-if="actionMenuItem.materialId" class="action-menu-item" @click="openTrail(actionMenuItem)">
            <span class="ami-icon">📋</span> Trail
          </div>
          <div class="action-menu-item"
               @click="openRaiseDemand(actionMenuItem, actionMenuItem.assetId ? 'inventory' : 'stock')">
            <span class="ami-icon ami-amber">⚡</span> Raise Demand
          </div>
          <div v-if="actionMenuItem.materialId" class="action-menu-item" @click="openEditItem(actionMenuItem)">
            <span class="ami-icon">✎</span> Edit Item
          </div>
        </div>
      </template>
    </Teleport>

    <!-- ═══════════════════════ Trail Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showTrailModal" @click.self="showTrailModal = false">
      <div class="modal-content trail-modal">
        <div class="modal-header">
          <h3><span class="trail-icon">📋</span> Trail &mdash; {{ trailItem?.name }}</h3>
          <button class="close-btn" type="button" @click="showTrailModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="trail-subtitle">
            Consumable · Unit: {{ trailItem?.unit }} · Current Balance: <b>{{ trailItem?.qty }}</b>
          </p>

          <div class="trail-summary">
            <div class="ts-card ts-green">
              <div class="ts-lbl">TOTAL IN</div>
              <div class="ts-val ts-green-val">+{{ trailTotalIn }}</div>
            </div>
            <div class="ts-card ts-red">
              <div class="ts-lbl">TOTAL OUT</div>
              <div class="ts-val ts-red-val">{{ trailTotalOut }}</div>
            </div>
            <div class="ts-card ts-blue">
              <div class="ts-lbl">TRANSACTIONS</div>
              <div class="ts-val ts-blue-val">{{ trailTxnCount }}</div>
            </div>
            <div class="ts-card ts-green">
              <div class="ts-lbl">BALANCE</div>
              <div class="ts-val ts-green-val">{{ trailItem?.qty }}</div>
            </div>
          </div>

          <div class="trail-filters">
            <select v-model="trailTypeFilter" class="form-select select-w-sm">
              <option value="">All Types</option>
              <option value="IN">IN</option>
              <option value="OUT">OUT</option>
            </select>
            <input type="date" v-model="trailDateFrom" class="form-input date-input" />
            <input type="date" v-model="trailDateTo" class="form-input date-input" />
            <div class="search-input-wrapper">
              <span class="search-icon">🔍</span>
              <input type="text" v-model="trailSearch" placeholder="Txn ID / Party..." class="form-input search-input" />
            </div>
            <button class="btn btn-sec outline" type="button" @click="clearTrailFilters">✕ Clear</button>
          </div>

          <div class="trail-table-wrap">
            <table class="table trail-table">
              <thead>
                <tr>
                  <th style="text-align: left;">Txn ID</th>
                  <th style="text-align: left;">Date</th>
                  <th style="text-align: center;">Type</th>
                  <th style="text-align: center;">Qty</th>
                  <th style="text-align: left;">Party / Ref.</th>
                  <th style="text-align: left;">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in filteredTrailRows" :key="r.id" class="item-row">
                  <td class="txn-id-cell">{{ r.txnId }}</td>
                  <td>{{ r.date }}</td>
                  <td style="text-align: center;">
                    <span class="type-badge" :class="r.type === 'IN' ? 'tb-in' : 'tb-out'">{{ r.type }}</span>
                  </td>
                  <td style="text-align: center;">
                    <b :class="r.type === 'IN' ? 'qty-in' : 'qty-out'">{{ r.qty >= 0 ? '+' + r.qty : r.qty }}</b>
                  </td>
                  <td class="party-cell">{{ r.party }}</td>
                  <td class="muted">{{ r.remarks }}</td>
                </tr>
                <tr v-if="!filteredTrailRows.length">
                  <td colspan="6" class="text-center empty-row">No transactions match these filters.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer justify-end">
          <button class="btn btn-sec outline" type="button" @click="showTrailModal = false">Close</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Raise Demand Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showRaiseDemandModal" @click.self="showRaiseDemandModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3><span class="demand-icon">⚡</span> Raise Demand</h3>
          <button class="close-btn" type="button" @click="showRaiseDemandModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Demand will be submitted to Central Lab (Peshawar) for approval.</p>

          <div class="form-group full-width">
            <label>Item</label>
            <input type="text" :value="`${raiseDemandItem?.name || ''} (${raiseDemandItem?.name || ''})`" class="form-input readonly-input" readonly />
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Current Balance</label>
              <input type="text" :value="`${raiseDemandItem?.qty ?? ''} ${raiseDemandItem?.unit || ''}`" class="form-input readonly-input" readonly />
            </div>
            <div class="form-group half-width">
              <label>Qty Requested <span class="req">*</span></label>
              <input type="number" step="1" min="1" v-model="raiseDemandForm.quantity" placeholder="e.g. 10" class="form-input" :class="{ 'has-error': raiseDemandErrors.quantity }" />
              <div v-if="raiseDemandErrors.quantity" class="field-error">{{ raiseDemandErrors.quantity[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Urgency <span class="req">*</span></label>
              <select v-model="raiseDemandForm.urgency" class="form-select" :class="{ 'has-error': raiseDemandErrors.urgency }">
                <option value="routine">Routine</option>
                <option value="urgent">Urgent</option>
              </select>
              <div v-if="raiseDemandErrors.urgency" class="field-error">{{ raiseDemandErrors.urgency[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Required By Date</label>
              <input type="date" v-model="raiseDemandForm.required_by" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Justification / Remarks</label>
            <input type="text" v-model="raiseDemandForm.justification" placeholder="e.g. Stock depleted, upcoming field campaign..." class="form-input" />
          </div>
        </div>
        <div class="modal-footer justify-end">
          <button v-write class="btn btn-sec outline" type="button" :disabled="raiseDemandSaving" @click="showRaiseDemandModal = false">Cancel</button>
          <button v-write="'add_inventories'" class="btn btn-pri" type="button" :disabled="raiseDemandSaving" @click="submitRaiseDemand">
            <span class="btn-icon">📨</span> {{ raiseDemandSaving ? 'Submitting…' : 'Submit Demand' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Edit Item Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showEditItemModal" @click.self="showEditItemModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>✎ Edit Stock Item</h3>
          <button class="close-btn" type="button" @click="showEditItemModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Updates the master catalog entry. Only system administrators can edit.</p>

          <div class="form-group full-width">
            <label>Item Name <span class="req">*</span></label>
            <input type="text" v-model="editItemForm.name" class="form-input focus-bold" :class="{ 'has-error': editItemErrors.name }" />
            <div v-if="editItemErrors.name" class="field-error">{{ editItemErrors.name[0] }}</div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Category</label>
              <select v-model="editItemForm.category" class="form-select">
                <option value="">&mdash; Select Category &mdash;</option>
                <option value="chem">🧪 Chemical Reagents</option>
                <option value="micro">🔴 Microbiological Media &amp; Reagents</option>
                <option value="glass">🧫 Glassware &amp; Plasticware</option>
                <option value="safety">🖐 Protective &amp; Safety Supplies</option>
                <option value="other">📦 Other</option>
              </select>
            </div>
            <div class="form-group half-width">
              <label>Unit <span class="req">*</span></label>
              <select v-model="editItemForm.unit" class="form-select">
                <option value="Kit">Kit</option>
                <option value="Box">Box</option>
                <option value="Pack">Pack</option>
                <option value="Bottle">Bottle</option>
                <option value="Litre">Litre</option>
                <option value="Pcs">Pcs</option>
                <option value="Set">Set</option>
                <option value="Roll">Roll</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Available Qty <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" v-model="editItemForm.available_quantity" class="form-input" :class="{ 'has-error': editItemErrors.quantity }" />
              <div v-if="editItemErrors.quantity" class="field-error">{{ editItemErrors.quantity[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Min. Threshold <span class="req">*</span></label>
              <input type="number" step="0.01" min="0" v-model="editItemForm.threshold" class="form-input" :class="{ 'has-error': editItemErrors.threshold }" />
              <div v-if="editItemErrors.threshold" class="field-error">{{ editItemErrors.threshold[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Expiry Date</label>
              <input type="date" v-model="editItemForm.date_of_expiry" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Supplier</label>
              <input type="text" v-model="editItemForm.supplier" class="form-input" />
            </div>
          </div>
        </div>
        <div class="modal-footer justify-end">
          <button v-write class="btn btn-sec outline" type="button" :disabled="editItemSaving" @click="showEditItemModal = false">Cancel</button>
          <button v-write="'edit_material'" class="btn btn-pri" type="button" :disabled="editItemSaving" @click="saveEditItem">
            <span class="btn-icon">💾</span> {{ editItemSaving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Toast ═══════════════════════ -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
        <span class="toast-msg">{{ toast.message }}</span>
        <button class="toast-close" type="button" @click="toast.show = false">✕</button>
      </div>
    </transition>

    <!-- ═══════════════════════ Existing Item Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showExistingItemModal" @click.self="showExistingItemModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>📥 Add Qty — Existing Stock Item</h3>
          <button class="close-btn" type="button" @click="showExistingItemModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Adds quantity to an item already in the Stock Register. A SI/YY/### transaction is logged automatically.</p>

          <div class="form-group full-width">
            <label>Item <span class="req">*</span></label>
            <select v-model="existingItemForm.item_id" class="form-select">
              <option value="">— Select Item —</option>
              <option v-for="item in stockItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.unit }})</option>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Qty to Add <span class="req">*</span></label>
              <input type="number" v-model="existingItemForm.qty_to_add" placeholder="e.g. 10" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Date <span class="req">*</span></label>
              <input type="date" v-model="existingItemForm.date" class="form-input" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Source / Supplier</label>
              <input type="text" v-model="existingItemForm.supplier" placeholder="e.g. Merck Pak, Local Purchase" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>New Expiry Date</label>
              <input type="date" v-model="existingItemForm.new_expiry_date" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Invoice / Ref. No.</label>
            <input type="text" v-model="existingItemForm.invoice_ref" placeholder="e.g. PO/26/0041" class="form-input" />
          </div>

          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea v-model="existingItemForm.remarks" placeholder="Optional notes" class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer justify-end">
          <button class="btn btn-sec outline" type="button" @click="showExistingItemModal = false">Cancel</button>
          <button v-write="['inventory_received','edit_inventory_approve_status']" class="btn btn-pri" type="button" @click="saveExistingQty">💾 Save</button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════ Log Issue Modal ═══════════════════════ -->
    <div class="modal-overlay" v-if="showLogIssueModal" @click.self="showLogIssueModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>🔴 Log Stock-Out Issue</h3>
          <button class="close-btn" type="button" @click="showLogIssueModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Records consumption / issuance of a stock item. A SO/YY/### transaction is logged automatically.</p>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Item <span class="req">*</span></label>
              <select v-model="logIssueForm.item_id" class="form-select" :class="{ 'has-error': logIssueErrors.material_id }">
                <option value="">&mdash; Select Item &mdash;</option>
                <option v-for="item in stockItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.unit }})</option>
              </select>
              <div v-if="logIssueErrors.material_id" class="field-error">{{ logIssueErrors.material_id[0] }}</div>
            </div>
            <div class="form-group half-width">
              <label>Qty Out <span class="req">*</span></label>
              <input type="number" step="1" min="1" v-model="logIssueForm.qty" placeholder="e.g. 3" class="form-input" :class="{ 'has-error': logIssueErrors.quantity }" />
              <div v-if="logIssueErrors.quantity" class="field-error">{{ logIssueErrors.quantity[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Date</label>
              <input type="date" v-model="logIssueForm.date" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Type <span class="req">*</span></label>
              <select v-model="logIssueForm.type" class="form-select" :class="{ 'has-error': logIssueErrors.type }">
                <option value="analysis">Analysis</option>
                <option value="write_off">Write-off</option>
                <option value="transfer">Transfer</option>
                <option value="calibration">Calibration</option>
              </select>
              <div v-if="logIssueErrors.type" class="field-error">{{ logIssueErrors.type[0] }}</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width" v-if="showRecipientLab">
              <label>Recipient Lab <span class="req">*</span></label>
              <select v-model="logIssueForm.recipient_lab_id" class="form-select" :class="{ 'has-error': logIssueErrors.recipient_lab_id }">
                <option value="">&mdash; Select Lab &mdash;</option>
                <option v-for="lab in labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option>
              </select>
              <div v-if="logIssueErrors.recipient_lab_id" class="field-error">{{ logIssueErrors.recipient_lab_id[0] }}</div>
            </div>
            <div class="form-group" :class="showRecipientLab ? 'half-width' : 'full-width'">
              <label>Recipient Name</label>
              <input type="text" v-model="logIssueForm.recipient_name" placeholder="e.g. M. Irfan" class="form-input" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group half-width">
              <label>Recipient Role</label>
              <input type="text" v-model="logIssueForm.recipient_role" placeholder="e.g. Analyst" class="form-input" />
            </div>
            <div class="form-group half-width">
              <label>Sample ID / Ref</label>
              <input type="text" v-model="logIssueForm.sample_ref" placeholder="e.g. MI/26/001" class="form-input" />
            </div>
          </div>

          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea v-model="logIssueForm.remarks" placeholder="Optional notes" class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button v-write="'add_material_logs'" class="btn btn-pri" type="button" :disabled="logIssueSaving" @click="saveLogIssue">
            <span class="btn-icon">💾</span> {{ logIssueSaving ? 'Saving…' : 'Save' }}
          </button>
          <button class="btn btn-sec outline" type="button" :disabled="logIssueSaving" @click="showLogIssueModal = false">Cancel</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style lang="scss" scoped src="./StockInventory.scss"></style>

<style lang="scss">
/* Non-scoped: the action menu is <Teleport>'ed to <body> so it cannot inherit
   this component's scoped styles. These rules are global by design. */
.action-menu.floating-menu {
  position: fixed;
  z-index: 2100;
  padding: 4px 0;
  border: 1px solid #c8d5e2;
  border-radius: 5px;
  background: #fff;
  box-shadow: 0 10px 18px rgba(16, 36, 64, .14);
  font-family: 'Inter', sans-serif;

  .action-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    color: #253247;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    white-space: nowrap;

    &:hover { background: #eef4fb; }
  }

  .ami-icon {
    width: 16px;
    font-size: 13px;
    line-height: 1;
    text-align: center;
  }

  .ami-amber { color: #f59e0b; }
}

.action-menu-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
}
</style>

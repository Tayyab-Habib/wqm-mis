<script setup>
import { ref, computed, onMounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

const activeTab = ref('consumables')
const activeSubTab = ref('register')
const searchText = ref('')
const catFilter = ref('')
const ragFilter = ref('')
const loading = ref(false)
const errorMsg = ref('')

// Dropdown and Modals state
const showAddMenu = ref(false)
const showNewItemModal = ref(false)
const showExistingItemModal = ref(false)

const newItemForm = ref({
  name: '',
  category: '',
  unit: 'Kit',
  opening_balance: 0,
  min_threshold: '',
  expiry_date: '',
  supplier: '',
  remarks: ''
})

const existingItemForm = ref({
  item_id: '',
  qty_to_add: '',
  date: '',
  supplier: '',
  new_expiry_date: '',
  invoice_ref: '',
  remarks: ''
})

const stockItems = ref([])
const inventoryItems = ref([])

function deriveCategory(name = '') {
  const n = String(name).toLowerCase()
  if (n.includes('coliform') || n.includes('macconkey') || n.includes('broth') || n.includes('media') || n.includes('agar') || n.includes('micro')) return 'micro'
  if (n.includes('bottle') || n.includes('glass') || n.includes('membrane') || n.includes('filter') || n.includes('flask') || n.includes('beaker') || n.includes('pipette')) return 'glass'
  if (n.includes('glove') || n.includes('coat') || n.includes('mask') || n.includes('goggle') || n.includes('safety') || n.includes('protective')) return 'safety'
  return 'chem'
}

function statusToRag(status, qty, threshold) {
  const normalized = String(status || '').toLowerCase()
  if (normalized === 'expired') return { rag: 'Expired', ragClass: 'r-red' }
  if (normalized === 'depleted' || Number(qty) <= 0) return { rag: 'Critical', ragClass: 'r-red' }
  if (normalized === 'below_threshold' || (Number(threshold) > 0 && Number(qty) < Number(threshold))) return { rag: 'Depleting', ragClass: 'r-amber' }
  return { rag: 'Adequate', ragClass: 'r-green' }
}

function formatExpiry(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const day = String(date.getDate()).padStart(2, '0')
  const month = date.toLocaleString('en-US', { month: 'short' })
  const year = String(date.getFullYear()).slice(-2)
  return `${day}-${month}-${year}`
}

function mapMaterial(m) {
  const logs = m.material_logs || m.laboratory_material_logs || []
  const qty = parseFloat(m.available_quantity ?? m.quantity ?? 0)
  const earliestExpiry = logs.reduce((earliest, log) => {
    const value = log.date_of_expiry || log.expiry_date
    if (!value) return earliest
    return !earliest || new Date(value) < new Date(earliest) ? value : earliest
  }, m.date_of_expiry || m.expiry_date || null)
  const expiryDate = earliestExpiry ? new Date(earliestExpiry) : null
  const expWarn = expiryDate && !Number.isNaN(expiryDate.getTime()) && expiryDate < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
  const expCrit = expiryDate && !Number.isNaN(expiryDate.getTime()) && expiryDate < new Date()
  const { rag, ragClass } = statusToRag(m.status, qty, m.threshold)

  return {
    id: m.id,
    cat: m.category || m.type || deriveCategory(m.name || m.material?.name),
    name: m.name || m.material?.name || '-',
    unit: m.unit || '-',
    qty,
    batches: `${logs.length || 1} ${logs.length === 1 ? 'batch' : 'batches'}`,
    expiry: formatExpiry(earliestExpiry),
    expWarn,
    expCrit,
    rag,
    ragClass,
  }
}

async function loadData() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [matRes, assetRes] = await Promise.all([
      assetService.getMaterials(),
      assetService.getLaboratoryAssets(),
    ])
    
    // For visual match with screenshot, fallback to static if empty
    const matData = matRes.data?.data || matRes.data || []
    stockItems.value = Array.isArray(matData) ? matData.map(mapMaterial) : []
    /*
      stockItems.value = matData.map(m => {
        const qty = parseFloat(m.quantity || m.qty || 0)
        let rag = 'Adequate', ragClass = 'r-green'
        if (qty === 0) { rag = 'Critical'; ragClass = 'r-red' }
        else if (qty <= 3) { rag = 'Depleting'; ragClass = 'r-amber' }
        return {
          id: m.id,
          cat: m.category || m.type || 'chem',
          name: m.name || '—',
          unit: m.unit || '—',
          qty,
          batches: '1 batch',
          expiry: m.expiry_date || m.expiry || '—',
          rag: m.status || rag,
          ragClass: m.status === 'Expired' ? 'r-red' : ragClass,
          expWarn: false
        }
      })
    } else {
      stockItems.value = rawItems
    }
    
    */
    inventoryItems.value = assetRes.data?.data || assetRes.data || []
  } catch (e) {
    console.error(e)
    errorMsg.value = 'Failed to load stock inventory.'
    stockItems.value = []
    inventoryItems.value = []
  } finally {
    loading.value = false
  }
}

const filteredItems = computed(() => {
  return stockItems.value.filter(item => {
    const matchSearch = !searchText.value || item.name.toLowerCase().includes(searchText.value.toLowerCase())
    const matchCat = !catFilter.value || item.cat === catFilter.value
    const matchRag = !ragFilter.value || item.rag === ragFilter.value
    return matchSearch && matchCat && matchRag
  })
})

const groupedItems = computed(() => {
  const groups = {}
  filteredItems.value.forEach(item => {
    if (!groups[item.cat]) groups[item.cat] = []
    groups[item.cat].push(item)
  })
  return groups
})

const catOrder = ['chem', 'micro', 'glass', 'safety']
const catLabels = { chem: '🧪 CHEMICAL REAGENTS', micro: '🔴 MICROBIOLOGICAL MEDIA & REAGENTS', glass: '🧫 GLASSWARE & PLASTICWARE', safety: '🧤 PROTECTIVE & SAFETY SUPPLIES' }

function categoryLabel(cat) {
  return {
    chem: 'CHEMICAL REAGENTS',
    micro: 'MICROBIOLOGICAL MEDIA & REAGENTS',
    glass: 'GLASSWARE & PLASTICWARE',
    safety: 'PROTECTIVE & SAFETY SUPPLIES',
  }[cat] || cat
}

const summaryAdequate = computed(() => stockItems.value.filter(i => i.rag === 'Adequate').length)
const summaryDepleting = computed(() => stockItems.value.filter(i => i.rag === 'Depleting').length)
const summaryCritical = computed(() => stockItems.value.filter(i => i.rag === 'Critical' || i.qty === 0).length)
const summaryExpired = computed(() => stockItems.value.filter(i => i.rag === 'Expired').length)

const invServiceable = computed(() => inventoryItems.value.filter(i => i.status === 'Serviceable').length)
const invAttention = computed(() => inventoryItems.value.filter(i => i.status === 'Attention').length)
const invCondemned = computed(() => inventoryItems.value.filter(i => i.status === 'Condemned').length)
const invMissing = computed(() => inventoryItems.value.filter(i => i.status === 'Missing').length)

function openNewItemModal() {
  showAddMenu.value = false
  showNewItemModal.value = true
}

function openExistingItemModal() {
  showAddMenu.value = false
  showExistingItemModal.value = true
}

onMounted(loadData)
</script>

<template>
  <div class="asset-management-container">
    <!-- Main Tabs -->
    <div class="main-tabs">
      <div class="tab" :class="{ active: activeTab === 'consumables' }" @click="activeTab = 'consumables'">
        <span class="tab-icon">📦</span> Stock (Consumables)
      </div>
      <div class="tab" :class="{ active: activeTab === 'inventory' }" @click="activeTab = 'inventory'">
        <span class="tab-icon">🗄</span> Inventory (Non-consumables)
      </div>
    </div>

    <!-- Consumables Tab -->
    <div v-if="activeTab === 'consumables'" class="tab-content">
      
      <!-- Sub-Tabs -->
      <div class="sub-tabs">
        <div class="sub-tab" :class="{ active: activeSubTab === 'register' }" @click="activeSubTab = 'register'">
          <span class="icon">📋</span> Register
        </div>
        <div class="sub-tab" :class="{ active: activeSubTab === 'stock-out' }" @click="activeSubTab = 'stock-out'">
          <span class="icon">🔴</span> Stock Out
        </div>
      </div>

      <div v-if="activeSubTab === 'register'">
        
        <!-- Summary cards -->
        <div class="cards summary-cards">
          <div class="card card-blue">
            <div class="c-lbl">TOTAL ITEMS</div>
            <div class="c-val c-blue-val">{{ stockItems.length }}</div>
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

        <div style="height:20px"></div>

        <!-- Toolbar -->
        <div class="toolbar">
          <select v-model="catFilter" class="form-select select-w">
            <option value="">All Categories</option>
            <option value="chem">Chemical Reagents</option>
            <option value="micro">Microbiological Media &amp; Reagents</option>
            <option value="glass">Glassware &amp; Plasticware</option>
            <option value="safety">Protective &amp; Safety Supplies</option>
          </select>
          <select v-model="ragFilter" class="form-select select-w-sm">
            <option value="">All RAG</option>
            <option>Adequate</option>
            <option>Depleting</option>
            <option>Critical</option>
            <option>Expired</option>
          </select>
          <div class="search-input-wrapper">
             <span class="search-icon">🔍</span>
             <input type="text" v-model="searchText" placeholder="Item name..." class="form-input search-input">
          </div>
          
          <div class="tsp"></div>
          
          <!-- Add Item Dropdown Button -->
          <div class="dropdown-wrapper">
            <button class="btn btn-pri add-btn" @click="showAddMenu = !showAddMenu">
              + Add Item <span class="chevron">▼</span>
            </button>
            <div class="dropdown-menu" v-if="showAddMenu">
              <div class="dropdown-item" @click="openNewItemModal">📄 New Item</div>
              <div class="dropdown-item" @click="openExistingItemModal">📦 Existing Item</div>
            </div>
            <!-- backdrop for easy close -->
            <div class="dropdown-backdrop" v-if="showAddMenu" @click="showAddMenu = false"></div>
          </div>
        </div>

        <!-- Table -->
        <div class="tbl-wrap">
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
              <template v-for="cat in catOrder" :key="cat">
                <template v-if="groupedItems[cat] && groupedItems[cat].length > 0">
                  <tr class="category-header-row">
                    <td colspan="9">
                      <span class="cat-title"><span class="cat-marker"></span>{{ categoryLabel(cat) }}</span>
                    </td>
                  </tr>
                  <tr v-for="(item, idx) in groupedItems[cat]" :key="item.name" class="item-row">
                    <td style="text-align: center;">
                      <button class="btn-expand">▶</button>
                    </td>
                    <td style="text-align: center; color: #334155;">{{ idx + 1 }}</td>
                    <td style="text-align: left;">
                      <b style="color: #0f172a; font-weight: 700;">{{ item.name }}</b>
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
                      <span v-if="item.expWarn || item.expCrit" class="warn-icon">⚠</span>
                    </td>
                    <td style="text-align: center;">
                      <span class="badge" :class="item.ragClass">{{ item.rag }}</span>
                    </td>
                    <td style="text-align: center;">
                      <button class="btn-action" :class="{'btn-action-red': item.rag === 'Critical'}">
                        ⋮ Actions
                      </button>
                    </td>
                  </tr>
                </template>
              </template>
              <tr v-if="!stockItems.length && !loading">
                 <td colspan="9" class="text-center empty-state">No stock items found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <div v-else class="stock-out-view">
        <div class="empty-state">
          Stock out records will be displayed here.
        </div>
      </div>
    </div>

    <!-- Inventory placeholder -->
    <div v-if="activeTab === 'inventory'" class="tab-content">
      <div class="cards summary-cards">
        <div class="card card-blue"><div class="c-lbl">TOTAL ITEMS</div><div class="c-val c-blue-val">{{ inventoryItems.length }}</div></div>
        <div class="card card-green"><div class="c-lbl">SERVICEABLE</div><div class="c-val c-green-val">{{ invServiceable }}</div></div>
        <div class="card card-amber"><div class="c-lbl">NEEDS ATTENTION</div><div class="c-val c-amber-val">{{ invAttention }}</div></div>
        <div class="card card-red"><div class="c-lbl">CONDEMNED</div><div class="c-val c-red-val">{{ invCondemned }}</div></div>
        <div class="card card-red"><div class="c-lbl">MISSING / LOST</div><div class="c-val c-red-val">{{ invMissing }}</div></div>
      </div>
      <div class="empty-state mt-3">🗄 Inventory register loaded from backend.</div>
    </div>

    <!-- New Item Modal -->
    <div class="modal-overlay" v-if="showNewItemModal" @click.self="showNewItemModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>+ Add Stock Item</h3>
          <button class="close-btn" @click="showNewItemModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">New item will be added to the Stock Register under the selected category.</p>
          
          <div class="form-group full-width">
            <label>Item Name <span class="req">*</span></label>
            <input type="text" v-model="newItemForm.name" placeholder="e.g. Manganese Reagent Kit" class="form-input focus-bold">
          </div>
          
          <div class="form-row">
            <div class="form-group half-width">
              <label>Category <span class="req">*</span></label>
              <select v-model="newItemForm.category" class="form-select">
                <option value="">— Select Category —</option>
                <option value="chem">Chemical Reagents</option>
                <option value="micro">Microbiological Media &amp; Reagents</option>
                <option value="glass">Glassware &amp; Plasticware</option>
                <option value="safety">Protective &amp; Safety Supplies</option>
              </select>
            </div>
            <div class="form-group half-width">
              <label>Unit <span class="req">*</span></label>
              <select v-model="newItemForm.unit" class="form-select">
                <option value="Kit">Kit</option>
                <option value="Bottle">Bottle</option>
                <option value="Litre">Litre</option>
                <option value="Pack">Pack</option>
                <option value="Box">Box</option>
              </select>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group half-width">
              <label>Opening Balance <span class="req">*</span></label>
              <input type="number" v-model="newItemForm.opening_balance" class="form-input">
            </div>
            <div class="form-group half-width">
              <label>Min. Threshold <span class="req">*</span></label>
              <input type="number" v-model="newItemForm.min_threshold" placeholder="e.g. 5" class="form-input">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group half-width">
              <label>Expiry Date (if applicable)</label>
              <input type="date" v-model="newItemForm.expiry_date" class="form-input">
            </div>
            <div class="form-group half-width">
              <label>Supplier</label>
              <input type="text" v-model="newItemForm.supplier" placeholder="e.g. Merck Pakistan" class="form-input">
            </div>
          </div>
          
          <div class="form-group full-width">
            <label>Remarks (optional)</label>
            <textarea v-model="newItemForm.remarks" placeholder="Storage conditions, notes..." class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-pri" @click="showNewItemModal = false">
            <span class="icon">💾</span> Save Item
          </button>
          <button class="btn btn-sec outline" @click="showNewItemModal = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Existing Item Modal -->
    <div class="modal-overlay" v-if="showExistingItemModal" @click.self="showExistingItemModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>📥 Add Qty — Existing Stock Item</h3>
          <button class="close-btn" @click="showExistingItemModal = false">✕</button>
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
              <input type="number" v-model="existingItemForm.qty_to_add" placeholder="e.g. 10" class="form-input">
            </div>
            <div class="form-group half-width">
              <label>Date <span class="req">*</span></label>
              <input type="date" v-model="existingItemForm.date" class="form-input">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group half-width">
              <label>Source / Supplier</label>
              <input type="text" v-model="existingItemForm.supplier" placeholder="e.g. Merck Pak, Local Purchase" class="form-input">
            </div>
            <div class="form-group half-width">
              <label>New Expiry Date</label>
              <input type="date" v-model="existingItemForm.new_expiry_date" class="form-input">
            </div>
          </div>
          
          <div class="form-group full-width">
            <label>Invoice / Ref. No.</label>
            <input type="text" v-model="existingItemForm.invoice_ref" placeholder="e.g. PO/26/0041" class="form-input">
          </div>
          
          <div class="form-group full-width">
            <label>Remarks</label>
            <textarea v-model="existingItemForm.remarks" placeholder="Optional notes" class="form-textarea" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer justify-end">
          <button class="btn btn-sec outline" @click="showExistingItemModal = false">Cancel</button>
          <button class="btn btn-pri" @click="showExistingItemModal = false">
            <span class="icon">💾</span> Save
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style lang="scss" scoped src="./StockInventory.scss"></style>

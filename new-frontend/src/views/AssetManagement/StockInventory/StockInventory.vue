<script setup>
import { ref, computed, onMounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

const activeTab  = ref('consumables')
const searchText = ref('')
const catFilter  = ref('')
const ragFilter  = ref('')
const loading    = ref(false)
const errorMsg   = ref('')

const stockItems     = ref([])
const inventoryItems = ref([])

function computeRag(item) {
  const qty = parseFloat(item.quantity || item.qty || 0)
  if (qty === 0) return { rag: 'Critical', ragClass: 'r-red' }
  if (qty <= 3)  return { rag: 'Depleting', ragClass: 'r-amber' }
  return { rag: 'Adequate', ragClass: 'r-green' }
}

function mapMaterial(m) {
  const { rag, ragClass } = computeRag(m)
  const expiry = m.expiry_date || m.expiry || '—'
  const expWarn = expiry !== '—' && new Date(expiry) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
  return {
    id: m.id,
    cat: m.category || m.type || 'chem',
    name: m.name || '—',
    unit: m.unit || '—',
    qty: parseFloat(m.quantity || m.qty || 0),
    expiry,
    rag: m.status || rag,
    ragClass: m.status === 'Expired' ? 'r-red' : ragClass,
    expWarn,
  }
}

function mapAsset(a) {
  return {
    id: a.id,
    cat: a.category || 'instrument',
    name: a.name || '—',
    model: a.model || '—',
    purchased: a.purchased_at ? a.purchased_at.split(' ')[0] : '—',
    qty: a.quantity || 1,
    location: a.location || '—',
    condition: a.condition || '—',
    status: a.status || 'Serviceable',
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
    const matData   = matRes.data?.data   || matRes.data   || []
    const assetData = assetRes.data?.data || assetRes.data || []
    stockItems.value     = matData.map(mapMaterial)
    inventoryItems.value = assetData.map(mapAsset)
  } catch (e) {
    errorMsg.value = 'Failed to load stock data'
    console.error('Stock load error:', e)
  } finally {
    loading.value = false
  }
}

const filteredItems = computed(() => {
  return stockItems.value.filter(item => {
    const matchSearch = !searchText.value || item.name.toLowerCase().includes(searchText.value.toLowerCase())
    const matchCat    = !catFilter.value  || item.cat === catFilter.value
    const matchRag    = !ragFilter.value  || item.rag === ragFilter.value
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

const catOrder  = ['chem', 'micro', 'glass', 'safety']
const catLabels = { chem:'🧪 Chemical Reagents', micro:'🔴 Microbiological Media & Reagents', glass:'🧫 Glassware & Plasticware', safety:'🧤 Protective & Safety Supplies' }

// Summary counts
const summaryAdequate  = computed(() => stockItems.value.filter(i => i.rag === 'Adequate').length)
const summaryDepleting = computed(() => stockItems.value.filter(i => i.rag === 'Depleting').length)
const summaryCritical  = computed(() => stockItems.value.filter(i => i.rag === 'Critical' || i.qty === 0).length)
const summaryExpired   = computed(() => stockItems.value.filter(i => i.rag === 'Expired').length)

const invServiceable = computed(() => inventoryItems.value.filter(i => i.status === 'Serviceable').length)
const invAttention   = computed(() => inventoryItems.value.filter(i => i.status === 'Attention').length)
const invCondemned   = computed(() => inventoryItems.value.filter(i => i.status === 'Condemned').length)
const invMissing     = computed(() => inventoryItems.value.filter(i => i.status === 'Missing').length)

onMounted(loadData)
</script>

<template>
  <div>
    <!-- Tabs -->
    <div class="tabs">
      <div class="tab" :class="{ active: activeTab === 'consumables' }" @click="activeTab = 'consumables'">📦 Stock (Consumables)</div>
      <div class="tab" :class="{ active: activeTab === 'inventory' }"   @click="activeTab = 'inventory'">🗄 Inventory (Non-consumables)</div>
    </div>

    <!-- Consumables -->
    <div v-if="activeTab === 'consumables'">
      <!-- Summary cards -->
      <div class="cards" style="grid-template-columns:repeat(5,1fr)">
        <div class="card"><div class="c-lbl">Total Items</div><div class="c-val">{{ stockItems.length }}</div></div>
        <div class="card c-green"><div class="c-lbl">Adequate</div><div class="c-val">{{ summaryAdequate }}</div></div>
        <div class="card c-amber"><div class="c-lbl">Depleting</div><div class="c-val">{{ summaryDepleting }}</div></div>
        <div class="card c-red"><div class="c-lbl">Critical / Zero</div><div class="c-val">{{ summaryCritical }}</div></div>
        <div class="card c-red"><div class="c-lbl">Expired</div><div class="c-val">{{ summaryExpired }}</div></div>
      </div>

      <div style="height:8px"></div>

      <!-- Toolbar -->
      <div class="toolbar">
        <select v-model="catFilter">
          <option value="">All Categories</option>
          <option value="chem">🧪 Chemical Reagents</option>
          <option value="micro">🔴 Microbiological Media &amp; Reagents</option>
          <option value="glass">🧫 Glassware &amp; Plasticware</option>
          <option value="safety">🧤 Protective &amp; Safety Supplies</option>
        </select>
        <select v-model="ragFilter">
          <option value="">All RAG</option>
          <option>Adequate</option>
          <option>Depleting</option>
          <option>Critical</option>
          <option>Expired</option>
        </select>
        <input type="text" v-model="searchText" placeholder="🔍 Item name…">
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm">+ Add Item</button>
      </div>

      <!-- Table -->
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>S#</th>
              <th>Item Name</th>
              <th>Unit</th>
              <th>Total Qty</th>
              <th>Earliest Expiry</th>
              <th>RAG</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="cat in catOrder" :key="cat">
              <template v-if="groupedItems[cat]">
                <tr :style="{ background: cat==='chem'?'#e8f0fe':cat==='micro'?'#fce4ec':cat==='glass'?'#e8f5e9':'#fff8e1' }">
                  <td colspan="7" style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:5px 10px">
                    {{ catLabels[cat] }}
                  </td>
                </tr>
                <tr v-for="(item, idx) in groupedItems[cat]" :key="item.name" :class="idx % 2 === 1 ? 'alt' : ''">
                  <td>{{ idx + 1 }}</td>
                  <td><b>{{ item.name }}</b></td>
                  <td>{{ item.unit }}</td>
                  <td class="mono" :style="item.qty === 0 ? 'color:var(--red);font-weight:700' : ''"><b>{{ item.qty }}</b></td>
                  <td :style="item.expWarn ? 'color:var(--amber);font-weight:600' : ''">
                    {{ item.expiry }} <span v-if="item.expWarn">⚠</span>
                  </td>
                  <td><span class="rag" :class="item.ragClass">{{ item.rag }}</span></td>
                  <td>
                    <button class="btn btn-sec btn-xs">⋮ Actions</button>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
        <div class="tbl-footer">▶ FIFO order — oldest batch consumed first.</div>
      </div>
    </div>

    <!-- Inventory placeholder -->
    <div v-if="activeTab === 'inventory'">
      <div class="cards" style="grid-template-columns:repeat(5,1fr)">
        <div class="card"><div class="c-lbl">Total Items</div><div class="c-val">{{ inventoryItems.length }}</div></div>
        <div class="card c-green"><div class="c-lbl">Serviceable</div><div class="c-val">{{ invServiceable }}</div></div>
        <div class="card c-amber"><div class="c-lbl">Needs Attention</div><div class="c-val">{{ invAttention }}</div></div>
        <div class="card c-red"><div class="c-lbl">Condemned</div><div class="c-val">{{ invCondemned }}</div></div>
        <div class="card c-red"><div class="c-lbl">Missing / Lost</div><div class="c-val">{{ invMissing }}</div></div>
      </div>
      <div class="abar blue">🗄 Inventory register loaded from backend.</div>
    </div>
  </div>
</template>

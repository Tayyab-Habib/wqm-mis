<script setup>
import { ref, computed, onMounted } from 'vue'
import { waterSchemeService } from '../../../services/waterSchemeService.js'
import { dropdownService } from '../../../services/dropdownService.js'

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const filters    = ref({ from_date:'', to_date:'', division_id:'', result:'' })
const mapPins    = ref([])

function mapWss(w) {
  return {
    id: w.id,
    wss: w.name || '—',
    district: w.district?.name || '—',
    result: w.last_sample_result || 'Untested',
    lat: parseFloat(w.latitude) || null,
    lng: parseFloat(w.longitude) || null,
    top:  w.latitude  ? `${Math.max(5, Math.min(90, (35.5 - parseFloat(w.latitude)) * 10 + 30))}%` : '50%',
    left: w.longitude ? `${Math.max(5, Math.min(90, (parseFloat(w.longitude) - 69) * 8 + 20))}%` : '50%',
  }
}

async function loadDropdowns() {
  try {
    const res = await dropdownService.getDivisions()
    divisions.value = res.data || []
  } catch (e) { console.error('Dropdown error:', e) }
}

async function loadMap() {
  loading.value = true
  errorMsg.value = ''
  try {
    const payload = {}
    if (filters.value.division_id) payload.division_id = filters.value.division_id
    if (filters.value.result)      payload.result      = filters.value.result
    const res  = await waterSchemeService.getAll(payload)
    const data = res.data?.data || res.data || []
    mapPins.value = data.filter(w => w.latitude && w.longitude).map(mapWss)
  } catch (e) {
    errorMsg.value = 'Failed to load map data'
    console.error('WSS Map error:', e)
  } finally {
    loading.value = false
  }
}

const fitCount      = computed(() => mapPins.value.filter(p => p.result === 'Fit').length)
const unfitCount    = computed(() => mapPins.value.filter(p => p.result === 'Unfit').length)
const untestedCount = computed(() => mapPins.value.filter(p => p.result === 'Untested').length)

const hoveredPin = ref(null)

onMounted(async () => {
  await loadDropdowns()
  await loadMap()
})
</script>

<template>
  <div>
    <div class="filters" style="margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>
      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id">
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
          <option value="Untested">Untested</option>
        </select>
      </div>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="loadMap" :disabled="loading">{{ loading ? '🔄…' : '🔍 Load Map' }}</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="abar blue">
      🗾 Water Sample Map (R-07) — Green pin = Fit · Red pin = Unfit · Grey = Untested &nbsp;|&nbsp; Coordinate-based placement
    </div>

    <div class="cards cards-4" style="margin-bottom:14px">
      <div class="card"><div class="c-lbl">Total WSS Plotted</div><div class="c-val">{{ mapPins.length }}</div></div>
      <div class="card c-green"><div class="c-lbl">Fit</div><div class="c-val">{{ fitCount }}</div></div>
      <div class="card c-red"><div class="c-lbl">Unfit</div><div class="c-val">{{ unfitCount }}</div></div>
      <div class="card"><div class="c-lbl">Untested</div><div class="c-val">{{ untestedCount }}</div></div>
    </div>

    <!-- Map placeholder -->
    <div style="background:#e8f5e9;border:1px solid var(--border);border-radius:6px;height:400px;display:flex;align-items:center;justify-content:center;color:var(--muted);position:relative;overflow:hidden">
      <div style="text-align:center;z-index:1">
        <div style="font-size:32px;margin-bottom:8px">🗺️</div>
        <div style="font-size:13px;font-weight:500">Google Maps Integration</div>
        <div style="font-size:11.5px;margin-top:4px">Color-coded pins: 🟢 Fit &nbsp; 🔴 Unfit &nbsp; ⚪ Untested</div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">KP Province · {{ mapPins.length }} WSS plotted</div>
      </div>

      <div v-for="pin in mapPins" :key="pin.id"
           :style="{ position:'absolute', top:pin.top, left:pin.left, fontSize:'18px', cursor:'pointer', zIndex:2 }"
           @mouseenter="hoveredPin = pin"
           @mouseleave="hoveredPin = null">
        {{ pin.result === 'Fit' ? '🟢' : pin.result === 'Unfit' ? '🔴' : '⚪' }}
      </div>

      <div v-if="hoveredPin"
           style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);background:rgba(13,33,55,.93);color:#fff;border-radius:6px;padding:8px 14px;font-size:11.5px;pointer-events:none;z-index:10;white-space:nowrap">
        <b>{{ hoveredPin.wss }}</b> — {{ hoveredPin.district }}<br>
        <span :style="hoveredPin.result==='Fit'?'color:#4ade80':hoveredPin.result==='Unfit'?'color:#f87171':'color:#aaa'">
          {{ hoveredPin.result === 'Fit' ? '✅ Fit' : hoveredPin.result === 'Unfit' ? '❌ Unfit' : '⚪ Untested' }}
        </span>
      </div>

      <div style="position:absolute;bottom:10px;right:10px;background:rgba(255,255,255,.9);border-radius:4px;padding:5px 10px;font-size:11px;box-shadow:0 1px 6px rgba(0,0,0,.12)">
        <span>🟢 Fit ({{ fitCount }})</span>
        <span style="margin-left:10px">🔴 Unfit ({{ unfitCount }})</span>
        <span style="margin-left:10px">⚪ Untested ({{ untestedCount }})</span>
      </div>
    </div>

    <div style="margin-top:14px">
      <div class="sh"><h2>Plotted WSS</h2></div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>WSS Name</th><th>District</th><th>Latitude</th><th>Longitude</th><th>Result</th></tr>
          </thead>
          <tbody>
            <tr v-if="!mapPins.length">
              <td colspan="5" style="text-align:center;padding:20px;color:var(--muted)">No WSS with coordinates found.</td>
            </tr>
            <tr v-for="(pin, i) in mapPins" :key="pin.id" :class="i%2===1?'alt':''">
              <td>{{ pin.wss }}</td>
              <td>{{ pin.district }}</td>
              <td class="mono">{{ pin.lat }}</td>
              <td class="mono">{{ pin.lng }}</td>
              <td><span class="rag" :class="pin.result==='Fit'?'r-green':pin.result==='Unfit'?'r-red':'r-grey'">{{ pin.result }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

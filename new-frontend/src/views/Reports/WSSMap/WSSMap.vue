<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { waterSchemeService } from '../../../services/waterSchemeService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const loading      = ref(false)
const errorMsg     = ref('')
const divisions    = ref([])
const phedDivs     = ref([])
const districts    = ref([])
const filters      = ref({ from_date: '', to_date: '', division_id: '', phed_division_id: '', district_id: '', result: '' })
const mapPins      = ref([])
const counts       = ref({ total: 0, fit: 0, unfit: 0, untested: 0 })
const hoveredPin   = ref(null)

// ── Leaflet refs ────────────────────────────────────────────────────
const mapContainer = ref(null)
let leafletMap = null
let markerLayer = null

// Centre on KP province by default
const KP_CENTER = [34.0, 71.5]
const KP_ZOOM = 7

function colorFor(result) {
  if (result === 'Fit') return '#16a34a'
  if (result === 'Unfit') return '#dc2626'
  return '#9ca3af'
}

function buildIcon(result) {
  const color = colorFor(result)
  const html = `<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>`
  return L.divIcon({ html, className: 'wss-pin-icon', iconSize: [18, 18], iconAnchor: [9, 9] })
}

function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }
  catch { return d }
}

function popupHtml(pin) {
  const resultColor = colorFor(pin.result)
  return `
    <div style="font-family:'DM Sans',sans-serif;font-size:12px;min-width:200px">
      <div style="font-weight:700;color:#0f172a;margin-bottom:4px">${pin.wss}</div>
      <div style="color:#64748b;margin-bottom:6px">📍 ${pin.district} · ${pin.phedDivision || '—'}</div>
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${resultColor}"></span>
        <b style="color:${resultColor}">${pin.result}</b>
      </div>
      <div style="font-size:11px;color:#64748b">Last sampled: ${fmtDate(pin.lastSampledAt)}</div>
      <div style="font-size:10.5px;color:#9ca3af;font-family:monospace;margin-top:5px">${pin.lat.toFixed(5)}, ${pin.lng.toFixed(5)}</div>
    </div>`
}

// ── API calls ────────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [divs, pdivs, dists] = await Promise.all([
      dropdownService.getDivisions().catch(() => ({ data: [] })),
      dropdownService.getPhedDivisions().catch(() => ({ data: [] })),
      dropdownService.getDistricts().catch(() => ({ data: [] })),
    ])
    divisions.value = divs.data?.data || divs.data || []
    phedDivs.value  = pdivs.data?.data || pdivs.data || []
    districts.value = dists.data?.data || dists.data || []
  } catch (e) { console.error('Dropdown error:', e) }
}

function mapPin(w) {
  return {
    id:        w.id,
    wss:       w.name || '—',
    district:  w.district?.name || '—',
    phedDivision: w.phed_division?.name || '—',
    lat:       w.latitude,
    lng:       w.longitude,
    result:    w.last_sample_result || 'Untested',
    lastSampledAt: w.last_sampled_at,
  }
}

async function loadMap() {
  loading.value = true
  errorMsg.value = ''
  try {
    const payload = {}
    if (filters.value.from_date)        payload.from_date        = filters.value.from_date
    if (filters.value.to_date)          payload.to_date          = filters.value.to_date
    if (filters.value.division_id)      payload.division_id      = filters.value.division_id
    if (filters.value.phed_division_id) payload.phed_division_id = filters.value.phed_division_id
    if (filters.value.district_id)      payload.district_id      = filters.value.district_id
    if (filters.value.result)           payload.result           = filters.value.result

    const res = await waterSchemeService.getMap(payload)
    // axios response interceptor already unwraps response.data, so `res` IS the body
    const rows = res?.data || []
    mapPins.value = rows.map(mapPin)
    counts.value  = res?.counts || {
      total:    mapPins.value.length,
      fit:      mapPins.value.filter(p => p.result === 'Fit').length,
      unfit:    mapPins.value.filter(p => p.result === 'Unfit').length,
      untested: mapPins.value.filter(p => p.result === 'Untested').length,
    }
    renderMarkers()
  } catch (e) {
    errorMsg.value = 'Failed to load map data: ' + (e?.response?.data?.message || e?.message || 'Unknown error')
    console.error('WSS Map error:', e)
  } finally {
    loading.value = false
  }
}

// ── Map render ───────────────────────────────────────────────────────
function initMap() {
  if (leafletMap || !mapContainer.value) return
  leafletMap = L.map(mapContainer.value, {
    center: KP_CENTER,
    zoom: KP_ZOOM,
    scrollWheelZoom: true,
  })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(leafletMap)
  markerLayer = L.layerGroup().addTo(leafletMap)
}

function renderMarkers() {
  if (!leafletMap) return
  markerLayer.clearLayers()
  const valid = []
  mapPins.value.forEach(pin => {
    if (typeof pin.lat !== 'number' || typeof pin.lng !== 'number') return
    const m = L.marker([pin.lat, pin.lng], { icon: buildIcon(pin.result) })
    m.bindPopup(popupHtml(pin), { closeButton: true })
    m.on('mouseover', () => { hoveredPin.value = pin })
    m.on('mouseout', () => { hoveredPin.value = null })
    m.addTo(markerLayer)
    valid.push([pin.lat, pin.lng])
  })
  if (valid.length) {
    leafletMap.fitBounds(L.latLngBounds(valid), { padding: [40, 40], maxZoom: 12 })
  }
}

function resetFilters() {
  filters.value = { from_date: '', to_date: '', division_id: '', phed_division_id: '', district_id: '', result: '' }
  loadMap()
}

function exportCsv() {
  if (!mapPins.value.length) return
  const head = ['WSS Name', 'District', 'PHE Division', 'Latitude', 'Longitude', 'Last Result', 'Last Sampled']
  const rows = mapPins.value.map(p => [p.wss, p.district, p.phedDivision, p.lat, p.lng, p.result, fmtDate(p.lastSampledAt)])
  const csv = [head, ...rows].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `wss_map_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}

onMounted(async () => {
  await loadDropdowns()
  await nextTick()
  initMap()
  await loadMap()
})

onBeforeUnmount(() => {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    markerLayer = null
  }
})

// Keep map height responsive on dropdown filter toggle etc.
watch(() => mapContainer.value, () => { if (leafletMap) leafletMap.invalidateSize() })
</script>

<template>
  <div>
    <div class="filters" style="margin-bottom:10px;flex-wrap:wrap">
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
        <label>PHE Division</label>
        <select v-model="filters.phed_division_id">
          <option value="">All PHE Divisions</option>
          <option v-for="d in phedDivs" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
          <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
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
      <button class="btn btn-pri btn-sm" @click="loadMap" :disabled="loading">{{ loading ? '🔄 Loading…' : '🔍 Apply' }}</button>
      <button class="btn btn-sec btn-sm" @click="resetFilters" :disabled="loading">↻ Reset</button>
      <button class="btn btn-sec btn-sm" @click="exportCsv" :disabled="!mapPins.length">⬇ Export</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="abar blue">
      🗺 Water Sample Map (R-07) — 🟢 Fit · 🔴 Unfit · ⚪ Untested. Click a marker for details. Map data © OpenStreetMap contributors.
    </div>

    <div class="cards cards-4" style="margin-bottom:14px">
      <div class="card"><div class="c-lbl">Total WSS Plotted</div><div class="c-val">{{ counts.total }}</div></div>
      <div class="card c-green"><div class="c-lbl">Fit</div><div class="c-val">{{ counts.fit }}</div></div>
      <div class="card c-red"><div class="c-lbl">Unfit</div><div class="c-val">{{ counts.unfit }}</div></div>
      <div class="card"><div class="c-lbl">Untested</div><div class="c-val">{{ counts.untested }}</div></div>
    </div>

    <!-- Real Leaflet map -->
    <div class="wss-map-wrap">
      <div ref="mapContainer" class="wss-map-canvas"></div>

      <div v-if="loading" class="wss-map-overlay">
        <div>⏳ Loading WSS data…</div>
      </div>

      <div v-if="!loading && !mapPins.length" class="wss-map-overlay">
        <div>No WSS with coordinates match these filters.</div>
      </div>

      <div class="wss-map-legend">
        <span><span class="dot dot-green"></span> Fit ({{ counts.fit }})</span>
        <span><span class="dot dot-red"></span> Unfit ({{ counts.unfit }})</span>
        <span><span class="dot dot-grey"></span> Untested ({{ counts.untested }})</span>
      </div>
    </div>

    <div style="margin-top:14px">
      <div class="sh"><h2>Plotted WSS</h2></div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>WSS Name</th>
              <th>District</th>
              <th>PHE Division</th>
              <th>Latitude</th>
              <th>Longitude</th>
              <th>Last Result</th>
              <th>Last Sampled</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!mapPins.length">
              <td colspan="7" style="text-align:center;padding:20px;color:var(--muted)">No WSS with coordinates found.</td>
            </tr>
            <tr v-for="(pin, i) in mapPins" :key="pin.id" :class="i % 2 === 1 ? 'alt' : ''">
              <td><b>{{ pin.wss }}</b></td>
              <td>{{ pin.district }}</td>
              <td>{{ pin.phedDivision }}</td>
              <td class="mono">{{ pin.lat }}</td>
              <td class="mono">{{ pin.lng }}</td>
              <td>
                <span class="rag"
                      :class="pin.result === 'Fit' ? 'r-green' : pin.result === 'Unfit' ? 'r-red' : 'r-grey'">
                  {{ pin.result }}
                </span>
              </td>
              <td>{{ fmtDate(pin.lastSampledAt) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.wss-map-wrap {
  position: relative;
  border: 1px solid #d0d7e0;
  border-radius: 6px;
  overflow: hidden;
  height: 460px;
  background: #f0f3f6;
}
.wss-map-canvas {
  width: 100%;
  height: 100%;
}
.wss-map-overlay {
  position: absolute;
  top: 12px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(15, 23, 42, .82);
  color: #fff;
  padding: 8px 14px;
  border-radius: 6px;
  font-size: 12px;
  z-index: 500;
  pointer-events: none;
}
.wss-map-legend {
  position: absolute;
  bottom: 12px;
  right: 12px;
  background: rgba(255, 255, 255, .95);
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  padding: 7px 12px;
  font-size: 11.5px;
  font-weight: 500;
  display: flex;
  gap: 14px;
  z-index: 500;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .12);
  .dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 4px;
    vertical-align: middle;
  }
  .dot-green { background: #16a34a; }
  .dot-red   { background: #dc2626; }
  .dot-grey  { background: #9ca3af; }
}
</style>

<style>
/* Leaflet's default close-button gets nicer hover */
.leaflet-container { font-family: 'DM Sans', sans-serif; }
.wss-pin-icon { background: transparent; border: none; }
</style>

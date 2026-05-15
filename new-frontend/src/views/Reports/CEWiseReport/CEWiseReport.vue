<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRbac } from '../../../composables/useRbac.js'
import { reportService }   from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX }    from '../../../utils/exportHelpers.js'
import { useUserStore }    from '../../../stores/useUserStore.js'

const userStore = useUserStore()
const loading   = ref(false)
const errorMsg  = ref('')
const generated = ref(false)

// ── Dropdowns ──────────────────────────────────────────────────────────
const regions   = ref([])
const divisions = ref([])
const districts = ref([])
const circles   = ref([])  // needed to bridge region→division (divisions.region_id is NULL in DB)

// ── Filters ────────────────────────────────────────────────────────────
const filters = ref({
  from_date:   '',   // no default — load all data on first visit
  to_date:     '',
  region_id:   '',
  division_id: '',
  district_id: '',
})

// Cascaded dropdowns. divisions.region_id is properly backfilled per the
// xlsx hierarchy now, so Division filters directly off d.region_id.
const filteredDivisions = computed(() =>
  filters.value.region_id
    ? divisions.value.filter(d => String(d.region_id) === String(filters.value.region_id))
    : divisions.value
)

const filteredDistricts = computed(() =>
  filters.value.division_id
    ? districts.value.filter(d => String(d.division_id) === String(filters.value.division_id))
    : districts.value
)

// ── Data ───────────────────────────────────────────────────────────────
const ceSummary      = ref([])
const districtDetail = ref([])
const provinceTotals = ref({ total: 0, fit: 0, unfit: 0, districts_count: 0, divisions_count: 0 })
const generatedAt    = ref('')

// ── Load dropdowns ─────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [regRes, divRes, distRes, cirRes] = await Promise.all([
      dropdownService.getRegions(),
      dropdownService.getDivisions(),
      dropdownService.getDistricts(),
      dropdownService.getCircles(),
    ])
    regions.value   = regRes.data?.data  || regRes.data  || []
    divisions.value = divRes.data?.data  || divRes.data  || []
    districts.value = distRes.data?.data || distRes.data || []
    circles.value   = cirRes.data?.data  || cirRes.data  || []
  } catch (e) { console.error('Dropdown error:', e) }
}

// ── Client-side date validation ────────────────────────────────────────
const dateError = computed(() => {
  const f = filters.value.from_date
  const t = filters.value.to_date
  if (f && t && f > t) return 'From date must be on or before To date.'
  return ''
})

// Request sequence — drop stale responses if a newer request fires mid-flight
let requestSeq = 0

// ── Generate ───────────────────────────────────────────────────────────
async function generateReport() {
  if (dateError.value) {
    errorMsg.value  = dateError.value
    generated.value = false
    loading.value   = false
    return
  }

  const mySeq = ++requestSeq
  loading.value   = true
  errorMsg.value  = ''
  try {
    const payload = {}
    if (filters.value.from_date)   payload.from_date   = filters.value.from_date
    if (filters.value.to_date)     payload.to_date     = filters.value.to_date
    if (filters.value.region_id)   payload.region_id   = filters.value.region_id
    if (filters.value.division_id) payload.division_id = filters.value.division_id
    if (filters.value.district_id) payload.district_id = filters.value.district_id

    const res = await reportService.getCEWiseReport(payload)
    if (mySeq !== requestSeq) return    // newer request superseded this one

    // axios interceptor returns body directly (not under res.data)
    const body = res.ce_summary ? res : (res.data || res)
    ceSummary.value      = body.ce_summary      || []
    districtDetail.value = body.district_detail || []
    provinceTotals.value = body.province_totals || { total: 0, fit: 0, unfit: 0, districts_count: 0, divisions_count: 0 }
    generatedAt.value    = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    generated.value = true
  } catch (e) {
    if (mySeq !== requestSeq) return
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message)
    console.error('CE-wise error:', e)
  } finally {
    if (mySeq === requestSeq) loading.value = false
  }
}

// ── Computed helpers ───────────────────────────────────────────────────
const provinceFitPct = computed(() => {
  const t = provinceTotals.value.total
  return t > 0 ? ((provinceTotals.value.fit / t) * 100).toFixed(1) + '%' : '—'
})
const provinceUnfitPct = computed(() => {
  const t = provinceTotals.value.total
  return t > 0 ? ((provinceTotals.value.unfit / t) * 100).toFixed(1) + '%' : '—'
})

// Unique CE names for grouping district detail
const uniqueCEs = computed(() => [...new Set(districtDetail.value.map(r => r.region_name))])

function districtsByCE(ceName) {
  return districtDetail.value.filter(r => r.region_name === ceName)
}

function ceSummaryFor(ceName) {
  return ceSummary.value.find(r => r.region_name === ceName) || {}
}

// ── RAG helpers ────────────────────────────────────────────────────────
function ragClass(total, unfit) {
  if (!total) return 'r-grey'
  const ratio = unfit / total
  if (ratio > 0.2) return 'r-red'
  if (ratio > 0.1) return 'r-amber'
  return 'r-green'
}
function ragLabel(total, unfit) {
  if (!total) return '—'
  const ratio = unfit / total
  if (ratio > 0.2) return 'Critical'
  if (ratio > 0.1) return 'Caution'
  return 'Satisfactory'
}
function remark(total, unfit) {
  if (!total) return '—'
  const ratio = unfit / total
  if (ratio > 0.2) return 'Immediate attention required'
  if (ratio > 0.1) return 'Monitor closely'
  return 'Satisfactory performance'
}
function pctFit(total, fit) {
  return total > 0 ? ((fit / total) * 100).toFixed(1) + '%' : '0.0%'
}

// Province RAG
const provinceRag = computed(() => ragClass(provinceTotals.value.total, provinceTotals.value.unfit))
const provinceRagLabel = computed(() => ragLabel(provinceTotals.value.total, provinceTotals.value.unfit))

// ── Report period label ────────────────────────────────────────────────
const reportPeriod = computed(() => {
  const from = filters.value.from_date
  const to   = filters.value.to_date
  if (!from && !to) return 'All Time'
  const fmt = (d) => d ? new Date(d).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—'
  return `${fmt(from)} – ${fmt(to)}`
})

const reportRegion = computed(() => {
  if (!filters.value.region_id) return 'All CE Zones'
  return regions.value.find(r => r.id == filters.value.region_id)?.name || 'All CE Zones'
})

const generatedBy = computed(() => userStore.currentUser?.name || 'System')

// ── Export ─────────────────────────────────────────────────────────────
function exportReport() {
  if (!ceSummary.value.length) { alert('No data to export.'); return }

  // Sheet 1 — CE Summary
  exportToXLSX(ceSummary.value.map(r => ({
    'Chief Engineer':  r.region_name,
    'Districts':       r.districts_count,
    'Divisions':       r.divisions_count,
    'Total':           r.total,
    'Fit':             r.fit,
    'Unfit':           r.unfit,
    '% Fit':           pctFit(r.total, r.fit),
    'RAG Status':      ragLabel(r.total, r.unfit),
  })), 'CEWise_Annexure7_CE_Summary')

  // Sheet 2 — District Detail
  setTimeout(() => {
    let sn = 1
    exportToXLSX(districtDetail.value.map(r => ({
      'S#':       sn++,
      'CE Zone':  r.region_name,
      'District': r.district_name,
      'Division': r.division_name,
      'Total':    r.total,
      'Fit':      r.fit,
      'Unfit':    r.unfit,
      '% Fit':    pctFit(r.total, r.fit),
      'RAG':      ragLabel(r.total, r.unfit),
      'Remarks':  remark(r.total, r.unfit),
    })), 'CEWise_Annexure7_District_Detail')
  }, 400)
}

function printReport() { window.print() }

function clearFilters() {
  filters.value = {
    from_date:   '',
    to_date:     '',
    region_id:   '',
    division_id: '',
    district_id: '',
  }
}

// Auto-refresh on filter change (debounced)
let filterTimer = null
watch(filters, () => {
  if (!generated.value) return
  if (dateError.value) return       // skip while date range is invalid
  clearTimeout(filterTimer)
  filterTimer = setTimeout(generateReport, 350)
}, { deep: true })

// RBAC: pre-select + lock filters at the user's hierarchy scope.
// CE-Wise only has region/division/district selectors.
const rbac = useRbac()
function applyRbacLocks() {
  if (rbac.regionId.value) filters.value.region_id = String(rbac.regionId.value)
  // No circle_id field on this form; SE just sees their region-level data
  // (circle scoping still happens at the backend via AuthScope).
}

onMounted(async () => {
  await loadDropdowns()
  applyRbacLocks()
  await generateReport()
})
</script>

<template>
  <div class="cewise-page">
    <!-- ── Filters ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>CE Zone</label>
        <select v-model="filters.region_id" :disabled="!!rbac.regionId.value" @change="filters.division_id='';filters.district_id=''">
          <option value="">All CE Zones</option>
          <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id" @change="filters.district_id=''">
          <option value="">All Divisions</option>
          <option v-for="d in filteredDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="clearFilters">✕ Clear Filters</button>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="exportReport">↓ Export .xlsx</button>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="printReport">Print PDF</button>
    </div>

    <!-- Inline date range warning -->
    <div v-if="dateError"
         style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:10px;color:#854d0e;font-size:12px">
      ⚠ {{ dateError }}
    </div>

    <!-- Error -->
    <div v-if="errorMsg && !dateError"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      {{ errorMsg }}
    </div>

    <!-- Skeleton loader -->
    <div v-if="loading" class="ce-sk">
      <div class="sk sk-banner"></div>
      <div class="sk-cards">
        <div class="sk-card" v-for="i in 4" :key="'sc'+i">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
      </div>
      <div class="sk-tbl">
        <div class="sk-tbl-head"><div class="sk sk-th" v-for="i in 8" :key="'th'+i"></div></div>
        <div class="sk-tbl-row" v-for="r in 6" :key="'tr'+r"><div class="sk sk-td" v-for="i in 8" :key="'td'+r+'-'+i"></div></div>
      </div>
    </div>

    <template v-if="generated && !loading">
      <!-- ── Report banner ── -->
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 16px;margin-bottom:12px;font-size:12px;display:flex;align-items:center;gap:8px">
        <span style="font-size:14px">📊</span>
        <span style="font-weight:700;color:#1e40af">
          Chief Engineer-wise Water Quality Report &nbsp;|&nbsp; {{ reportPeriod }} &nbsp;|&nbsp; Annexure-7
        </span>
        <span style="color:#6b7280;font-size:11px;margin-left:auto">
          Generated by <b>{{ generatedBy }}</b> on {{ generatedAt }}
        </span>
      </div>

      <!-- ── Stat cards ── -->
      <div class="cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:14px">
        <div class="card">
          <div class="c-lbl">TOTAL TESTED</div>
          <div class="c-val">{{ provinceTotals.total.toLocaleString() }}</div>
        </div>
        <div class="card c-green">
          <div class="c-lbl">FIT SAMPLES</div>
          <div class="c-val">{{ provinceTotals.fit.toLocaleString() }}</div>
          <div class="c-sub">{{ provinceFitPct }}</div>
        </div>
        <div class="card c-red">
          <div class="c-lbl">UNFIT SAMPLES</div>
          <div class="c-val">{{ provinceTotals.unfit.toLocaleString() }}</div>
          <div class="c-sub">{{ provinceUnfitPct }}</div>
        </div>
        <div class="card">
          <div class="c-lbl">DISTRICTS COVERED</div>
          <div class="c-val">{{ provinceTotals.districts_count }}</div>
        </div>
      </div>

      <!-- ── CE Summary Table ── -->
      <div class="tbl-wrap" style="margin-bottom:18px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">Chief Engineer</th>
              <th style="color:#fff;text-align:center">Districts</th>
              <th style="color:#fff;text-align:center">Divisions</th>
              <th style="color:#fff;text-align:center">Total</th>
              <th style="color:#fff;text-align:center">Fit</th>
              <th style="color:#fff;text-align:center">Unfit</th>
              <th style="color:#fff;text-align:center">% Fit</th>
              <th style="color:#fff;text-align:center">RAG Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ceSummary.length">
              <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">No data found.</td>
            </tr>
            <tr v-for="(r, i) in ceSummary" :key="r.region_id" :class="i%2===1?'alt':''">
              <td style="font-weight:600">{{ r.region_name }}</td>
              <td class="mono" style="text-align:center">{{ r.districts_count }}</td>
              <td class="mono" style="text-align:center">{{ r.divisions_count }}</td>
              <td class="mono" style="text-align:center;font-weight:600">{{ r.total.toLocaleString() }}</td>
              <td class="mono" style="text-align:center">{{ r.fit.toLocaleString() }}</td>
              <td class="mono" style="text-align:center">{{ r.unfit.toLocaleString() }}</td>
              <td class="mono" style="text-align:center">{{ pctFit(r.total, r.fit) }}</td>
              <td style="text-align:center">
                <span class="rag" :class="ragClass(r.total, r.unfit)">{{ ragLabel(r.total, r.unfit) }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="background:var(--navy2);font-weight:700">
              <td style="color:#fff">PROVINCE TOTAL</td>
              <td class="mono" style="color:#fff;text-align:center">{{ provinceTotals.districts_count }}</td>
              <td class="mono" style="color:#fff;text-align:center">{{ provinceTotals.divisions_count }}</td>
              <td class="mono" style="color:#fff;text-align:center;font-weight:700">{{ provinceTotals.total.toLocaleString() }}</td>
              <td class="mono" style="color:#fff;text-align:center">{{ provinceTotals.fit.toLocaleString() }}</td>
              <td class="mono" style="color:#fff;text-align:center">{{ provinceTotals.unfit.toLocaleString() }}</td>
              <td class="mono" style="color:#fff;text-align:center">{{ provinceFitPct }}</td>
              <td style="text-align:center">
                <span class="rag" :class="provinceRag">{{ provinceRagLabel }}</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ── CE-wise District Detail ── -->
      <div class="sh" style="margin-bottom:8px;display:flex;align-items:center;gap:8px">
        <h2>CE-wise District Detail</h2>
        <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-weight:600">Annexure-7 · Sheet 2</span>
      </div>

      <div class="tbl-wrap">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff;width:40px">S#</th>
              <th style="color:#fff">District</th>
              <th style="color:#fff">Division</th>
              <th style="color:#fff;text-align:center">Total</th>
              <th style="color:#fff;text-align:center">Fit</th>
              <th style="color:#fff;text-align:center">Unfit</th>
              <th style="color:#fff;text-align:center">% Fit</th>
              <th style="color:#fff;text-align:center">RAG</th>
              <th style="color:#fff">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!districtDetail.length">
              <td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">No district data found.</td>
            </tr>

            <template v-for="ceName in uniqueCEs" :key="ceName">
              <!-- CE group header -->
              <tr style="background:#e8f0fe;border-top:2px solid #93c5fd">
                <td colspan="9"
                    style="font-size:11px;font-weight:700;color:#1e3a8a;padding:5px 12px">
                  {{ ceName }}
                  <span style="font-weight:400;color:#6b7280;margin-left:8px">
                    — {{ ceSummaryFor(ceName).districts_count || districtsByCE(ceName).length }} Districts
                    | Total: {{ (ceSummaryFor(ceName).total || 0).toLocaleString() }}
                    | Fit: {{ (ceSummaryFor(ceName).fit || 0).toLocaleString() }}
                    | Unfit: {{ (ceSummaryFor(ceName).unfit || 0).toLocaleString() }}
                    | {{ pctFit(ceSummaryFor(ceName).total || 0, ceSummaryFor(ceName).fit || 0) }}
                  </span>
                </td>
              </tr>

              <!-- District rows for this CE -->
              <tr v-for="(d, i) in districtsByCE(ceName)" :key="d.district_id"
                  :class="i%2===1?'alt':''">
                <td class="mono" style="color:var(--muted);text-align:center;font-size:11px">{{ i + 1 }}</td>
                <td>{{ d.district_name }}</td>
                <td style="font-size:11px">{{ d.division_name }}</td>
                <td class="mono" style="text-align:center">{{ d.total.toLocaleString() }}</td>
                <td class="mono" style="text-align:center">{{ d.fit.toLocaleString() }}</td>
                <td class="mono" style="text-align:center"
                    :style="d.unfit > 0 ? 'color:var(--red);font-weight:600' : ''">
                  {{ d.unfit.toLocaleString() }}
                </td>
                <td class="mono" style="text-align:center">{{ pctFit(d.total, d.fit) }}</td>
                <td style="text-align:center">
                  <span class="rag" :class="ragClass(d.total, d.unfit)">
                    {{ ragLabel(d.total, d.unfit) }}
                  </span>
                </td>
                <td style="font-size:11px;color:var(--muted)">{{ remark(d.total, d.unfit) }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Legend -->
      <div style="font-size:10.5px;color:var(--muted);margin-top:10px;line-height:1.9">
        <b>RAG:</b>
        <span class="rag r-green" style="margin:0 4px">Satisfactory</span> ≤10% unfit &nbsp;·&nbsp;
        <span class="rag r-amber" style="margin:0 4px">Caution</span> 10–20% unfit &nbsp;·&nbsp;
        <span class="rag r-red" style="margin:0 4px">Critical</span> &gt;20% unfit
      </div>
    </template>
  </div>
</template>

<style>
/* Crisp-text override scoped to CE-wise view: defeats global td.mono rule
   (DM Mono 11.5px → fuzzy on Windows). */
.cewise-page td.mono {
  font-family: 'DM Sans', sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 12.5px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* ── Skeleton loader ─────────────────────────────────────────────────── */
.ce-sk .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: ce-sk-shimmer 1.4s infinite linear;
}
@keyframes ce-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.ce-sk .sk-banner { height: 44px; margin-bottom: 12px; }
.ce-sk .sk-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 14px;
}
.ce-sk .sk-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ce-sk .sk-lbl { height: 10px; width: 60%; }
.ce-sk .sk-val { height: 22px; width: 45%; }
.ce-sk .sk-tbl { border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
.ce-sk .sk-tbl-head, .ce-sk .sk-tbl-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 1fr 1.2fr;
  gap: 8px;
  padding: 9px 12px;
}
.ce-sk .sk-tbl-head { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.ce-sk .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.ce-sk .sk-th, .ce-sk .sk-td { height: 12px; }

@media print {
  .filters, .btn, nav, aside, .ce-sk { display: none !important; }
  .tbl-wrap, .tbl-wrap *, .cards, .cards *, .sh, .sh * { visibility: visible; }
  body { font-size: 10px; }
}
</style>

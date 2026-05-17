<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', circle_id: '', district_id: '', result: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q)           params.q           = filters.value.q
    if (filters.value.circle_id)   params.circle_id   = filters.value.circle_id
    if (filters.value.district_id) params.district_id = filters.value.district_id
    if (filters.value.result)      params.result      = filters.value.result
    data.value = await ceService.wssRegister(params)
  } catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const grouped = computed(() => {
  const rows = data.value?.rows || []
  const groups = {}
  for (const r of rows) {
    const key = `${r.circle_id || 0}::${r.district || 'Unknown'}`
    if (!groups[key]) groups[key] = { circle_id: r.circle_id, circle: data.value?.circles?.find(c => c.id === r.circle_id)?.name || '—', district: r.district, items: [] }
    groups[key].items.push(r)
  }
  return Object.values(groups)
})

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'p-green'
  if (r === 'unfit') return 'p-red'
  return 'p-grey'
}

function exportCsv() {
  const rows = data.value?.rows || []
  if (!rows.length) return
  const head = ['WSS Code', 'WSS Name', 'District', 'SE Circle', 'Source', 'Times Tested', 'Last Result', 'Last Sampled', 'Next Scheduled']
  const csv = [head, ...rows.map(r => [r.wss_code, r.wss_name, r.district, data.value?.circles?.find(c => c.id === r.circle_id)?.name || '', r.source_type, r.times_tested, r.last_result, fmtDate(r.last_sampled_at), r.next_scheduled])]
    .map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `ce_wss_register_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <div class="cd">
    <div class="cd-scope">
      <span class="pin">💧</span>
      <b>WSS Register — CE scope.</b>
      <span class="sep">·</span>
      <span>All schemes across {{ (data?.circles || []).map(c => c.name).join(', ') || 'your circles' }}. Click Trail to see testing history.</span>
    </div>

    <div class="cd-cards cards-5">
      <div class="c">
        <div class="lbl">TOTAL WSS</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">CE region area</div>
      </div>
      <div class="c c-green">
        <div class="lbl">LAST RESULT: FIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_fit ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">LAST RESULT: UNFIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_unfit ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">UNTESTED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.untested ?? 0 }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">SCHEDULE OVERDUE</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.overdue ?? 0 }}</div>
      </div>
    </div>

    <div class="cd-toolbar">
      <div class="fg">
        <label>Search</label>
        <input type="text" v-model="filters.q" placeholder="🔍 WSS name, code…" />
      </div>
      <div class="fg">
        <label>SE Circle</label>
        <select v-model="filters.circle_id">
          <option value="">All Circles (CE Region)</option>
          <option v-for="c in (data?.circles || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
          <option v-for="d in (data?.districts || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>WQ Result</label>
        <select v-model="filters.result">
          <option value="">All WQ Results</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
          <option value="Untested">Untested</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="cd-btn cd-btn-sec" @click="exportCsv">⬇ Export</button>
    </div>

    <table class="cd-tbl">
      <thead>
        <tr>
          <th>WSS Code</th><th>WSS Name</th><th>District</th><th>SE Circle</th><th>Source</th><th>Solar</th><th>Op. Status</th><th>Times Tested</th><th>Last Result</th><th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <CeSkelRow v-for="n in 5" :key="'ws' + n" :cols="[100, 160, 80, 110, 70, 30, 70, 50, 60, 80, 80, 60]" />
        </template>
        <template v-else>
          <template v-for="grp in grouped" :key="grp.circle_id + '-' + grp.district">
            <tr class="group-h">
              <td colspan="12">📍 SE {{ grp.circle }} Circle — {{ grp.district }} District</td>
            </tr>
            <tr v-for="r in grp.items" :key="r.id">
              <td class="sid">{{ r.wss_code }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="cd-pill p-blue">SE {{ grp.circle }}</span></td>
              <td>{{ r.source_type }}</td>
              <td style="text-align:center">{{ (r.power_input || '').toLowerCase().includes('solar') ? '🟡' : '⚡' }}</td>
              <td><span class="cd-pill p-green">{{ r.operational_status }}</span></td>
              <td>{{ r.times_tested }}</td>
              <td><span class="cd-pill" :class="resultClass(r.last_result)">{{ r.last_result }}</span></td>
              <td>{{ fmtDate(r.last_sampled_at) }}</td>
              <td :style="r.overdue ? 'color:#b91c1c;font-weight:700' : ''">{{ fmtDate(r.next_scheduled) }}<span v-if="r.overdue"> ⚠</span></td>
              <td><button class="cd-btn cd-btn-sec">📋 Trail</button></td>
            </tr>
          </template>
          <tr v-if="!(data?.rows || []).length">
            <td colspan="12" class="empty">No WSS match these filters.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './ce-shared.scss' as *;
</style>

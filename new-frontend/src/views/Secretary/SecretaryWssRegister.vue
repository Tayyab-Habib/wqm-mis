<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', region_id: '', district_id: '', result: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q)           params.q           = filters.value.q
    if (filters.value.region_id)   params.region_id   = filters.value.region_id
    if (filters.value.district_id) params.district_id = filters.value.district_id
    if (filters.value.result)      params.result      = filters.value.result
    data.value = await secretaryService.wssRegister(params)
  } catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const grouped = computed(() => {
  const rows = data.value?.rows || []
  const groups = {}
  for (const r of rows) {
    const key = r.region_id || 0
    if (!groups[key]) groups[key] = { region_id: r.region_id, ce: r.ce, items: [] }
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
  if (r.includes('fit') && !r.includes('unfit')) return 'p-green'
  if (r.includes('unfit')) return 'p-red'
  return 'p-grey'
}

function exportCsv() {
  const rows = data.value?.rows || []
  if (!rows.length) return
  const head = ['WSS Code', 'WSS Name', 'District', 'CE', 'SE Circle', 'Source', 'Times Tested', 'Last Result', 'Last Sampled']
  const csv = [head, ...rows.map(r => [r.wss_code, r.wss_name, r.district, r.ce, r.se_circle, r.source_type, r.times_tested, r.last_result, fmtDate(r.last_sampled_at)])]
    .map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `secretary_wss_register_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">💧</span>
      <b>WSS Register — Province-wide.</b>
      <span class="sep">·</span>
      <span>All functional water supply schemes across KP, grouped by CE. Filter by CE, district, or WQ status.</span>
    </div>

    <div class="sd-cards cards-5">
      <div class="c">
        <div class="lbl">TOTAL FUNCTIONAL WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">LAST RESULT: FIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_fit ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">LAST RESULT: UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_unfit ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">UNTESTED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.untested ?? 0 }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">SCHEDULE OVERDUE</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.overdue ?? 0 }}</div>
      </div>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>Search</label><input type="text" v-model="filters.q" placeholder="🔍 WSS name, code…" /></div>
      <div class="fg">
        <label>Chief Engineer</label>
        <select v-model="filters.region_id">
          <option value="">All Chief Engineers</option>
          <option v-for="r in (data?.regions || [])" :key="r.id" :value="r.id">{{ r.name }}</option>
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
      <button class="sd-btn sd-btn-sec" @click="exportCsv">⬇ Export</button>
    </div>

    <table class="sd-tbl">
      <thead>
        <tr><th>WSS Code</th><th>WSS Name</th><th>District</th><th>CE</th><th>SE Circle</th><th>Source</th><th>Solar</th><th>Op. Status</th><th>Times Tested</th><th>Last Result</th><th>Last Sampled</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 5" :key="'ws' + n" :cols="[100, 160, 80, 110, 110, 70, 30, 80, 60, 80, 80]" />
        </template>
        <template v-else>
          <template v-for="grp in grouped" :key="grp.region_id">
            <tr class="group-h">
              <td colspan="11">📍 {{ grp.ce }}</td>
            </tr>
            <tr v-for="r in grp.items" :key="r.id">
              <td class="sid">{{ r.wss_code }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ r.ce }}</td>
              <td><span class="sd-pill p-blue">{{ r.se_circle }}</span></td>
              <td>{{ r.source_type }}</td>
              <td style="text-align:center">{{ (r.power_input || '').toLowerCase().includes('solar') ? '🟡' : '⚡' }}</td>
              <td><span class="sd-pill" :class="r.operational_status === 'Operational' ? 'p-green' : 'p-rose'">{{ r.operational_status }}</span></td>
              <td>{{ r.times_tested }}</td>
              <td><span class="sd-pill" :class="resultClass(r.last_result)">{{ r.last_result }}</span></td>
              <td>{{ fmtDate(r.last_sampled_at) }}</td>
            </tr>
          </template>
          <tr v-if="!(data?.rows || []).length">
            <td colspan="11" class="empty">No WSS match these filters.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>

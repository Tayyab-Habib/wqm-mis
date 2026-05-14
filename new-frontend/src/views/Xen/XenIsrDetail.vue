<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const sample = ref(null)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    sample.value = await xenService.isrShow(route.params.id)
  } catch (e) {
    error.value = e?.response?.status === 404
      ? 'Sample not in your division or not found.'
      : (e?.response?.data?.message || 'Failed to load report.')
  } finally { loading.value = false }
}
onMounted(load)
watch(() => route.params.id, load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
  catch { return d }
}
function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'st-green'
  if (r === 'unfit') return 'st-red'
  return 'st-grey'
}
</script>

<template>
  <div class="xd">
    <div class="xen-toolbar">
      <button class="btn-export" @click="router.back()">← Back</button>
      <div class="spacer"></div>
      <button class="btn-export" @click="() => window.print()">🖨 Print</button>
    </div>

    <template v-if="loading">
      <div class="panel">
        <div class="panel-h panel-h-navy">
          <span class="skel" style="width: 260px; height: 13px; background: rgba(255,255,255,.18)"></span>
        </div>
        <div class="isr-meta">
          <div class="row">
            <div v-for="i in 4" :key="'m1-' + i" class="kv">
              <span class="skel" style="width: 140px; height: 12px"></span>
            </div>
          </div>
          <div class="row">
            <div v-for="i in 4" :key="'m2-' + i" class="kv">
              <span class="skel" style="width: 140px; height: 12px"></span>
            </div>
          </div>
          <div class="row">
            <div v-for="i in 3" :key="'m3-' + i" class="kv">
              <span class="skel" style="width: 140px; height: 12px"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top: 16px">
        <div class="panel-h panel-h-navy">
          <span class="skel" style="width: 180px; height: 13px; background: rgba(255,255,255,.18)"></span>
        </div>
        <table class="tbl">
          <thead><tr><th>Parameter</th><th>Unit</th><th>Result</th><th>Range</th><th>Round</th></tr></thead>
          <tbody>
            <SkelRow v-for="n in 5" :key="'pp' + n" :cols="[140, 60, 70, 110, 50]" />
          </tbody>
        </table>
      </div>

      <div class="panel" style="margin-top: 16px">
        <div class="panel-h panel-h-navy">
          <span class="skel" style="width: 200px; height: 13px; background: rgba(255,255,255,.18)"></span>
        </div>
        <table class="tbl">
          <thead><tr><th>Round</th><th>Sampled</th><th>Analyzed</th><th>Status</th><th>Result</th><th>Remarks</th></tr></thead>
          <tbody>
            <SkelRow v-for="n in 3" :key="'tr' + n" :cols="[40, 130, 130, 80, 60, 160]" />
          </tbody>
        </table>
      </div>
    </template>
    <div v-else-if="error" class="xd-err">{{ error }}</div>

    <template v-else-if="sample">
      <div class="panel">
        <div class="panel-h panel-h-navy">📋 Individual Sample Report — {{ sample.slug }}</div>
        <div class="isr-meta">
          <div class="row">
            <div class="kv"><b>WSS:</b> {{ sample.wss_name }}</div>
            <div class="kv"><b>WSS Code:</b> {{ sample.wss_code }}</div>
            <div class="kv"><b>PHE Division:</b> {{ sample.phed_division }}</div>
            <div class="kv"><b>District:</b> {{ sample.district }}</div>
          </div>
          <div class="row">
            <div class="kv"><b>Sample Date:</b> {{ fmtDate(sample.sampled_at) }}</div>
            <div class="kv"><b>Analyzed:</b> {{ fmtDate(sample.analyzed_at) }}</div>
            <div class="kv"><b>Reported:</b> {{ fmtDate(sample.reported_at) }}</div>
            <div class="kv"><b>Type:</b> <span class="pill st-blue">{{ sample.type }}</span></div>
          </div>
          <div class="row">
            <div class="kv"><b>Sampling Point:</b> {{ sample.sampling_point }}</div>
            <div class="kv"><b>Source Type:</b> {{ sample.source_type }}</div>
            <div class="kv"><b>Result:</b> <span class="pill" :class="resultClass(sample.result)">{{ sample.result }}</span></div>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top: 16px">
        <div class="panel-h panel-h-navy">⚗ Test Parameters</div>
        <table class="tbl">
          <thead><tr><th>Parameter</th><th>Unit</th><th>Result</th><th>Range</th><th>Round</th></tr></thead>
          <tbody>
            <tr v-for="(p, i) in sample.parameters" :key="i">
              <td>{{ p.parameter }}</td>
              <td>{{ p.unit || '—' }}</td>
              <td><b>{{ p.value }}</b></td>
              <td>{{ p.range || '—' }}</td>
              <td><span class="pill st-blue">R{{ p.round }}</span></td>
            </tr>
            <tr v-if="sample.parameters.length === 0">
              <td colspan="5" class="empty">No test parameters recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="panel" style="margin-top: 16px">
        <div class="panel-h panel-h-navy">🧪 Test Rounds Timeline</div>
        <table class="tbl">
          <thead><tr><th>Round</th><th>Sampled</th><th>Analyzed</th><th>Status</th><th>Result</th><th>Remarks</th></tr></thead>
          <tbody>
            <tr v-for="t in sample.tests" :key="t.round">
              <td><span class="pill st-blue">R{{ t.round }}</span></td>
              <td>{{ fmtDate(t.sampled_at) }}</td>
              <td>{{ fmtDate(t.analyzed_at) }}</td>
              <td><span class="pill st-grey">{{ t.status }}</span></td>
              <td><span class="pill" :class="resultClass(t.result)">{{ t.result }}</span></td>
              <td>{{ t.remarks || '—' }}</td>
            </tr>
            <tr v-if="sample.tests.length === 0"><td colspan="6" class="empty">No tests yet.</td></tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;

.isr-meta {
  padding: 14px 18px;
  .row { display: flex; gap: 28px; flex-wrap: wrap; padding: 6px 0; }
  .kv { font-size: 12.5px; color: #334155;
    b { color: #64748b; font-weight: 600; margin-right: 4px; }
  }
}
</style>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { seService } from '../../services/seService.js'

const route  = useRoute()
const router = useRouter()
const loading = ref(true)
const sample  = ref(null)
const error   = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try { sample.value = await seService.isrShow(route.params.id) }
  catch (e) {
    error.value = e?.response?.status === 404
      ? 'Sample not in your circle or not found.'
      : (e?.response?.data?.message || 'Failed to load report.')
  } finally { loading.value = false }
}
onMounted(load)
watch(() => route.params.id, load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' }) }
  catch { return d }
}
function resultPill(r) {
  if (r === 'Fit')   return 'p-green'
  if (r === 'Unfit') return 'p-red'
  return 'p-grey'
}
</script>

<template>
  <div class="sd">
    <div class="sd-toolbar">
      <button class="sd-btn sd-btn-sec" @click="router.back()">← Back</button>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="() => window.print()">🖨 Print</button>
    </div>

    <div v-if="loading" style="padding:32px;text-align:center;color:#64748b">Loading report…</div>
    <div v-else-if="error" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:14px;border-radius:6px">{{ error }}</div>
    <template v-else-if="sample">
      <div class="sd-tbl-wrap head-navy">
        <div class="sd-tbl-head">📋 Individual Sample Report — {{ sample.slug }}</div>
        <div style="padding:14px 18px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px 24px;font-size:12.5px;color:#334155">
          <div><b style="color:#64748b">WSS:</b> {{ sample.wss_name }}</div>
          <div><b style="color:#64748b">District:</b> {{ sample.district }}</div>
          <div><b style="color:#64748b">PHE Division:</b> {{ sample.phed_division }}</div>
          <div><b style="color:#64748b">Result:</b> <span class="sd-pill" :class="resultPill(sample.result)">{{ sample.result }}</span></div>
          <div><b style="color:#64748b">Sampled:</b> {{ fmtDate(sample.sampled_at) }}</div>
          <div><b style="color:#64748b">Analyzed:</b> {{ fmtDate(sample.analyzed_at) }}</div>
          <div><b style="color:#64748b">Reported:</b> {{ fmtDate(sample.reported_at) }}</div>
          <div><b style="color:#64748b">Type:</b> {{ sample.type }}</div>
          <div><b style="color:#64748b">Sampling Point:</b> {{ sample.sampling_point }}</div>
          <div><b style="color:#64748b">Source Type:</b> {{ sample.source_type }}</div>
        </div>
      </div>

      <div class="sd-tbl-wrap head-navy" style="margin-top:14px">
        <div class="sd-tbl-head">⚗ Test Parameters</div>
        <table class="sd-tbl">
          <thead><tr><th>Parameter</th><th>Unit</th><th>Result</th><th>Range</th></tr></thead>
          <tbody>
            <tr v-for="(p, i) in sample.parameters" :key="i">
              <td>{{ p.parameter }}</td>
              <td>{{ p.unit }}</td>
              <td><b>{{ p.value }}</b></td>
              <td>{{ p.range || '—' }}</td>
            </tr>
            <tr v-if="!sample.parameters.length"><td colspan="4" class="empty">No parameters recorded.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="sd-tbl-wrap head-navy" style="margin-top:14px">
        <div class="sd-tbl-head">🧪 Test Rounds Timeline</div>
        <table class="sd-tbl">
          <thead><tr><th>Round</th><th>Sampled</th><th>Analyzed</th><th>Status</th><th>Result</th></tr></thead>
          <tbody>
            <tr v-for="t in sample.tests" :key="t.round">
              <td><span class="sd-pill p-cyan">R{{ t.round }}</span></td>
              <td>{{ fmtDate(t.sampled_at) }}</td>
              <td>{{ fmtDate(t.analyzed_at) }}</td>
              <td><span class="sd-pill p-grey">{{ t.status }}</span></td>
              <td><span class="sd-pill" :class="resultPill(t.result)">{{ t.result }}</span></td>
            </tr>
            <tr v-if="!sample.tests.length"><td colspan="5" class="empty">No tests yet.</td></tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { clientPortalService } from '../../services/clientPortalService.js'

const loading  = ref(true)
const errorMsg = ref('')
const samples  = ref([])
const expanded = ref(null)
const search   = ref('')

async function load() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res = await clientPortalService.getSamples()
    samples.value = res.data || res
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Failed to load results.'
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!search.value.trim()) return samples.value
  const q = search.value.toLowerCase()
  return samples.value.filter(s =>
    (s.slug || '').toLowerCase().includes(q) ||
    (s.sample_name || '').toLowerCase().includes(q) ||
    (s.laboratory || '').toLowerCase().includes(q)
  )
})

const fitCount     = computed(() => samples.value.filter(s => isResultFit(s.result)).length)
const unfitCount   = computed(() => samples.value.filter(s => isResultUnfit(s.result)).length)
const pendingCount = computed(() => samples.value.filter(s => !s.result).length)

function isResultFit(r)   { return r && (String(r).toLowerCase() === 'fit'   || r === '1') }
function isResultUnfit(r) { return r && (String(r).toLowerCase() === 'unfit' || r === '2') }

function toggle(id) {
  expanded.value = expanded.value === id ? null : id
}

function resultClass(result) {
  if (!result) return 'badge-pending'
  if (isResultFit(result))   return 'badge-fit'
  if (isResultUnfit(result)) return 'badge-unfit'
  return 'badge-pending'
}
function resultLabel(result) {
  if (!result) return 'Pending'
  if (isResultFit(result))   return 'Fit'
  if (isResultUnfit(result)) return 'Unfit'
  return result
}
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <div class="cr-page">
    <!-- Page header -->
    <div class="cp-page-header">
      <div>
        <h1 class="cp-page-title">My Test Results</h1>
        <p class="cp-page-sub">View all your water quality test results and parameter details</p>
      </div>
    </div>

    <!-- Skeleton placeholder while sample results load. Mirrors the
         layout underneath (4 stat cards + 8-col results table) so the
         page doesn't reflow when the real data lands. -->
    <div v-if="loading" class="cr-sk-wrap">
      <div class="cr-sk-cards">
        <div v-for="n in 4" :key="'cr-sc-' + n" class="cr-sk-card">
          <div class="cr-sk cr-sk-icon"></div>
          <div style="flex:1;display:flex;flex-direction:column;gap:6px">
            <div class="cr-sk cr-sk-val"></div>
            <div class="cr-sk cr-sk-lbl"></div>
          </div>
        </div>
      </div>
      <div class="cr-sk-tbl">
        <div class="cr-sk-th">
          <div v-for="n in 8" :key="'cr-th-' + n" class="cr-sk cr-sk-h"></div>
        </div>
        <div v-for="r in 5" :key="'cr-row-' + r" class="cr-sk-tr">
          <div v-for="c in 8" :key="'cr-tr' + r + '-' + c" class="cr-sk cr-sk-c"></div>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="errorMsg" class="cp-alert cp-alert--error">
      <span>⚠️</span> {{ errorMsg }}
    </div>

    <template v-else>
      <!-- Summary stat cards -->
      <div class="cp-stat-row">
        <div class="cp-stat-card">
          <div class="cp-stat-icon cp-stat-icon--blue">🧪</div>
          <div>
            <div class="cp-stat-val">{{ samples.length }}</div>
            <div class="cp-stat-lbl">Total Samples</div>
          </div>
        </div>
        <div class="cp-stat-card">
          <div class="cp-stat-icon cp-stat-icon--green">✅</div>
          <div>
            <div class="cp-stat-val" style="color:#16a34a">{{ fitCount }}</div>
            <div class="cp-stat-lbl">Fit</div>
          </div>
        </div>
        <div class="cp-stat-card">
          <div class="cp-stat-icon cp-stat-icon--red">❌</div>
          <div>
            <div class="cp-stat-val" style="color:#dc2626">{{ unfitCount }}</div>
            <div class="cp-stat-lbl">Unfit</div>
          </div>
        </div>
        <div class="cp-stat-card">
          <div class="cp-stat-icon cp-stat-icon--grey">⏳</div>
          <div>
            <div class="cp-stat-val" style="color:#64748b">{{ pendingCount }}</div>
            <div class="cp-stat-lbl">Pending</div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!samples.length" class="cp-empty">
        <div class="cp-empty-icon">🧪</div>
        <div class="cp-empty-title">No test results yet</div>
        <div class="cp-empty-sub">Your water quality test results will appear here once processed.</div>
      </div>

      <template v-else>
        <!-- Search -->
        <div class="cp-toolbar">
          <div class="cp-search-wrap">
            <span class="cp-search-icon">🔍</span>
            <input v-model="search" class="cp-search" placeholder="Search by sample ID, name or lab…" />
          </div>
          <span class="cp-count">{{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Table card -->
        <div class="cp-card">
          <div class="cp-table-wrap">
            <table class="cp-table">
              <thead>
                <tr>
                  <th>Sample ID</th>
                  <th>Sample Name</th>
                  <th>Source</th>
                  <th>Sampled</th>
                  <th>Analyzed</th>
                  <th>Laboratory</th>
                  <th>Result</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!filtered.length">
                  <td colspan="8" class="cp-table-empty">No results match your search.</td>
                </tr>
                <template v-for="s in filtered" :key="s.id">
                  <tr class="cp-table-row" :class="{ 'cp-table-row--expanded': expanded === s.id }">
                    <td><span class="cp-mono">{{ s.slug || s.id }}</span></td>
                    <td class="cp-fw">{{ s.sample_name || '—' }}</td>
                    <td class="cp-muted">{{ s.source_type || '—' }}</td>
                    <td class="cp-mono">{{ fmtDate(s.sampled_at) }}</td>
                    <td class="cp-mono">{{ fmtDate(s.analyzed_at) }}</td>
                    <td class="cp-muted">{{ s.laboratory || '—' }}</td>
                    <td><span class="cp-badge" :class="resultClass(s.result)">{{ resultLabel(s.result) }}</span></td>
                    <td>
                      <button class="cp-btn-view" @click="toggle(s.id)">
                        {{ expanded === s.id ? 'Hide ▲' : 'View ▼' }}
                      </button>
                    </td>
                  </tr>

                  <!-- Expanded parameters -->
                  <tr v-if="expanded === s.id" :key="'exp-' + s.id" class="cp-expand-row">
                    <td colspan="8" style="padding:0">
                      <div class="cp-expand-body">
                        <div class="cp-expand-header">
                          <span class="cp-expand-title">Parameter Results</span>
                          <span class="cp-expand-id">{{ s.slug }}</span>
                        </div>
                        <table class="cp-inner-table">
                          <thead>
                            <tr>
                              <th>Parameter</th>
                              <th>Type</th>
                              <th>Unit</th>
                              <th>WHO/NEQS Limit</th>
                              <th>Result</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-if="!s.parameters?.length">
                              <td colspan="5" class="cp-table-empty">No parameter data available.</td>
                            </tr>
                            <tr v-for="(p, pi) in s.parameters" :key="pi">
                              <td class="cp-fw">{{ p.parameter }}</td>
                              <td class="cp-muted">{{ p.type }}</td>
                              <td class="cp-muted">{{ p.unit || '—' }}</td>
                              <td class="cp-muted">{{ p.limit || '—' }}</td>
                              <td class="cp-fw cp-mono">{{ p.result || '—' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.cr-page { display: flex; flex-direction: column; gap: 20px; }

/* ── Results skeleton ─────────────────────────────────────────────── */
.cr-sk-wrap   { display: flex; flex-direction: column; gap: 14px; padding: 4px; }
.cr-sk-cards  { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.cr-sk-card   {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 14px 16px; display: flex; align-items: center; gap: 12px;
}
.cr-sk-tbl    { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.cr-sk-th     { display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.cr-sk-tr     { display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px; padding: 12px 14px; border-bottom: 1px solid #f1f5f9; }
.cr-sk-tr:last-child { border-bottom: none; }

.cr-sk {
  display: inline-block;
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: cr-shimmer 1.4s infinite ease-in-out;
}
.cr-sk-icon { width: 38px; height: 38px; border-radius: 8px; flex-shrink: 0; }
.cr-sk-val  { width: 50px; height: 18px; }
.cr-sk-lbl  { width: 80px; height: 10px; }
.cr-sk-h    { height: 11px; }
.cr-sk-c    { height: 12px; }

@keyframes cr-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}
</style>

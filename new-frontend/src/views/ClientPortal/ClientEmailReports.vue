<script setup>
import { ref, computed, onMounted } from 'vue'
import { clientPortalService } from '../../services/clientPortalService.js'

const loading  = ref(true)
const errorMsg = ref('')
const reports  = ref([])
const expanded = ref(null)
const search   = ref('')

async function load() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res = await clientPortalService.getEmailReports()
    reports.value = res.data || res
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Failed to load reports.'
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!search.value.trim()) return reports.value
  const q = search.value.toLowerCase()
  return reports.value.filter(r =>
    (r.slug || '').toLowerCase().includes(q) ||
    (r.sample_name || '').toLowerCase().includes(q)
  )
})

const fitCount   = computed(() => reports.value.filter(r => r.result === 'Fit').length)
const unfitCount = computed(() => reports.value.filter(r => r.result === 'Unfit').length)

function toggle(id) {
  expanded.value = expanded.value === id ? null : id
}

function resultClass(result) {
  if (!result) return 'badge-pending'
  return result === 'Fit' ? 'badge-fit' : 'badge-unfit'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <div class="cer-page">
    <!-- Page header -->
    <div class="cp-page-header">
      <div>
        <h1 class="cp-page-title">Email Reports</h1>
        <p class="cp-page-sub">Finalized water quality analysis reports with complete parameter details</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="cp-loading">
      <div class="cp-spinner"></div>
      <span>Loading reports…</span>
    </div>

    <!-- Error -->
    <div v-else-if="errorMsg" class="cp-alert cp-alert--error">
      <span>⚠️</span> {{ errorMsg }}
    </div>

    <template v-else>
      <!-- Stat cards -->
      <div class="cp-stat-row" v-if="reports.length">
        <div class="cp-stat-card">
          <div class="cp-stat-icon cp-stat-icon--blue">📋</div>
          <div>
            <div class="cp-stat-val">{{ reports.length }}</div>
            <div class="cp-stat-lbl">Total Reports</div>
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
      </div>

      <!-- Empty -->
      <div v-if="!reports.length" class="cp-empty">
        <div class="cp-empty-icon">📧</div>
        <div class="cp-empty-title">No finalized reports yet</div>
        <div class="cp-empty-sub">Reports will appear here once your samples have been fully analyzed and reported.</div>
      </div>

      <template v-else>
        <!-- Search -->
        <div class="cp-toolbar">
          <div class="cp-search-wrap">
            <span class="cp-search-icon">🔍</span>
            <input v-model="search" class="cp-search" placeholder="Search by sample ID or name…" />
          </div>
          <span class="cp-count">{{ filtered.length }} report{{ filtered.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Report cards -->
        <div class="cer-list">
          <div v-if="!filtered.length" class="cp-empty" style="margin-top:0">
            <div class="cp-empty-icon">🔍</div>
            <div class="cp-empty-title">No reports match your search</div>
          </div>

          <div v-for="r in filtered" :key="r.id" class="cer-card">
            <!-- Card header -->
            <div
              class="cer-card-header"
              :class="{ 'cer-card-header--open': expanded === r.id }"
              @click="toggle(r.id)"
            >
              <span class="cp-badge" :class="resultClass(r.result)">{{ r.result }}</span>
              <div class="cer-card-meta">
                <div class="cer-card-id">
                  {{ r.slug || ('Sample #' + r.id) }}
                  <span v-if="r.sample_name" class="cer-card-name"> — {{ r.sample_name }}</span>
                </div>
                <div class="cer-card-dates">
                  <span>📅 Sampled: {{ fmtDate(r.sampled_at) }}</span>
                  <span v-if="r.reported_at"> · Reported: {{ fmtDate(r.reported_at) }}</span>
                  <span v-if="r.laboratory"> · {{ r.laboratory.name }}</span>
                </div>
              </div>
              <div class="cer-card-toggle">
                <span>{{ expanded === r.id ? '▲' : '▼' }}</span>
              </div>
            </div>

            <!-- Expanded detail -->
            <div v-if="expanded === r.id" class="cer-card-body">
              <!-- Lab info -->
              <div v-if="r.laboratory" class="cer-lab-info">
                <div class="cer-lab-name">🏛 {{ r.laboratory.name }}</div>
                <div v-if="r.laboratory.address" class="cer-lab-detail">📍 {{ r.laboratory.address }}</div>
                <div class="cer-lab-detail">
                  <span v-if="r.laboratory.phone">📞 {{ r.laboratory.phone }}</span>
                  <span v-if="r.laboratory.email" style="margin-left:16px">✉️ {{ r.laboratory.email }}</span>
                </div>
              </div>

              <!-- Summary chips -->
              <div class="cer-summary-chips">
                <div class="cer-chip">
                  <div class="cer-chip-lbl">Sample ID</div>
                  <div class="cer-chip-val">{{ r.slug || r.id }}</div>
                </div>
                <div class="cer-chip">
                  <div class="cer-chip-lbl">District</div>
                  <div class="cer-chip-val">{{ r.district || '—' }}</div>
                </div>
                <div class="cer-chip">
                  <div class="cer-chip-lbl">Analyzed</div>
                  <div class="cer-chip-val">{{ fmtDate(r.analyzed_at) }}</div>
                </div>
                <div class="cer-chip">
                  <div class="cer-chip-lbl">Overall Result</div>
                  <div class="cer-chip-val">
                    <span class="cp-badge" :class="resultClass(r.result)">{{ r.result }}</span>
                  </div>
                </div>
              </div>

              <!-- Parameter table -->
              <div class="cp-card" style="margin-top:0">
                <div class="cp-table-wrap">
                  <table class="cp-table">
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
                      <tr v-if="!r.parameters?.length">
                        <td colspan="5" class="cp-table-empty">No parameter data available.</td>
                      </tr>
                      <tr v-for="(p, pi) in r.parameters" :key="pi" class="cp-table-row">
                        <td class="cp-fw">{{ p.parameter }}</td>
                        <td class="cp-muted">{{ p.type }}</td>
                        <td class="cp-muted">{{ p.unit || '—' }}</td>
                        <td class="cp-muted">{{ p.limit || '—' }}</td>
                        <td class="cp-fw cp-mono">{{ p.result || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.cer-page { display: flex; flex-direction: column; gap: 20px; }
.cer-list { display: flex; flex-direction: column; gap: 10px; }

.cer-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  transition: box-shadow .15s;
}
.cer-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }

.cer-card-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  cursor: pointer;
  user-select: none;
  transition: background .12s;
}
.cer-card-header:hover { background: #f8fafc; }
.cer-card-header--open { background: #eff6ff; border-bottom: 1px solid #bfdbfe; }

.cer-card-meta { flex: 1; min-width: 0; }
.cer-card-id   { font-size: 13px; font-weight: 700; color: #0f2d5e; }
.cer-card-name { font-weight: 400; color: #64748b; }
.cer-card-dates { font-size: 11px; color: #94a3b8; margin-top: 3px; }
.cer-card-toggle { font-size: 12px; color: #94a3b8; }

.cer-card-body { padding: 18px; display: flex; flex-direction: column; gap: 14px; }

.cer-lab-info {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
}
.cer-lab-name   { font-size: 12.5px; font-weight: 700; color: #0f2d5e; margin-bottom: 4px; }
.cer-lab-detail { font-size: 11px; color: #64748b; margin-top: 3px; }

.cer-summary-chips {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}
.cer-chip {
  background: #f1f5f9;
  border-radius: 8px;
  padding: 10px 14px;
}
.cer-chip-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.cer-chip-val { font-size: 12.5px; font-weight: 600; color: #0f2d5e; }
</style>

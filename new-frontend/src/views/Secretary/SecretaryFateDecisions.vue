<script setup>
import { ref, onMounted } from 'vue'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await secretaryService.fateDecisions() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function statusClass(s) {
  if (s === 'Implemented')      return 'p-green'
  if (s === 'Advisory Issued')  return 'p-amber'
  if (s === 'Monitoring Active')return 'p-blue'
  return 'p-grey'
}
function decisionClass(d) {
  if (d === 'Decommissioned')    return 'p-rose'
  if (d === 'Public Advisory')   return 'p-amber'
  if (d === 'Continue Monitoring')return 'p-blue'
  return 'p-grey'
}
</script>

<template>
  <div class="sd">
    <div class="sd-banner rose">
      ⚖️
      <span class="lab">WSS Fate Decisions — Pending Secretary Approval.</span>
      &nbsp; These WSS have failed chemical retest R2 and have been escalated by the respective CE. As Secretary, you are the approving authority for the Fate Decision.
    </div>

    <div class="sd-cards cards-4">
      <div class="c c-rose">
        <div class="lbl">DECISIONS PENDING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.pending ?? 0 }}</div>
        <div class="sub" v-if="!loading">Secretary approval required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">DECISIONS ISSUED (YTD)</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.issued_ytd ?? 0 }}</div>
        <div class="sub" v-if="!loading">Approved by Secretary</div>
      </div>
      <div class="c">
        <div class="lbl">DECOMMISSIONED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.decommissioned ?? 0 }}</div>
        <div class="sub" v-if="!loading">WSS taken out of service</div>
      </div>
      <div class="c c-green">
        <div class="lbl">PUBLIC ADVISORY ISSUED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.public_advisory ?? 0 }}</div>
        <div class="sub" v-if="!loading">Community warned</div>
      </div>
    </div>

    <table class="sd-tbl" style="margin-bottom:18px">
      <thead>
        <tr><th>WSS Name</th><th>District</th><th>CE</th><th>Contaminant</th><th>Original</th><th>R1</th><th>R2</th><th>WHO Limit</th><th>Contamination History</th><th>Stage</th><th>Secretary Decision</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 3" :key="'fp' + n" :cols="[160, 80, 110, 80, 70, 70, 70, 70, 180, 60, 80]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.pending || [])" :key="r.id" style="background:#fdf2f8">
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td><span class="sd-pill p-blue">{{ r.ce }}</span></td>
            <td><span class="sd-pill p-amber">{{ r.contaminant }}</span></td>
            <td>{{ r.original }}</td>
            <td>{{ r.r1 }}</td>
            <td>{{ r.r2 }}</td>
            <td>{{ r.who_limit }}</td>
            <td style="font-size:11px;color:#64748b">Trend: based on R0 → R1 → R2 values.</td>
            <td><span class="sd-pill p-red">{{ r.stage }}</span></td>
            <td><button class="sd-btn sd-btn-rose">⬇ Decide</button></td>
          </tr>
          <tr v-if="!(data?.pending || []).length">
            <td colspan="11" class="empty">No pending fate decisions.</td>
          </tr>
        </template>
      </tbody>
    </table>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">Past Fate Decisions — Issued by Secretary</h3>
    <table class="sd-tbl">
      <thead>
        <tr><th>WSS Name</th><th>District</th><th>CE</th><th>Contaminant</th><th>Decision</th><th>Date</th><th>Status</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 4" :key="'pa' + n" :cols="[160, 80, 110, 80, 110, 90, 90]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.past || [])" :key="r.id">
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td>{{ r.ce }}</td>
            <td><span class="sd-pill p-amber">{{ r.contaminant }}</span></td>
            <td><span class="sd-pill" :class="decisionClass(r.decision)">{{ r.decision }}</span></td>
            <td>{{ fmtDate(r.date) }}</td>
            <td><span class="sd-pill" :class="statusClass(r.status)">{{ r.status }}</span></td>
          </tr>
          <tr v-if="!(data?.past || []).length">
            <td colspan="7" class="empty">No past fate decisions yet.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>

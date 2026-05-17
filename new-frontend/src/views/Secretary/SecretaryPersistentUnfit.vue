<script setup>
import { ref, onMounted } from 'vue'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await secretaryService.persistentUnfit() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

function statusClass(s) {
  if (s === 'Implemented')      return 'p-green'
  if (s === 'Public Advisory')  return 'p-amber'
  if (s === 'Monitoring')       return 'p-blue'
  if (s === 'Pending')          return 'p-rose'
  return 'p-grey'
}
</script>

<template>
  <div class="sd">
    <div class="sd-banner rose">
      ⚠️
      <span class="lab">Persistent Unfit WSS — Province-wide.</span>
      All WSS with chemical contamination after R2, grouped by CE. Cases escalated to Secretary for Fate Decision.
    </div>

    <div class="sd-cards cards-4">
      <div class="c c-rose">
        <div class="lbl">TOTAL PERSISTENT UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total ?? 0 }}</div>
        <div class="sub" v-if="!loading">Province-wide</div>
      </div>
      <div class="c c-red">
        <div class="lbl">FATE DECISION PENDING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.fate_pending ?? 0 }}</div>
        <div class="sub" v-if="!loading">Secretary approval required</div>
      </div>
      <div class="c">
        <div class="lbl">UNDER MONITORING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.under_monitoring ?? 0 }}</div>
        <div class="sub" v-if="!loading">Decision: continue monitoring</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">DECOMMISSIONED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.decommissioned ?? 0 }}</div>
        <div class="sub" v-if="!loading">WSS taken out of service</div>
      </div>
    </div>

    <table class="sd-tbl">
      <thead>
        <tr><th>Sample ID</th><th>WSS Name</th><th>District</th><th>CE</th><th>SE Circle</th><th>R0 Remarks</th><th>R1 Remarks</th><th>R2 Remarks</th><th>Stage</th><th>Status</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 5" :key="'pu' + n" :cols="[100, 160, 80, 110, 110, 140, 140, 140, 60, 80]" />
        </template>
        <template v-else>
          <template v-for="grp in (data?.groups || [])" :key="grp.ce">
            <tr class="group-h"><td colspan="10">{{ grp.ce.toUpperCase() }}</td></tr>
            <tr v-for="r in grp.rows" :key="r.id" style="background:#fff0f5">
              <td class="sid">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ r.ce }}</td>
              <td>{{ r.se_circle }}</td>
              <td style="font-size:11px;color:#475569">{{ r.original }}</td>
              <td style="font-size:11px;color:#475569">{{ r.r1 }}</td>
              <td style="font-size:11px;color:#475569">{{ r.r2 }}</td>
              <td><span class="sd-pill p-red">{{ r.stage }}</span></td>
              <td><span class="sd-pill" :class="statusClass(r.status)">{{ r.status }}</span></td>
            </tr>
          </template>
          <tr v-if="!(data?.groups || []).length">
            <td colspan="10" class="empty">No persistent unfit WSS.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>

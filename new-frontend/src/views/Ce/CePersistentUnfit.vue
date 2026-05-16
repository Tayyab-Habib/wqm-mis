<script setup>
import { ref, onMounted } from 'vue'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await ceService.persistentUnfit() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
  <div class="cd">
    <div class="cd-banner" style="border-left-color:#9d174d;background:#fdf2f8;border-color:#fbcfe8">
      ⚠️
      <span class="lab" style="color:#9d174d">Persistent Unfit WSS</span>
      — Chemical contamination after R2. Auto-escalated to Secretary for WSS Fate Decision. CE monitoring required.
    </div>

    <div class="cd-cards cards-4">
      <div class="c c-rose">
        <div class="lbl">PERSISTENT UNFIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.persistent ?? 0 }}</div>
        <div class="sub" v-if="!loading">Chemical contamination after R2</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">ESCALATED TO SECRETARY</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.escalated_secretary ?? 0 }}</div>
        <div class="sub" v-if="!loading">Fate Decision pending</div>
      </div>
      <div class="c">
        <div class="lbl">FATE DECISION ISSUED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.fate_issued ?? 0 }}</div>
        <div class="sub" v-if="!loading">Decommissioning ordered</div>
      </div>
      <div class="c c-green">
        <div class="lbl">REMEDIATED (YTD)</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.remediated_ytd ?? 0 }}</div>
        <div class="sub" v-if="!loading">Source replaced</div>
      </div>
    </div>

    <table class="cd-tbl">
      <thead>
        <tr>
          <th>WSS Name</th><th>District</th><th>SE Circle</th><th>Contaminant</th><th>Original</th><th>R1</th><th>R2</th><th>WHO Limit</th><th>Stage</th><th>Fate Decision</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <CeSkelRow v-for="n in 3" :key="'pp' + n" :cols="[160, 80, 110, 80, 70, 70, 70, 70, 60, 80]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.list || [])" :key="r.id" style="background:#fff0f5">
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td><span class="cd-pill p-blue">{{ r.circle }}</span></td>
            <td><span class="cd-pill p-amber">{{ r.contaminant }}</span></td>
            <td>{{ r.original }}</td>
            <td>{{ r.r1 }}</td>
            <td>{{ r.r2 }}</td>
            <td>{{ r.who_limit }}</td>
            <td><span class="cd-pill p-red">{{ r.stage }}</span></td>
            <td><span style="color:#9d174d;font-weight:700">↑ {{ r.fate_decision }}</span></td>
          </tr>
          <tr v-if="!(data?.list || []).length">
            <td colspan="10" class="empty">No persistent unfit WSS.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './ce-shared.scss' as *;
</style>

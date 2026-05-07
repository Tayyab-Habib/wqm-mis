<script setup>
import { ref } from 'vue'
import { sampleService } from '../../../services/sampleService.js'

const searchForm = ref({ sampleId:'', clientName:'', lab:'', from:'', to:'' })
const loading    = ref(false)
const errorMsg   = ref('')
const report     = ref(null)
const physicalParams  = ref([])
const chemicalParams  = ref([])
const microbialParams = ref([])

function mapParams(details) {
  const physical  = []
  const chemical  = []
  const microbial = []

  ;(details || []).forEach(d => {
    const param = {
      param:   d.test?.name || d.test?.water_quality_parameter || '—',
      unit:    d.test?.unit || '—',
      result:  d.input_result || d.analysis_result || '—',
      limit:   d.test?.max_value || d.test?.limit || '—',
      status:  d.analysis_result === 'NT' ? 'Info' : (parseFloat(d.input_result) > parseFloat(d.test?.max_value) ? 'Unfit' : 'Fit'),
      remarks: d.remarks || '—',
    }
    const type = d.test?.type?.toLowerCase() || ''
    if (type.includes('physical'))  physical.push(param)
    else if (type.includes('micro') || type.includes('biological')) microbial.push(param)
    else chemical.push(param)
  })

  return { physical, chemical, microbial }
}

async function searchSample() {
  if (!searchForm.value.sampleId) { errorMsg.value = 'Please enter a Sample ID'; return }
  loading.value = true
  errorMsg.value = ''
  report.value = null
  try {
    const res = await sampleService.getById(searchForm.value.sampleId)
    const s   = res.data || res

    report.value = {
      sampleId:        s.slug || String(s.id),
      reportNo:        `RPT-${s.slug || s.id}`,
      wss:             s.waterScheme?.name || s.water_sample_address || '—',
      district:        s.district?.name || '—',
      division:        s.division?.name || '—',
      collectionPoint: s.sampling_point || '—',
      sourceGps:       s.latitude && s.longitude ? `${s.latitude}, ${s.longitude}` : '—',
      collectionDate:  s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
      collectionTime:  s.sampled_at ? s.sampled_at.split(' ')[1] || '—' : '—',
      dateReceived:    s.created_at ? s.created_at.split(' ')[0] : '—',
      collectedBy:     s.createdByUser?.name || '—',
      analysedBy:      s.modifiedByUser?.name || s.labIncharge?.name || '—',
      testType:        s.test_type || '—',
      reasonForTesting: s.complaint || '—',
      overallResult:   s.result || 'Pending',
    }

    const { physical, chemical, microbial } = mapParams(s.waterSampleDetails)
    physicalParams.value  = physical
    chemicalParams.value  = chemical
    microbialParams.value = microbial
  } catch (e) {
    errorMsg.value = 'Sample not found. Please check the Sample ID.'
    console.error('Sample search error:', e)
  } finally {
    loading.value = false
  }
}

function statusClass(s) {
  return s === 'Fit' ? 'r-green' : s === 'Unfit' ? 'r-red' : 'r-grey'
}
</script>

<template>
  <div>
    <!-- Search bar -->
    <div class="filters" style="margin-bottom:10px">
      <div class="fg" style="flex:2">
        <label>Sample ID</label>
        <input type="text" v-model="searchForm.sampleId" placeholder="e.g. 26/CLB/5042 or 26/CLB/P0089…">
      </div>
      <div class="fg" style="flex:2">
        <label>Client / WSS Name</label>
        <input type="text" v-model="searchForm.clientName" placeholder="e.g. Hayatabad WSS, Al-Noor Hospital…">
      </div>
      <div class="fg">
        <label>Lab</label>
        <select v-model="searchForm.lab">
          <option value="">All Labs</option>
          <option value="Peshawar">Peshawar</option>
          <option value="Mardan">Mardan</option>
          <option value="Kohat">Kohat</option>
          <option value="Abbottabad">Abbottabad</option>
        </select>
      </div>
      <div class="fg"><label>From</label><input type="date" v-model="searchForm.from"></div>
      <div class="fg"><label>To</label><input type="date" v-model="searchForm.to"></div>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="searchSample" :disabled="loading">{{ loading ? '🔄…' : '🔍 Search' }}</button>
      <button class="btn btn-sec btn-sm">🖨 Print PDF</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>
    <div v-if="!report && !loading" style="text-align:center;padding:40px;color:var(--muted);font-size:13px">Enter a Sample ID and click Search to view the report.</div>

    <!-- Report document -->
    <div v-if="report" style="background:#fff;border:1px solid var(--border);border-radius:6px;padding:24px 28px;max-width:860px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,.07)">
      <!-- Header -->
      <div style="text-align:center;border-bottom:2px solid var(--navy);padding-bottom:14px;margin-bottom:18px">
        <div style="font-size:12px;font-weight:700;color:var(--navy);letter-spacing:.04em;text-transform:uppercase">Government of Khyber Pakhtunkhwa</div>
        <div style="font-size:11.5px;color:var(--navy2);margin:2px 0">Public Health Engineering Department</div>
        <div style="font-size:13px;font-weight:700;color:var(--navy);margin:4px 0">Water Quality Analysis Report</div>
        <div style="font-size:11px;color:var(--muted)">Central Water Quality Laboratory, Peshawar</div>
      </div>

      <!-- Sample meta -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;margin-bottom:18px;border:1px solid #d0d7e0;border-radius:5px;overflow:hidden;font-size:12px">
        <div style="background:#f0f7ff;padding:7px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Sample ID</span><br>
          <b style="font-family:'DM Mono',monospace;font-size:13px;color:var(--navy)">{{ report.sampleId }}</b>
        </div>
        <div style="padding:7px 14px;border-bottom:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Report No.</span><br>
          <b>{{ report.reportNo }}</b>
        </div>
        <div style="background:#f0f7ff;padding:7px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Water Supply Scheme</span><br>
          <b>{{ report.wss }}</b>
        </div>
        <div style="padding:7px 14px;border-bottom:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">District / Division</span><br>
          <b>{{ report.district }} &nbsp;/&nbsp; {{ report.division }}</b>
        </div>
        <div style="background:#f0f7ff;padding:7px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Collection Point</span><br>
          <b>{{ report.collectionPoint }}</b>
        </div>
        <div style="padding:7px 14px;border-bottom:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Source GPS</span><br>
          <b style="font-family:'DM Mono',monospace">{{ report.sourceGps }}</b>
        </div>
        <div style="background:#f0f7ff;padding:7px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Collection Date &amp; Time</span><br>
          <b>{{ report.collectionDate }} &nbsp; {{ report.collectionTime }}</b>
        </div>
        <div style="padding:7px 14px;border-bottom:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Date Received at Lab</span><br>
          <b>{{ report.dateReceived }}</b>
        </div>
        <div style="background:#f0f7ff;padding:7px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Collected By</span><br>
          <b>{{ report.collectedBy }}</b>
        </div>
        <div style="padding:7px 14px;border-bottom:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Analysed By</span><br>
          <b>{{ report.analysedBy }}</b>
        </div>
        <div style="background:#f0f7ff;padding:7px 14px;border-right:1px solid #d0d7e0">
          <span style="color:var(--muted);font-size:11px">Test Type</span><br>
          <b>{{ report.testType }}</b>
        </div>
        <div style="padding:7px 14px">
          <span style="color:var(--muted);font-size:11px">Reason for Testing</span><br>
          <b>{{ report.reasonForTesting }}</b>
        </div>
      </div>

      <!-- Physical Parameters -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--blue);margin-bottom:6px;padding-bottom:3px;border-bottom:2px solid var(--sky)">🔵 Physical Parameters</div>
      <div class="tbl-wrap" style="margin-bottom:14px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy2);color:#fff">
              <th>Parameter</th><th>Unit</th><th>Result</th><th>WHO / NEQS Limit</th><th>Status</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in physicalParams" :key="p.param" :class="i%2===1?'alt':''">
              <td>{{ p.param }}</td>
              <td>{{ p.unit }}</td>
              <td class="mono">{{ p.result }}</td>
              <td class="mono">{{ p.limit }}</td>
              <td><span class="rag" :class="statusClass(p.status)">{{ p.status === 'Fit' ? '✅ Fit' : p.status === 'Unfit' ? '❌ Unfit' : 'ℹ Info' }}</span></td>
              <td>{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Chemical Parameters -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--blue);margin-bottom:6px;padding-bottom:3px;border-bottom:2px solid var(--sky)">🟠 Chemical Parameters</div>
      <div class="tbl-wrap" style="margin-bottom:14px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy2);color:#fff">
              <th>Parameter</th><th>Unit</th><th>Result</th><th>WHO / NEQS Limit</th><th>Status</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in chemicalParams" :key="p.param" :class="i%2===1?'alt':''">
              <td>{{ p.param }}</td>
              <td>{{ p.unit }}</td>
              <td class="mono">{{ p.result }}</td>
              <td class="mono">{{ p.limit }}</td>
              <td><span class="rag r-green">✅ Fit</span></td>
              <td>{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Microbial Parameters -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--blue);margin-bottom:6px;padding-bottom:3px;border-bottom:2px solid var(--sky)">🔴 Microbial Parameters</div>
      <div class="tbl-wrap" style="margin-bottom:20px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy2);color:#fff">
              <th>Parameter</th><th>Unit</th><th>Result</th><th>WHO / NEQS Limit</th><th>Status</th><th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in microbialParams" :key="p.param" :class="i%2===1?'alt':''">
              <td>{{ p.param }}</td>
              <td>{{ p.unit }}</td>
              <td class="mono" style="color:var(--red);font-weight:700">{{ p.result }}</td>
              <td class="mono">{{ p.limit }}</td>
              <td><span class="rag r-red">❌ Unfit</span></td>
              <td>{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Overall Verdict -->
      <div style="border-radius:7px;padding:14px 20px;background:#fff3f3;border:2px solid #d32f2f;display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div style="font-size:36px">❌</div>
        <div>
          <div style="font-size:15px;font-weight:800;color:#d32f2f;letter-spacing:.02em">UNFIT FOR HUMAN CONSUMPTION</div>
          <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
            Microbial contamination detected — Total Coliform &amp; E. coli exceed WHO/NEQS limits. Immediate corrective action recommended.
          </div>
        </div>
      </div>

      <!-- Signatures -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:8px;font-size:11.5px">
        <div style="border-top:1px solid #999;padding-top:8px;text-align:center">
          <div style="color:var(--muted);font-size:10.5px">Analysed By</div>
          <div style="font-weight:600;margin-top:2px">Dr. Fatima Khan</div>
          <div style="color:var(--muted)">Lab Officer</div>
        </div>
        <div style="border-top:1px solid #999;padding-top:8px;text-align:center">
          <div style="color:var(--muted);font-size:10.5px">Checked By</div>
          <div style="font-weight:600;margin-top:2px">Muhammad Irfan</div>
          <div style="color:var(--muted)">Senior Research Officer</div>
        </div>
        <div style="border-top:1px solid #999;padding-top:8px;text-align:center">
          <div style="color:var(--muted);font-size:10.5px">Issued By / Lab In-charge</div>
          <div style="font-weight:600;margin-top:2px">S.M. Adeel</div>
          <div style="color:var(--muted)">SRO / Lab In-charge</div>
        </div>
      </div>

      <!-- Footer note -->
      <div style="margin-top:18px;padding-top:10px;border-top:1px solid var(--border);font-size:10.5px;color:var(--muted);display:flex;justify-content:space-between">
        <span>Generated: {{ report.collectionDate }} &nbsp;|&nbsp; PHED KP Lab MIS &nbsp;|&nbsp; Confidential — Internal Use Only</span>
        <span>Report valid for 30 days from date of collection</span>
      </div>
    </div><!-- end v-if="report" -->
  </div>
</template>

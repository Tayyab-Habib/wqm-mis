<script setup>
import { ref, computed, onMounted } from 'vue'
import { diaryService } from '../../../services/diaryService.js'

const loading   = ref(false)
const errorMsg  = ref('')
const activeTab = ref(0)

// ── Data from backend ─────────────────────────────────────────────────
const diaryEntries = ref([])
const dispatches   = ref([])

function mapDiary(d) {
  return {
    id: d.id,
    date: d.created_at ? d.created_at.split(' ')[0] : '—',
    from: d.from || d.sender || '—',
    subject: d.subject || '—',
    priority: d.priority || 'Routine',
    due: d.due_date || d.action_due || '—',
    assignedTo: d.assigned_to || d.assignedUser?.name || '—',
    status: d.status || 'Pending',
    hasFile: !!d.file_path,
  }
}

function mapDispatch(d) {
  return {
    id: d.id,
    date: d.created_at ? d.created_at.split(' ')[0] : '—',
    to: d.to || d.recipient || '—',
    subject: d.subject || '—',
    priority: d.priority || 'Routine',
    mode: d.mode || d.dispatch_mode || '—',
    by: d.dispatched_by || d.user?.name || '—',
    status: d.status || 'Draft',
    hasFile: !!d.file_path,
  }
}

async function loadData() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [dRes, dispRes] = await Promise.all([
      diaryService.getDiaries(),
      diaryService.getDispatches(),
    ])
    const dData    = dRes.data?.data    || dRes.data    || []
    const dispData = dispRes.data?.data || dispRes.data || []
    diaryEntries.value = dData.map(mapDiary)
    dispatches.value   = dispData.map(mapDispatch)
  } catch (e) {
    errorMsg.value = 'Failed to load diary/dispatch data'
    console.error('Diary load error:', e)
  } finally {
    loading.value = false
  }
}

// ── Diary modal ───────────────────────────────────────────────────────
const showDiaryModal = ref(false)
const diaryForm = ref({ from:'', ref:'', letterDate: new Date().toISOString().split('T')[0], subject:'', priority:'', due:'', assignedTo:'', category:'', desc:'' })

async function saveDiary() {
  if (!diaryForm.value.from || !diaryForm.value.subject || !diaryForm.value.priority || !diaryForm.value.due || !diaryForm.value.assignedTo) {
    alert('Please fill all required fields.'); return
  }
  try {
    await diaryService.createDiary({
      from: diaryForm.value.from,
      reference: diaryForm.value.ref,
      letter_date: diaryForm.value.letterDate,
      subject: diaryForm.value.subject,
      priority: diaryForm.value.priority,
      due_date: diaryForm.value.due,
      assigned_to: diaryForm.value.assignedTo,
      category: diaryForm.value.category,
      description: diaryForm.value.desc,
    })
    await loadData()
    showDiaryModal.value = false
  } catch (e) {
    alert('Failed to save diary entry: ' + (e?.message || 'Unknown error'))
    console.error('Diary save error:', e)
  }
}

// ── Dispatch modal ────────────────────────────────────────────────────
const showDispatchModal = ref(false)
const dispForm = ref({ to:'', re:'', mode:'', subject:'', priority:'', by:'', notes:'' })

async function saveDispatch(status) {
  if (!dispForm.value.to || !dispForm.value.subject || !dispForm.value.mode || !dispForm.value.priority) {
    alert('Please fill all required fields.'); return
  }
  try {
    await diaryService.createDispatch({
      to: dispForm.value.to,
      in_response_to: dispForm.value.re,
      mode: dispForm.value.mode,
      subject: dispForm.value.subject,
      priority: dispForm.value.priority,
      dispatched_by: dispForm.value.by,
      notes: dispForm.value.notes,
      status,
    })
    await loadData()
    showDispatchModal.value = false
  } catch (e) {
    alert('Failed to save dispatch: ' + (e?.message || 'Unknown error'))
    console.error('Dispatch save error:', e)
  }
}

async function markDone(id, type) {
  try {
    if (type === 'diary') {
      await diaryService.updateDiary(id, { status: 'Completed' })
    } else {
      await diaryService.updateDispatch(id, { status: 'Sent' })
    }
    await loadData()
  } catch (e) {
    console.error('Mark done error:', e)
  }
}

// ── Filters ───────────────────────────────────────────────────────────
const diarySearch  = ref('')
const diaryPri     = ref('')
const diaryStatus  = ref('')

const filteredDiary = computed(() => diaryEntries.value.filter(d => {
  const matchSearch = !diarySearch.value || d.subject.toLowerCase().includes(diarySearch.value.toLowerCase()) || d.from.toLowerCase().includes(diarySearch.value.toLowerCase())
  const matchPri    = !diaryPri.value    || d.priority === diaryPri.value
  const matchStatus = !diaryStatus.value || d.status   === diaryStatus.value
  return matchSearch && matchPri && matchStatus
}))

const filteredDisp = computed(() => dispatches.value.filter(d => {
  return !diarySearch.value || d.subject.toLowerCase().includes(diarySearch.value.toLowerCase()) || d.to.toLowerCase().includes(diarySearch.value.toLowerCase())
}))

const pendingActions = computed(() => {
  const inward  = diaryEntries.value.filter(d => d.status !== 'Completed').map(d => ({ ...d, entryType:'Inward' }))
  const outward = dispatches.value.filter(d => d.status === 'Draft').map(d => ({ ...d, entryType:'Outward' }))
  return [...inward, ...outward]
})

function priClass(p) {
  return p === 'Immediate' ? 'r-red' : p === 'Urgent' ? 'r-amber' : 'r-grey'
}
function statusClass(s) {
  return s === 'Completed' || s === 'Acknowledged' || s === 'Sent' ? 'r-green' : s === 'In Progress' ? 'r-amber' : s === 'Draft' ? 'r-grey' : 'r-red'
}

onMounted(loadData)
</script>

<template>
  <div>
    <!-- Tabs -->
    <div class="tabs" style="margin-bottom:0">
      <div v-for="(t, i) in ['📥 Diary (Inward)','📤 Dispatch (Outward)','⏰ Pending Actions']" :key="i"
           class="tab" :class="{ active: activeTab === i }" @click="activeTab = i">{{ t }}</div>
    </div>

    <!-- ── DIARY (INWARD) ── -->
    <div v-if="activeTab === 0">
      <div class="toolbar">
        <input type="text" v-model="diarySearch" placeholder="🔍 Subject, reference, sender…">
        <select v-model="diaryPri" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
          <option value="">All Priority</option>
          <option value="Immediate">Immediate</option>
          <option value="Urgent">Urgent</option>
          <option value="Routine">Routine</option>
        </select>
        <select v-model="diaryStatus" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
          <option value="">All Status</option>
          <option value="Pending">Pending</option>
          <option value="In Progress">In Progress</option>
          <option value="Completed">Completed</option>
        </select>
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="showDiaryModal = true">+ New Diary Entry</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Diary No.</th><th>Date</th><th>From</th><th>Subject</th>
              <th>Priority</th><th>Action Due</th><th>Assigned To</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(d, i) in filteredDiary" :key="d.id" :class="i%2===1?'alt':''">
              <td class="mono">{{ d.id }}</td>
              <td>{{ d.date }}</td>
              <td>{{ d.from }}</td>
              <td>{{ d.subject }}</td>
              <td><span class="rag" :class="priClass(d.priority)">{{ d.priority }}</span></td>
              <td :style="d.status === 'Pending' ? 'color:var(--red);font-weight:600' : ''">{{ d.due }}</td>
              <td>{{ d.assignedTo }}</td>
              <td><span class="rag" :class="statusClass(d.status)">{{ d.status }}</span></td>
              <td>
                <button v-if="d.status !== 'Completed'" class="btn btn-sec btn-xs" @click="markDone(d.id, 'diary')">✔ Done</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── DISPATCH (OUTWARD) ── -->
    <div v-if="activeTab === 1">
      <div class="toolbar">
        <input type="text" v-model="diarySearch" placeholder="🔍 Subject, recipient, ref…">
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="showDispatchModal = true">+ New Dispatch</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Dispatch No.</th><th>Date</th><th>To</th><th>Subject</th>
              <th>Priority</th><th>Mode</th><th>Dispatched By</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(d, i) in filteredDisp" :key="d.id" :class="i%2===1?'alt':''">
              <td class="mono">{{ d.id }}</td>
              <td>{{ d.date }}</td>
              <td>{{ d.to }}</td>
              <td>{{ d.subject }}</td>
              <td><span class="rag" :class="priClass(d.priority)">{{ d.priority }}</span></td>
              <td>{{ d.mode }}</td>
              <td>{{ d.by }}</td>
              <td><span class="rag" :class="statusClass(d.status)">{{ d.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── PENDING ACTIONS ── -->
    <div v-if="activeTab === 2">
      <div v-if="pendingActions.length === 0" class="abar green">✅ No pending actions.</div>
      <div v-else class="abar amber">⚠ {{ pendingActions.length }} pending action(s) require attention.</div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr>
              <th>Ref. No.</th><th>Type</th><th>Date</th><th>From / To</th>
              <th>Subject</th><th>Priority</th><th>Due Date</th><th>Assigned To</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(d, i) in pendingActions" :key="d.id" :class="i%2===1?'alt':''">
              <td class="mono">{{ d.id }}</td>
              <td>
                <span v-if="d.entryType==='Inward'"
                      style="font-size:10px;background:#e0f0ff;color:var(--blue);padding:2px 7px;border-radius:4px;font-weight:600">📥 Inward</span>
                <span v-else
                      style="font-size:10px;background:#e8f5e9;color:var(--green);padding:2px 7px;border-radius:4px;font-weight:600">📤 Outward</span>
              </td>
              <td>{{ d.date }}</td>
              <td>{{ d.from || d.to }}</td>
              <td>{{ d.subject }}</td>
              <td><span class="rag" :class="priClass(d.priority)">{{ d.priority }}</span></td>
              <td>{{ d.due || '—' }}</td>
              <td>{{ d.assignedTo || d.by }}</td>
              <td>
                <button class="btn btn-pri btn-xs" @click="markDone(d.id, d.entryType === 'Inward' ? 'diary' : 'dispatch')">✔ Done</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── NEW DIARY MODAL ── -->
    <Teleport to="body">
      <div v-if="showDiaryModal" @click.self="showDiaryModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3800;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:680px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:14px;font-weight:700">📥 New Diary Entry (Inward)</div>
            <button @click="showDiaryModal = false"
                    style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <div style="padding:22px 24px">
            <div class="form-grid c2">
              <div class="fg2 span2">
                <label>From (Sender / Organisation) *</label>
                <input type="text" v-model="diaryForm.from" placeholder="e.g. UNOPS Office, DHO Peshawar…">
              </div>
              <div class="fg2">
                <label>Sender's Reference No.</label>
                <input type="text" v-model="diaryForm.ref" placeholder="e.g. UNOPS/2026/041">
              </div>
              <div class="fg2">
                <label>Letter / Communication Date</label>
                <input type="date" v-model="diaryForm.letterDate">
              </div>
              <div class="fg2 span2">
                <label>Subject *</label>
                <input type="text" v-model="diaryForm.subject" placeholder="Brief subject of the communication">
              </div>
              <div class="fg2">
                <label>Priority *</label>
                <select v-model="diaryForm.priority">
                  <option value="">— Select —</option>
                  <option value="Immediate">🔴 Immediate</option>
                  <option value="Urgent">🟠 Urgent</option>
                  <option value="Routine">🟢 Routine</option>
                </select>
              </div>
              <div class="fg2">
                <label>Action Due Date *</label>
                <input type="date" v-model="diaryForm.due">
              </div>
              <div class="fg2">
                <label>Assign To *</label>
                <select v-model="diaryForm.assignedTo">
                  <option value="">— Select —</option>
                  <option value="S.M. Adeel">S.M. Adeel</option>
                  <option value="Dr. Fatima Khan">Dr. Fatima Khan</option>
                  <option value="Ahmad Raza">Ahmad Raza</option>
                </select>
              </div>
              <div class="fg2">
                <label>Category</label>
                <select v-model="diaryForm.category">
                  <option value="">— Select —</option>
                  <option value="Report Request">Report Request</option>
                  <option value="Equipment / Procurement">Equipment / Procurement</option>
                  <option value="Complaint">Complaint</option>
                  <option value="Administrative">Administrative</option>
                </select>
              </div>
              <div class="fg2 span2">
                <label>Description / Summary</label>
                <textarea v-model="diaryForm.desc" rows="3" placeholder="Brief description…"></textarea>
              </div>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showDiaryModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveDiary">💾 Save Entry</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── NEW DISPATCH MODAL ── -->
    <Teleport to="body">
      <div v-if="showDispatchModal" @click.self="showDispatchModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3900;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:680px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:14px;font-weight:700">📤 New Dispatch (Outward)</div>
            <button @click="showDispatchModal = false"
                    style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <div style="padding:22px 24px">
            <div class="form-grid c2">
              <div class="fg2 span2">
                <label>To (Recipient / Organisation) *</label>
                <input type="text" v-model="dispForm.to" placeholder="e.g. UNOPS Office, DHO Peshawar…">
              </div>
              <div class="fg2">
                <label>In Response To (Diary No.)</label>
                <input type="text" v-model="dispForm.re" placeholder="e.g. DR/26/CLB/0041 (optional)">
              </div>
              <div class="fg2">
                <label>Dispatch Mode *</label>
                <select v-model="dispForm.mode">
                  <option value="">— Select —</option>
                  <option value="Email">Email</option>
                  <option value="Hard Copy">Hard Copy</option>
                  <option value="Email + Hard Copy">Email + Hard Copy</option>
                  <option value="Hand Delivered">Hand Delivered</option>
                </select>
              </div>
              <div class="fg2 span2">
                <label>Subject *</label>
                <input type="text" v-model="dispForm.subject" placeholder="Subject of dispatch">
              </div>
              <div class="fg2">
                <label>Priority *</label>
                <select v-model="dispForm.priority">
                  <option value="">— Select —</option>
                  <option value="Immediate">🔴 Immediate</option>
                  <option value="Urgent">🟠 Urgent</option>
                  <option value="Routine">🟢 Routine</option>
                </select>
              </div>
              <div class="fg2">
                <label>Dispatched By *</label>
                <select v-model="dispForm.by">
                  <option value="S.M. Adeel">S.M. Adeel</option>
                  <option value="Dr. Fatima Khan">Dr. Fatima Khan</option>
                  <option value="Ahmad Raza">Ahmad Raza</option>
                </select>
              </div>
              <div class="fg2 span2">
                <label>Summary / Notes</label>
                <textarea v-model="dispForm.notes" rows="3" placeholder="Brief description…"></textarea>
              </div>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showDispatchModal = false">Cancel</button>
            <button class="btn btn-sec" @click="saveDispatch('Draft')">💾 Save as Draft</button>
            <button class="btn btn-pri" @click="saveDispatch('Sent')">📤 Save &amp; Mark Sent</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

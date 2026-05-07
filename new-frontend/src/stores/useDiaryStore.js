import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useDiaryStore = defineStore('diary', () => {
  const diaryEntries = ref([
    { id:'DR/26/CLB/0041', date:'10-Mar-26', from:'UNOPS Office',   subject:'Request for Monthly WQ Report — Feb 2026', priority:'Immediate', due:'12-Mar-26', assignedTo:'S.M. Adeel',      status:'Pending',     hasFile:true  },
    { id:'DR/26/CLB/0040', date:'08-Mar-26', from:'KOICA Project',  subject:'Equipment Procurement — AAS Consumables',  priority:'Urgent',    due:'20-Mar-26', assignedTo:'Dr. Fatima Khan', status:'In Progress', hasFile:true  },
    { id:'DR/26/CLB/0039', date:'05-Mar-26', from:'DHO Peshawar',   subject:'Complaint — Drinking water quality Hayatabad', priority:'Urgent', due:'07-Mar-26', assignedTo:'S.M. Adeel',    status:'Completed',   hasFile:false },
  ])

  const dispatches = ref([
    { id:'DS/26/CLB/0018', date:'11-Mar-26', to:'UNOPS Office',          subject:'Monthly WQ Report — Feb 2026 (GAR + GSR)', priority:'Immediate', mode:'Email + Hard Copy', by:'S.M. Adeel',      status:'Acknowledged', hasFile:true  },
    { id:'DS/26/CLB/0017', date:'08-Mar-26', to:'DHO Peshawar',          subject:'Water Quality Report — Hayatabad Ph-5',     priority:'Urgent',    mode:'Email',             by:'Dr. Fatima Khan', status:'Sent',         hasFile:true  },
    { id:'DS/26/CLB/0016', date:'05-Mar-26', to:'KOICA Project',         subject:'Quotation — AAS Consumables Procurement',   priority:'Urgent',    mode:'Email',             by:'S.M. Adeel',      status:'Draft',        hasFile:false },
    { id:'DS/26/CLB/0015', date:'01-Mar-26', to:'All XENs — Peshawar Div.', subject:'Circular: Water Sampling Schedule Q1-2026', priority:'Routine', mode:'Hard Copy',        by:'S.M. Adeel',      status:'Acknowledged', hasFile:true  },
  ])

  const pendingActions = computed(() => {
    const inward  = diaryEntries.value.filter(d => d.status !== 'Completed').map(d => ({ ...d, entryType:'Inward' }))
    const outward = dispatches.value.filter(d => d.status === 'Draft').map(d => ({ ...d, entryType:'Outward' }))
    return [...inward, ...outward]
  })

  let diarySeq = 41
  let dispSeq  = 18

  function addDiary(entry) {
    diarySeq++
    diaryEntries.value.unshift({ ...entry, id:`DR/26/CLB/${String(diarySeq).padStart(4,'0')}`, status:'Pending' })
  }

  function addDispatch(entry) {
    dispSeq++
    dispatches.value.unshift({ ...entry, id:`DS/26/CLB/${String(dispSeq).padStart(4,'0')}` })
  }

  function markDone(id) {
    const d = diaryEntries.value.find(d => d.id === id)
    if (d) d.status = 'Completed'
    const dp = dispatches.value.find(d => d.id === id)
    if (dp) dp.status = 'Sent'
  }

  return { diaryEntries, dispatches, pendingActions, addDiary, addDispatch, markDone }
})

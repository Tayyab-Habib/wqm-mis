import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAssetStore = defineStore('assets', () => {
  // ── Stock (consumables) ──────────────────────────────────────────────
  const stockItems = ref([
    { id:'arsenic',   cat:'chem',   name:'Arsenic Reagent Kit',        unit:'Kit',    qty:12, expiry:'15-Apr-26', rag:'Adequate',  expWarn:true  },
    { id:'turbidity', cat:'chem',   name:'Turbidity Standard',          unit:'Bottle', qty:2,  expiry:'10-Apr-26', rag:'Depleting', expWarn:true  },
    { id:'ph4',       cat:'chem',   name:'pH Buffer 4.0',               unit:'Litre',  qty:0,  expiry:'28-Mar-26', rag:'Critical',  expWarn:true  },
    { id:'ph7',       cat:'chem',   name:'pH Buffer 7.0',               unit:'Litre',  qty:4,  expiry:'30-Jun-26', rag:'Adequate',  expWarn:false },
    { id:'fluoride',  cat:'chem',   name:'Fluoride Reagent (SPADNS)',   unit:'Bottle', qty:8,  expiry:'20-May-26', rag:'Adequate',  expWarn:false },
    { id:'nitrate',   cat:'chem',   name:'Nitrate Reagent',             unit:'Kit',    qty:1,  expiry:'15-May-26', rag:'Depleting', expWarn:false },
    { id:'coliform',  cat:'micro',  name:'Coliform Detection Medium',   unit:'Box',    qty:0,  expiry:'01-Mar-26', rag:'Expired',   expWarn:true  },
    { id:'macconkey', cat:'micro',  name:'MacConkey Broth (MF)',        unit:'Box',    qty:9,  expiry:'10-Aug-26', rag:'Adequate',  expWarn:false },
    { id:'h2s',       cat:'micro',  name:'H₂S Test Kit',                unit:'Pack',   qty:32, expiry:'01-Sep-26', rag:'Adequate',  expWarn:false },
    { id:'bottles250',cat:'glass',  name:'Sample Bottles 250mL (Sterile)',unit:'Pcs', qty:120, expiry:'—',         rag:'Adequate',  expWarn:false },
    { id:'bottles500',cat:'glass',  name:'Sample Bottles 500mL (PE)',   unit:'Pcs',    qty:40, expiry:'—',         rag:'Depleting', expWarn:false },
    { id:'memfilter', cat:'glass',  name:'Membrane Filter 0.45µm',      unit:'Pack',   qty:3,  expiry:'—',         rag:'Depleting', expWarn:false },
    { id:'gloves',    cat:'safety', name:'Latex Gloves (M)',             unit:'Box',    qty:12, expiry:'—',         rag:'Adequate',  expWarn:false },
    { id:'labcoats',  cat:'safety', name:'Lab Coats',                   unit:'Pcs',    qty:15, expiry:'—',         rag:'Adequate',  expWarn:false },
  ])

  // ── Inventory (non-consumables) ──────────────────────────────────────
  const inventoryItems = ref([
    { id:'IN/21/003', cat:'instrument', name:'Atomic Absorption Spectrophotometer', model:'Shimadzu AA-7000',    purchased:'12-Mar-21', qty:1, location:'Main Lab',        condition:'Good',         status:'Serviceable' },
    { id:'IN/20/001', cat:'instrument', name:'Turbidimeter',                         model:'Hach 2100Q',          purchased:'14-Feb-20', qty:1, location:'Physical Lab',    condition:'Faulty',       status:'Attention'   },
    { id:'IN/22/001', cat:'instrument', name:'Digital pH Meter',                     model:'Mettler Toledo S220', purchased:'30-Sep-22', qty:2, location:'Chemical Lab',    condition:'Good',         status:'Serviceable' },
    { id:'IN/19/001', cat:'instrument', name:'Autoclave',                             model:'Tuttnauer 2540E',     purchased:'11-Jan-19', qty:1, location:'Micro Lab',       condition:'Under Repair', status:'Attention'   },
    { id:'IN/20/002', cat:'instrument', name:'Incubator',                             model:'Memmert IN55',        purchased:'05-Aug-20', qty:1, location:'Micro Lab',       condition:'Good',         status:'Serviceable' },
    { id:'IN/15/001', cat:'instrument', name:'Flame Photometer',                      model:'Jenway PFP7',         purchased:'10-Jun-15', qty:1, location:'Store',           condition:'Beyond Repair',status:'Condemned'   },
    { id:'IN/22/002', cat:'it',         name:'Desktop Computer',                      model:'Dell OptiPlex 7090',  purchased:'01-Jul-22', qty:3, location:'Office',          condition:'Good',         status:'Serviceable' },
    { id:'IN/21/004', cat:'it',         name:'Laptop',                                model:'HP ProBook 450',      purchased:'15-Mar-21', qty:1, location:'—',               condition:'Not Found',    status:'Missing'     },
    { id:'IN/22/003', cat:'field',      name:'Portable pH/EC Meter',                  model:'Hanna HI9813-6',      purchased:'10-Feb-22', qty:4, location:'Field Kit Store', condition:'Good',         status:'Serviceable' },
    { id:'IN/21/001', cat:'field',      name:'GPS Device',                            model:'Garmin eTrex 32x',    purchased:'05-Jan-21', qty:3, location:'Field Kit Store', condition:'Good',         status:'Serviceable' },
  ])

  // ── Equipment ────────────────────────────────────────────────────────
  const equipment = ref([
    { id:'EQ/CLB/001', name:'Atomic Absorption Spectrophotometer', model:'Shimadzu AA-7000',    purchased:'12-Mar-21', calibCycle:'12 months', status:'Operational',  nextCalib:'01-Apr-2026',  calibOverdue:false },
    { id:'EQ/CLB/003', name:'Turbidimeter',                         model:'Hach 2100Q',          purchased:'14-Feb-20', calibCycle:'6 months',  status:'Out of Order', nextCalib:'OVERDUE ⚠',    calibOverdue:true  },
    { id:'EQ/CLB/005', name:'Autoclave',                            model:'Tuttnauer 2540E',     purchased:'11-Jan-19', calibCycle:'12 months', status:'Under Repair', nextCalib:'On Hold',       calibOverdue:false },
    { id:'EQ/CLB/006', name:'Digital pH Meter',                     model:'Mettler Toledo S220', purchased:'30-Sep-22', calibCycle:'6 months',  status:'Operational',  nextCalib:'30-Mar-26 ⚠',  calibOverdue:false },
  ])

  // ── Demand queue ─────────────────────────────────────────────────────
  const demands = ref([
    { id:'DMD/26/KHT/0012', from:'Kohat',      item:'Arsenic Reagent Kit',    qty:'5 Kit',   urgency:'Urgent',  daysPending:0, status:'Pending' },
    { id:'DMD/26/BNU/0031', from:'Bannu',      item:'pH Buffer 4.0',          qty:'4 Litre', urgency:'Urgent',  daysPending:3, status:'Pending' },
    { id:'DMD/26/ABT/0018', from:'Abbottabad', item:'Sample Bottles 500ml',   qty:'100 Pcs', urgency:'Routine', daysPending:5, status:'Pending' },
  ])

  function receiveStock(itemId, qty) {
    const item = stockItems.value.find(i => i.id === itemId)
    if (item) {
      item.qty += qty
      if (item.qty > 0) item.rag = 'Adequate'
    }
  }

  function approveDemand(demandId) {
    const d = demands.value.find(d => d.id === demandId)
    if (d) d.status = 'Approved'
  }

  function updateEquipmentStatus(eqId, status, nextCalib) {
    const eq = equipment.value.find(e => e.id === eqId)
    if (eq) { eq.status = status; if (nextCalib) eq.nextCalib = nextCalib }
  }

  return { stockItems, inventoryItems, equipment, demands, receiveStock, approveDemand, updateEquipmentStatus }
})

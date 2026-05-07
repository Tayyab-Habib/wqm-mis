import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useInvoiceStore = defineStore('invoices', () => {
  const invoices = ref([
    { id:'26/PWR/PHE/5001', client:'Khan Brothers Pvt.',  lab:'Central Lab', date:'08-Mar-26', samples:3,  total:5400,  received:5400,  balance:0,     status:'Paid',    type:'individual' },
    { id:'26/PWR/PHE/5002', client:'WAPDA Colony',        lab:'Central Lab', date:'07-Mar-26', samples:5,  total:9000,  received:4500,  balance:4500,  status:'Partial', type:'individual' },
    { id:'26/PWR/PHE/5003', client:'Al-Noor Hospital',    lab:'Central Lab', date:'06-Mar-26', samples:8,  total:14400, received:0,     balance:14400, status:'Unpaid',  type:'individual' },
    { id:'C/26/PWR/C0012',  client:'NESPAK Ltd.',         lab:'Central Lab', date:'05-Mar-26', samples:14, total:25200, received:25200, balance:0,     status:'Paid',    type:'clubbed'    },
    { id:'26/PWR/PHE/5004', client:'NHA Office',          lab:'Central Lab', date:'04-Mar-26', samples:6,  total:10800, received:0,     balance:10800, status:'Unpaid',  type:'individual' },
    { id:'26/MRD/PHE/1091', client:'Mardan PHE WSS',      lab:'Mardan',      date:'03-Mar-26', samples:4,  total:7200,  received:7200,  balance:0,     status:'Paid',    type:'individual' },
  ])

  const sbpSubmissions = ref([
    { id:'SBP/26/CLB/0042', date:'09-Mar-26', challan:'Pending',          amount:600000,  lab:'Central Lab', by:'S.M. Adeel', invoices:5,  status:'Pending Verification' },
    { id:'SBP/26/CLB/0041', date:'08-Mar-26', challan:'SBP-2026-04471',   amount:1800000, lab:'Central Lab', by:'S.M. Adeel', invoices:12, status:'Verified' },
    { id:'SBP/26/CLB/0040', date:'28-Feb-26', challan:'SBP-2026-03912',   amount:2100000, lab:'Central Lab', by:'S.M. Adeel', invoices:18, status:'Verified' },
  ])

  const ledger = ref([
    { date:'13-Jan-26', txId:'INV/26/CLB/0041', type:'Invoice',  client:'WAPDA Colony',      lab:'Central Lab', debit:72000,   credit:0,       note:'PCM test x 40 samples' },
    { date:'15-Jan-26', txId:'PMT/26/CLB/0031', type:'Payment',  client:'WAPDA Colony',      lab:'Central Lab', debit:0,       credit:72000,   note:'Bank Transfer — CHQ-0041' },
    { date:'20-Jan-26', txId:'INV/26/CLB/0042', type:'Invoice',  client:'NESPAK Ltd.',       lab:'Central Lab', debit:120000,  credit:0,       note:'PCM+Chemical x 60 samples' },
    { date:'28-Jan-26', txId:'PMT/26/CLB/0032', type:'Payment',  client:'NESPAK Ltd.',       lab:'Central Lab', debit:0,       credit:120000,  note:'Online EFT' },
    { date:'05-Feb-26', txId:'INV/26/CLB/0043', type:'Invoice',  client:'Al-Noor Hospital',  lab:'Central Lab', debit:86400,   credit:0,       note:'Microbial only x 48 samples' },
    { date:'08-Mar-26', txId:'SBP/26/CLB/0041', type:'SBP',      client:'State Bank',        lab:'Central Lab', debit:0,       credit:1800000, note:'SBP Challan SBP-2026-04471' },
  ])

  const totals = computed(() => ({
    invoiced:  invoices.value.reduce((s, i) => s + i.total, 0),
    collected: invoices.value.reduce((s, i) => s + i.received, 0),
    outstanding: invoices.value.reduce((s, i) => s + i.balance, 0),
  }))

  function recordPayment(invoiceId, amount) {
    const inv = invoices.value.find(i => i.id === invoiceId)
    if (!inv) return
    inv.received = Math.min(inv.total, inv.received + amount)
    inv.balance  = inv.total - inv.received
    inv.status   = inv.balance === 0 ? 'Paid' : 'Partial'
  }

  function addSbpSubmission(sub) {
    sbpSubmissions.value.unshift(sub)
  }

  return { invoices, sbpSubmissions, ledger, totals, recordPayment, addSbpSubmission }
})

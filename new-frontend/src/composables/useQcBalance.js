import { computed } from 'vue'

// Equivalent weights (mg/L → meq/L divisors)
const ANION_FACTORS  = { hco3:50, so4:48, cl:35, no3:14, co3:25 }
const CATION_FACTORS = { ca:20, mg:12, na:23, k:39 }

export function useQcBalance(params) {
  // params: reactive object with keys matching above

  const anions = computed(() => {
    const hco3 = (params.hco3 || 0) / ANION_FACTORS.hco3
    const so4  = (params.so4  || 0) / ANION_FACTORS.so4
    const cl   = (params.cl   || 0) / ANION_FACTORS.cl
    const no3  = (params.no3  || 0) / ANION_FACTORS.no3
    const co3  = (params.co3  || 0) / ANION_FACTORS.co3
    return { hco3, so4, cl, no3, co3, total: hco3 + so4 + cl + no3 + co3 }
  })

  const cations = computed(() => {
    const ca = (params.ca || 0) / CATION_FACTORS.ca
    const mg = (params.mg || 0) / CATION_FACTORS.mg
    const na = (params.na || 0) / CATION_FACTORS.na
    const k  = (params.k  || 0) / CATION_FACTORS.k
    return { ca, mg, na, k, total: ca + mg + na + k }
  })

  const diff    = computed(() => cations.value.total - anions.value.total)
  const sumBoth = computed(() => anions.value.total + cations.value.total)
  const pct     = computed(() => sumBoth.value > 0 ? (diff.value / sumBoth.value) * 100 : 0)
  const absPct  = computed(() => Math.abs(pct.value))
  const pass    = computed(() => absPct.value < 3)

  return { anions, cations, diff, pct, absPct, pass }
}

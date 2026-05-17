<script setup>
defineProps({
  cols: { type: Array, default: () => [120, 120, 120, 120, 120] },
})
</script>

<template>
  <tr>
    <td v-for="(w, i) in cols" :key="i">
      <span class="cd-skel" :style="`width:${w}px;height:12px`"></span>
    </td>
  </tr>
</template>

<!-- The shimmer rule lives in ce-shared.scss, but that file is imported as
     a SCOPED style by each parent view (CeDashboard, CeCircleDetail, …).
     Scoped styles do not reach into child component elements — so the
     .cd-skel spans rendered here never picked up the shimmer, producing
     empty 0-height rows. Defining it locally (also scoped) ensures the
     skeleton actually shows. Same fix as SecSkelRow.vue. -->
<style scoped>
.cd-skel {
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: cd-skel-shimmer 1.4s infinite;
  border-radius: 3px;
  display: inline-block;
  height: 14px;
  width: 100%;
}
@keyframes cd-skel-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>

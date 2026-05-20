<script setup>
defineProps({
  cols: { type: Array, default: () => [120, 120, 120, 120, 120] },
})
</script>

<template>
  <tr class="se-skel-row">
    <td v-for="(w, i) in cols" :key="i">
      <span class="se-skel" :style="`width:${w}px;height:12px`"></span>
    </td>
  </tr>
</template>

<!--
  Non-scoped on purpose. SeSkelRow is rendered inside parent tables and the
  shimmer animation needs to apply regardless of the parent component's scope
  ID. Previously this used `.sd-skel` from the parent's scoped se-shared.scss,
  which Vue does NOT propagate to child components — the spans rendered with
  no background, so loading tables looked like empty white rows.
-->
<style>
.se-skel-row td {
  padding: 9px 11px;
  border-bottom: 1px solid #eef2f7;
}
.se-skel-row .se-skel {
  display: inline-block;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: se-skel-shimmer 1.4s infinite linear;
  border-radius: 3px;
}
@keyframes se-skel-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>

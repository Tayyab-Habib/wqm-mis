<script setup>
defineProps({
  cols: { type: Array, default: () => [120, 120, 120, 120, 120] },
})
</script>

<template>
  <tr class="cmn-skel-row">
    <td v-for="(w, i) in cols" :key="i">
      <span class="cmn-skel" :style="`width:${w}px;height:12px`"></span>
    </td>
  </tr>
</template>

<!--
  Globally reusable shimmer row for table loading states. Mounted inside
  <tbody> with a `cols` array of column widths. Styles are intentionally
  NON-scoped so the shimmer renders correctly no matter what the parent
  component's scope id is — Vue's scoped CSS does not propagate into
  child components, which silently breaks visual rendering when the
  parent owns the `.cmn-skel` class.
-->
<style>
.cmn-skel-row td {
  padding: 9px 11px;
  border-bottom: 1px solid #eef2f7;
}
.cmn-skel-row .cmn-skel {
  display: inline-block;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: cmn-skel-shimmer 1.4s infinite linear;
  border-radius: 3px;
}
@keyframes cmn-skel-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>

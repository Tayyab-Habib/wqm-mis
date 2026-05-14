<script setup>
// Renders a single shimmering skeleton row for an XEN table.
// Pass `cols` as an array of widths (in px) — one entry per <td>.
// e.g. <SkelRow :cols="[80, 160, 90, 60, 70, 110]" />
defineProps({
  cols: { type: Array, default: () => [80, 160, 100, 100, 80] },
  height: { type: Number, default: 12 },
})
</script>

<template>
  <tr class="skel-row">
    <td v-for="(w, i) in cols" :key="i">
      <span class="skel" :style="{ width: w + 'px', height: height + 'px' }"></span>
    </td>
  </tr>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
</style>

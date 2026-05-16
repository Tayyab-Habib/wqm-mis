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

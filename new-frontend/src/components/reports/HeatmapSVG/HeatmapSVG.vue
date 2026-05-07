<script setup>
import './HeatmapSVG.scss'

defineProps({
  data: { type: Array, default: () => [] },
  width: { type: Number, default: 600 },
  height: { type: Number, default: 400 },
})
</script>

<template>
  <div class="heatmap-svg">
    <svg :width="width" :height="height" role="img" aria-label="Heatmap">
      <slot />
    </svg>
  </div>
</template>

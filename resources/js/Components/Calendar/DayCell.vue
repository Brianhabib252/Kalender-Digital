<script setup>
const props = defineProps({
  date: { type: Date, required: true },
  muted: { type: Boolean, default: false },
  isToday: { type: Boolean, default: false },
})
</script>

<template>
  <div class="relative rounded-xl h-full cursor-pointer hover:bg-gray-50 transition transform-gpu duration-300 hover:-translate-y-0.5 hover:shadow-sm ring-0 p-2" :class="{ 'bg-emerald-50/50 ring-1 ring-emerald-300': isToday }">
    <div class="px-1 py-1">
      <slot name="header" />
    </div>
    <div class="px-1 pb-1">
      <slot />
    </div>
  </div>
</template>

<style scoped>
</style>

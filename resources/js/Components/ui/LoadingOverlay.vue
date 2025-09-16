<script setup>
const props = defineProps({
  message: { type: String, default: 'Memuat…' },
})
</script>

<template>
  <div class="absolute inset-0 z-20 grid place-items-center bg-white/70 backdrop-blur-[2px]">
    <div class="flex items-center gap-4 text-gray-700 text-lg">
      <span class="inline-block h-6 w-6 border-2 border-current border-r-transparent rounded-full animate-spin"></span>
      <span class="font-semibold">{{ message }}</span>
    </div>
  </div>
</template>

<style scoped>
</style>

<script setup>
const props = defineProps({ event: Object })

function pad(n){ return String(n).padStart(2,'0') }
function parseLocalDate(str){
  const m = str && str.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (m){ const [,y,mo,d,h,mi,s]=m; return new Date(+y, +mo-1, +d, +h, +mi, +(s||0)) }
  return new Date(str)
}
function fmtHM(str){
  const dt = parseLocalDate(str)
  if (isNaN(dt)) return ''
  return pad(dt.getHours()) + ':' + pad(dt.getMinutes())
}
</script>

<template>
  <div
    class="absolute rounded-xl text-white text-sm p-3 overflow-hidden cursor-pointer shadow-md transition transform-gpu duration-200 hover:shadow-xl hover:scale-[1.01] active:scale-95 flex items-center justify-center text-center"
    :class="['bg-gradient-to-r from-emerald-500 to-green-500 ring-1 ring-emerald-400/50']"
  >
    <div class="truncate whitespace-nowrap">
      <span class="font-semibold">{{ event.title }}</span>
      <span v-if="!event.all_day" class="ml-2 font-mono text-xs opacity-90">({{ fmtHM(event.start_at) }} - {{ fmtHM(event.end_at) }})</span>
    </div>
  </div>
</template>

<style scoped>
</style>

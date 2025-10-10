<script setup>
const { event } = defineProps({
  event: {
    type: Object,
    required: true,
  },
})

function pad(value) {
  return String(value).padStart(2, '0')
}

function parseLocalDate(raw) {
  if (!raw)
    return new Date(Number.NaN)
  const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (match) {
    const [, year, month, day, hours, minutes, seconds] = match
    return new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hours),
      Number(minutes),
      Number(seconds || '0'),
    )
  }
  return new Date(raw)
}

function fmtHM(raw) {
  const dt = parseLocalDate(raw)
  if (Number.isNaN(dt.getTime()))
    return ''
  return `${pad(dt.getHours())}:${pad(dt.getMinutes())}`
}
</script>

<template>
  <div
    class="absolute rounded-xl text-white text-sm p-3 overflow-hidden cursor-pointer shadow-md transition transform-gpu duration-200 hover:shadow-xl hover:scale-[1.01] active:scale-95 flex items-center justify-center text-center bg-gradient-to-r from-emerald-500 to-green-500 ring-1 ring-emerald-400/50"
  >
    <div class="truncate whitespace-nowrap">
      <span class="font-semibold">{{ event.title }}</span>
      <span v-if="!event.all_day" class="ml-2 font-mono text-xs opacity-90">({{ fmtHM(event.start_at) }} - {{ fmtHM(event.end_at) }})</span>
    </div>
  </div>
</template>

<style scoped>
</style>

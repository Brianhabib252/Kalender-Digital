<script setup>
import { computed } from 'vue'
import EventBlock from './EventBlock.vue'

const props = defineProps({
  date: { type: String, required: true },
  events: { type: Array, default: () => [] },
  startHour: { type: Number, default: 0 },
  endHour: { type: Number, default: 23 },
  canCreate: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
})

const emit = defineEmits(['open-create','open-edit'])

function parseLocalDate(str) {
  if (!str) return new Date(NaN)
  // Match YYYY-MM-DDTHH:MM:SS (ignore timezone) or YYYY-MM-DD HH:MM:SS
  const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (m) {
    const [_, y, mo, d, h, mi, s] = m
    return new Date(Number(y), Number(mo)-1, Number(d), Number(h), Number(mi), Number(s||'0'))
  }
  // Fallback to Date parser
  return new Date(str)
}

const dayStart = computed(() => new Date(props.date + 'T' + String(props.startHour).padStart(2,'0') + ':00:00'))
const dayEnd = computed(() => new Date(props.date + 'T' + String(props.endHour).padStart(2,'0') + ':00:00'))
const rangeHours = computed(() => props.endHour - props.startHour + 1)

function minutesSinceStart(dt) {
  return Math.max(0, (dt - dayStart.value) / 60000)
}

function filtered() {
  return props.events.filter(e => {
    const s = parseLocalDate(e.start_at); const n = parseLocalDate(e.end_at)
    return (s <= dayEnd.value && n >= dayStart.value) || e.all_day
  })
}

const hourHeight = 40 // px per hour
const headerOffset = 48 // px offset to match the sticky header height

function blockStyle(start, end) {
  const top = minutesSinceStart(start) / 60 * hourHeight
  const h = Math.max(20, ((end - start) / 3600000) * hourHeight)
  return { top: `${top}px`, height: `${h}px` }
}

const laidOut = computed(() => {
  // Prepare clamped events and sort by start
  const items = filtered().map(e => {
    if (e.all_day) return null
    const s = parseLocalDate(e.start_at)
    const n = parseLocalDate(e.end_at)
    const start = new Date(Math.max(s.getTime(), dayStart.value.getTime()))
    const end = new Date(Math.min(n.getTime(), dayEnd.value.getTime()))
    if (end <= start) return null
    return { e, start, end }
  }).filter(Boolean).sort((a,b) => a.start - b.start || a.end - b.end)

  const output = []
  let cluster = []
  let active = [] // items currently overlapping

  const flushCluster = () => {
    if (cluster.length === 0) return
    // Assign columns greedily
    const colsEnd = [] // end time per column
    const colIndex = new Map()
    for (const it of cluster) {
      let idx = 0
      while (idx < colsEnd.length && colsEnd[idx] > it.start) idx++
      if (idx === colsEnd.length) colsEnd.push(it.end)
      else colsEnd[idx] = it.end
      colIndex.set(it, idx)
    }
    const colCount = colsEnd.length
    const gap = 2 // percent gap sum approximated via padding
    for (const it of cluster) {
      const ci = colIndex.get(it)
      const width = 100 / colCount
      const left = (ci * width)
      output.push({
        e: it.e,
        style: {
          ...blockStyle(it.start, it.end),
          left: `calc(${left}% + 0px)`,
          width: `calc(${width}% - 6px)`,
        },
        key: `${it.e.id}-${left}-${it.start.getTime()}`,
      })
    }
    cluster = []
    active = []
  }

  for (const it of items) {
    // remove inactive from active
    active = active.filter(a => a.end > it.start)
    if (active.length === 0 && cluster.length > 0) {
      flushCluster()
    }
    cluster.push(it)
    active.push(it)
  }
  flushCluster()
  return output
})

const hours = computed(() => Array.from({ length: rangeHours.value }, (_,i) => i + props.startHour))

function handleOpenCreate(date) {
  if (!props.canCreate) return
  emit('open-create', date)
}

function handleOpenEdit(event) {
  emit('open-edit', event)
}
</script>

<template>
  <div class="relative">
    <div class="flex min-w-full">
      <div class="w-14 select-none pr-2 text-right text-xs text-gray-500 sm:w-16 sm:text-sm">
        <div :style="{ height: headerOffset + 'px' }"></div>
        <div v-for="h in hours" :key="h" :style="{ height: hourHeight + 'px' }">{{ (h+'').padStart(2,'0') }}:00</div>
      </div>
      <div class="relative flex-1 overflow-hidden rounded-xl border bg-white">
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-emerald-50/70 px-3 py-3 sm:px-4">
          <div class="text-sm font-bold text-emerald-700 sm:text-base md:text-lg">{{ date }}</div>
          <button
            class="h-9 rounded-xl bg-emerald-600 px-3 text-sm font-semibold text-white transition hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 sm:h-10 sm:px-4"
            :disabled="!props.canCreate"
            @click="() => handleOpenCreate(date)"
          >
            + Buat
          </button>
        </div>
        <div class="relative" :style="{ height: (hourHeight * rangeHours) + 'px' }">
          <div v-for="h in hours" :key="h" class="absolute left-0 right-0 border-t border-gray-200" :style="{ top: (hourHeight * (h - props.startHour)) + 'px' }"></div>
          <EventBlock
            v-for="item in laidOut"
            :key="item.key"
            :event="item.e"
            :style="item.style"
            :class="props.canEdit ? '' : 'opacity-60'"
            @click="() => handleOpenEdit(item.e)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>

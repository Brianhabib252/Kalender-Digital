<script setup>
import { computed } from 'vue'
import { holidaysForDate } from '../../lib/holidays.js'
import EventBlock from './EventBlock.vue'

const props = defineProps({
  date: { type: String, required: true },
  events: { type: Array, default: () => [] },
  holidays: { type: Array, default: () => [] },
  startHour: { type: Number, default: 0 },
  endHour: { type: Number, default: 23 },
  canCreate: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
  displayLabel: { type: String, default: '' },
})

const emit = defineEmits(['open-create', 'open-edit'])

const headerLabel = computed(() => props.displayLabel || props.date)
const dayHolidays = computed(() => {
  if (!props.date)
    return []
  const base = parseLocalDate(`${props.date}T00:00:00`)
  if (Number.isNaN(base.getTime()))
    return []
  return holidaysForDate(base, props.holidays || [])
})
const isHoliday = computed(() => dayHolidays.value.length > 0)
const holidayNames = computed(() => dayHolidays.value.map(h => h?.name).filter(Boolean))

function parseLocalDate(str) {
  if (!str)
    return new Date(Number.NaN)
  // Match YYYY-MM-DDTHH:MM:SS (ignore timezone) or YYYY-MM-DD HH:MM:SS
  const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (m) {
    const [, y, mo, d, h, mi, s] = m
    return new Date(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s || '0'))
  }
  // Fallback to Date parser
  return new Date(str)
}

const dayStart = computed(() => new Date(`${props.date}T${String(props.startHour).padStart(2, '0')}:00:00`))
const dayEnd = computed(() => new Date(`${props.date}T${String(props.endHour).padStart(2, '0')}:00:00`))
const rangeHours = computed(() => props.endHour - props.startHour + 1)

function minutesSinceStart(dt) {
  return Math.max(0, (dt - dayStart.value) / 60000)
}

function filtered() {
  return props.events.filter((e) => {
    const start = parseLocalDate(e.start_at)
    const end = parseLocalDate(e.end_at)
    return (start <= dayEnd.value && end >= dayStart.value) || e.all_day
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
  const items = filtered()
    .map((event) => {
      if (event.all_day)
        return null
      const startRaw = parseLocalDate(event.start_at)
      const endRaw = parseLocalDate(event.end_at)
      const start = new Date(Math.max(startRaw.getTime(), dayStart.value.getTime()))
      const end = new Date(Math.min(endRaw.getTime(), dayEnd.value.getTime()))
      if (end <= start)
        return null
      return { e: event, start, end }
    })
    .filter(Boolean)
    .sort((a, b) => (a.start - b.start) || (a.end - b.end))

  const output = []
  let cluster = []
  let active = [] // items currently overlapping

  const flushCluster = () => {
    if (cluster.length === 0)
      return
    // Assign columns greedily
    const colsEnd = [] // end time per column
    const colIndex = new Map()
    for (const it of cluster) {
      let idx = 0
      while (idx < colsEnd.length && colsEnd[idx] > it.start)
        idx++
      if (idx === colsEnd.length)
        colsEnd.push(it.end)
      else
        colsEnd[idx] = it.end
      colIndex.set(it, idx)
    }
    const colCount = colsEnd.length
    for (const it of cluster) {
      const ci = colIndex.get(it)
      const width = 100 / colCount
      const left = ci * width
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
    if (active.length === 0 && cluster.length > 0)
      flushCluster()
    cluster.push(it)
    active.push(it)
  }
  flushCluster()
  return output
})

const hours = computed(() => {
  const values = []
  for (let index = 0; index < rangeHours.value; index += 1)
    values.push(index + props.startHour)
  return values
})

function handleOpenCreate(date) {
  if (!props.canCreate)
    return
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
        <div :style="{ height: `${headerOffset}px` }" />
        <div v-for="h in hours" :key="h" :style="{ height: `${hourHeight}px` }">
          {{ (`${h}`).padStart(2, '0') }}:00
        </div>
      </div>
      <div class="relative flex-1 overflow-hidden rounded-xl border bg-white">
        <div
          class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b px-3 py-3 sm:px-4 transition-colors"
          :class="isHoliday ? 'border-red-200 bg-red-50/80' : 'border-emerald-100 bg-emerald-50/70'"
        >
          <div
            class="text-sm font-bold sm:text-base md:text-lg"
            :class="isHoliday ? 'text-red-700' : 'text-emerald-700'"
          >
            {{ headerLabel }}
          </div>
          <button
            class="h-9 rounded-xl bg-emerald-600 px-3 text-base font-bold text-white transition hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 sm:h-10 sm:px-4 sm:text-lg"
            :disabled="!props.canCreate"
            @click="() => handleOpenCreate(date)"
          >
            +
          </button>
        </div>
        <div v-if="isHoliday" class="px-3 pt-2 text-xs font-semibold text-red-600 sm:px-4">
          Tanggal merah: {{ holidayNames.join(', ') }}
        </div>
        <div class="relative" :style="{ height: `${hourHeight * rangeHours}px` }">
          <div v-for="h in hours" :key="h" class="absolute left-0 right-0 border-t border-gray-200" :style="{ top: `${hourHeight * (h - props.startHour)}px` }" />
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

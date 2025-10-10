<script setup>
import { computed } from 'vue'
import { gregorianToHijri, hijriMonthRange } from '../../lib/hijri.js'
import { holidaysForDate } from '../../lib/holidays.js'
import DayCell from './DayCell.vue'
import EventChip from './EventChip.vue'

const props = defineProps({
  date: { type: String, required: true },
  events: { type: Array, default: () => [] },
  holidays: { type: Array, default: () => [] },
  canCreate: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
  calendarSystem: { type: String, default: 'gregorian' },
})

const emit = defineEmits(['select-day', 'open-create', 'open-edit'])

const isHijri = computed(() => props.calendarSystem === 'hijri')
const baseHijri = computed(() => (isHijri.value ? gregorianToHijri(parseYMD(props.date)) : null))

function startOfWeek(date) {
  const value = new Date(date)
  const day = (value.getDay() + 6) % 7
  value.setDate(value.getDate() - day)
  value.setHours(0, 0, 0, 0)
  return value
}

function endOfWeek(date) {
  const value = startOfWeek(date)
  value.setDate(value.getDate() + 6)
  value.setHours(23, 59, 59, 999)
  return value
}

function startOfMonth(date) {
  const value = new Date(date)
  value.setDate(1)
  value.setHours(0, 0, 0, 0)
  return value
}

function endOfMonth(date) {
  const value = new Date(date)
  value.setMonth(value.getMonth() + 1, 0)
  value.setHours(23, 59, 59, 999)
  return value
}

function parseYMD(s) {
  if (!s)
    return new Date()
  const [y, m, da] = s.split('-').map(n => Number.parseInt(n, 10))
  return new Date(y, (m || 1) - 1, da || 1)
}

function ymd(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const da = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${da}`
}

function handleOpenCreate(day) {
  if (!props.canCreate)
    return
  emit('open-create', day)
}

function handleOpenEdit(event) {
  emit('open-edit', event)
}

const weeks = computed(() => {
  const base = parseYMD(props.date)
  let gridStart
  let gridEnd
  if (isHijri.value) {
    const range = hijriMonthRange(base)
    gridStart = startOfWeek(range.start)
    gridEnd = endOfWeek(range.end)
  }
  else {
    const sm = startOfMonth(base)
    const em = endOfMonth(base)
    gridStart = startOfWeek(sm)
    gridEnd = endOfWeek(em)
  }
  const days = []
  let cursor = new Date(gridStart)
  const limit = gridEnd.getTime()
  while (cursor.getTime() <= limit) {
    days.push(new Date(cursor))
    cursor = new Date(cursor.getTime() + 86400000)
  }
  const rows = []
  for (let i = 0; i < days.length; i += 7)
    rows.push(days.slice(i, i + 7))
  return rows
})

function eventsOfDay(d) {
  const dayStart = new Date(d)
  dayStart.setHours(0, 0, 0, 0)
  const dayEnd = new Date(d)
  dayEnd.setHours(23, 59, 59, 999)
  return props.events.filter((e) => {
    const start = new Date(e.start_at)
    const end = new Date(e.end_at)
    return (start <= dayEnd && end >= dayStart) || e.all_day
  })
}

function holidaysOfDay(d) {
  return holidaysForDate(d, props.holidays || [])
}

function isHoliday(d) {
  return holidaysOfDay(d).length > 0
}

function holidayNamesOfDay(d) {
  return holidaysOfDay(d).map(holiday => holiday?.name).filter(Boolean).join(', ')
}

function dayNumberClass(d) {
  if (isToday(d))
    return 'text-emerald-600'
  if (!isCurrentMonth(d))
    return 'text-gray-300'
  if (isHoliday(d) || isWeekend(d))
    return 'text-red-600'
  return 'text-gray-700'
}

function dayNumberWithEventsClass(d) {
  if (isToday(d))
    return 'text-emerald-600 border-emerald-200 bg-emerald-50'
  if (!isCurrentMonth(d))
    return 'text-gray-300 border-gray-100'
  if (isHoliday(d) || isWeekend(d))
    return 'text-red-600 border-red-200 bg-red-50/30'
  return 'text-gray-700 border-gray-200'
}

function isToday(d) {
  const today = new Date()
  return ymd(d) === ymd(today)
}
function isCurrentMonth(d) {
  if (!isHijri.value) {
    return parseYMD(props.date).getMonth() === d.getMonth()
  }
  const info = baseHijri.value
  if (!info)
    return false
  const dayInfo = gregorianToHijri(d)
  return dayInfo.year === info.year && dayInfo.month === info.month
}
function isWeekend(d) {
  const day = d.getDay()
  return day === 0 || day === 6
}

function primaryDayNumber(d) {
  if (isHijri.value) {
    return gregorianToHijri(d).day
  }
  return d.getDate()
}

function formatGregorianLabel(d) {
  try {
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
  }
  catch {
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short' })
  }
}
</script>

<template>
  <div class="rounded-xl overflow-hidden">
    <div class="grid grid-cols-7 bg-emerald-600 text-center text-xs font-semibold uppercase tracking-wide text-white sm:text-sm md:text-base">
      <div
        v-for="w in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']"
        :key="w"
        class="p-2"
      >
        {{ w }}
      </div>
    </div>
    <div class="grid grid-cols-7 divide-x divide-gray-200 border-t">
      <template v-for="(week, wi) in weeks" :key="wi">
        <div
          v-for="(d, di) in week"
          :key="di"
          class="min-h-[110px] border-b border-gray-200 bg-white p-1.5 sm:min-h-[130px] sm:p-2"
        >
          <DayCell :date="d" :is-today="isToday(d)" :muted="!isCurrentMonth(d)" @click="() => emit('select-day', ymd(d))">
            <template #header>
              <div class="relative pt-3 pr-8">
                <button
                  class="absolute right-0 top-0 text-xs font-bold leading-none text-emerald-600 transition hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm"
                  :disabled="!props.canCreate"
                  @click.stop="() => handleOpenCreate(ymd(d))"
                >
                  +
                </button>
                <div
                  v-if="isHijri"
                  class="absolute right-0 top-7 text-right text-[10px] font-semibold uppercase tracking-wide text-emerald-500 sm:text-xs"
                >
                  {{ formatGregorianLabel(d) }}
                </div>
                <div
                  v-if="eventsOfDay(d).length === 0"
                  class="select-none text-4xl font-extrabold leading-none sm:text-5xl" :class="[dayNumberClass(d)]"
                >
                  <span>{{ primaryDayNumber(d) }}</span>
                </div>
                <div
                  v-else
                  class="inline-flex select-none items-center justify-center rounded-lg px-2 py-1 text-xl font-extrabold border sm:text-2xl" :class="[dayNumberWithEventsClass(d)]"
                >
                  {{ primaryDayNumber(d) }}
                </div>
                <div v-if="holidaysOfDay(d).length" class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-red-500">
                  {{ holidayNamesOfDay(d) }}
                </div>
              </div>
            </template>
            <div class="mt-1 space-y-1">
              <template v-for="(e, i) in eventsOfDay(d).slice(0, 3)" :key="`${e.id}:${i}`">
                <EventChip
                  :event="e"
                  :class="props.canEdit ? '' : 'opacity-60'"
                  @click="() => handleOpenEdit(e)"
                />
              </template>
              <div v-if="eventsOfDay(d).length > 3" class="text-[10px] text-gray-500">
                +{{ eventsOfDay(d).length - 3 }} lainnya
              </div>
            </div>
          </DayCell>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
</style>

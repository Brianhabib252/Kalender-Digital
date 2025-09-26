<script setup>
import { computed } from 'vue'
import DayCell from './DayCell.vue'
import EventChip from './EventChip.vue'

const props = defineProps({
  date: { type: String, required: true },
  events: { type: Array, default: () => [] },
  canCreate: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
})

const emit = defineEmits(['select-day','open-create','open-edit'])

function startOfWeek(d) {
  const x = new Date(d); const day = (x.getDay()+6)%7; x.setDate(x.getDate()-day); x.setHours(0,0,0,0); return x
}
function endOfWeek(d) { const x = startOfWeek(d); x.setDate(x.getDate()+6); x.setHours(23,59,59,999); return x }
function startOfMonth(d) { const x = new Date(d); x.setDate(1); x.setHours(0,0,0,0); return x }
function endOfMonth(d) { const x = new Date(d); x.setMonth(x.getMonth()+1,0); x.setHours(23,59,59,999); return x }

function parseYMD(s) {
  if (!s) return new Date()
  const [y, m, da] = s.split('-').map(n => parseInt(n, 10))
  return new Date(y, (m || 1) - 1, da || 1)
}

function ymd(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const da = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${da}`
}

function handleOpenCreate(day) {
  if (!props.canCreate) return
  emit('open-create', day)
}

function handleOpenEdit(event) {
  emit('open-edit', event)
}

const weeks = computed(() => {
  const base = parseYMD(props.date)
  const sm = startOfMonth(base)
  const em = endOfMonth(base)
  const gridStart = startOfWeek(sm)
  const gridEnd = endOfWeek(em)
  const days = []
  const cursor = new Date(gridStart)
  while (cursor <= gridEnd) {
    days.push(new Date(cursor))
    cursor.setDate(cursor.getDate()+1)
  }
  const rows = []
  for (let i=0; i<days.length; i+=7) rows.push(days.slice(i,i+7))
  return rows
})

function eventsOfDay(d) {
  const dayStart = new Date(d); dayStart.setHours(0,0,0,0)
  const dayEnd = new Date(d); dayEnd.setHours(23,59,59,999)
  return props.events.filter(e => {
    const s = new Date(e.start_at)
    const n = new Date(e.end_at)
    return (s <= dayEnd && n >= dayStart) || e.all_day
  })
}

function isToday(d) { const t = new Date(); return ymd(d) === ymd(t) }
function isCurrentMonth(d) { return parseYMD(props.date).getMonth() === d.getMonth() }
function isWeekend(d) { const g = d.getDay(); return g === 0 || g === 6 }
</script>

<template>
  <div class="rounded-xl overflow-hidden">
    <div class="grid grid-cols-7 bg-emerald-600 text-center text-xs font-semibold uppercase tracking-wide text-white sm:text-sm md:text-base">
      <div
        class="p-2"
        v-for="w in ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']"
        :key="w"
      >{{ w }}</div>
    </div>
    <div class="grid grid-cols-7 divide-x divide-gray-200 border-t">
      <template v-for="(week,wi) in weeks" :key="wi">
        <div
          v-for="(d,di) in week"
          :key="di"
          class="min-h-[110px] border-b border-gray-200 bg-white p-1.5 sm:min-h-[130px] sm:p-2"
        >
          <DayCell :date="d" :is-today="isToday(d)" :muted="!isCurrentMonth(d)" @click="() => emit('select-day', ymd(d))">
            <template #header>
              <div class="relative pt-3 pr-8">
                <button
                  class="absolute right-0 top-0 text-[10px] font-semibold text-emerald-600 transition hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 sm:text-[11px]"
                  :disabled="!props.canCreate"
                  @click.stop="() => handleOpenCreate(ymd(d))"
                >
                  + Buat
                </button>
                <div
                  v-if="eventsOfDay(d).length === 0"
                  :class="[
                    'select-none text-4xl font-extrabold leading-none sm:text-5xl',
                    isToday(d)
                      ? 'text-emerald-600'
                      : (!isCurrentMonth(d)
                        ? 'text-gray-300'
                        : (isWeekend(d) ? 'text-red-600' : 'text-gray-700'))
                  ]"
                >{{ d.getDate() }}</div>
                <div
                  v-else
                  :class="[
                    'inline-flex select-none items-center justify-center rounded-lg px-2 py-1 text-xl font-extrabold border sm:text-2xl',
                    isToday(d)
                      ? 'text-emerald-600 border-emerald-200 bg-emerald-50'
                      : (!isCurrentMonth(d)
                        ? 'text-gray-300 border-gray-100'
                        : (isWeekend(d)
                          ? 'text-red-600 border-red-200 bg-red-50/30'
                          : 'text-gray-700 border-gray-200'))
                  ]"
                >{{ d.getDate() }}</div>
              </div>
            </template>
            <div class="mt-1 space-y-1">
              <template v-for="(e,i) in eventsOfDay(d).slice(0,3)" :key="e.id + ':' + i">
                <EventChip
                  :event="e"
                  :class="props.canEdit ? '' : 'opacity-60'"
                  @click="() => handleOpenEdit(e)"
                />
              </template>
              <div v-if="eventsOfDay(d).length > 3" class="text-[10px] text-gray-500">+{{ eventsOfDay(d).length-3 }} lainnya</div>
            </div>
          </DayCell>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
</style>

<script setup>
import { ref, computed, watchEffect } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import MonthView from '../../Components/Calendar/MonthView.vue'
import DayView from '../../Components/Calendar/DayView.vue'
import SidebarDayList from '../../Components/Calendar/SidebarDayList.vue'
import EventFormModal from '../../Components/Calendar/EventFormModal.vue'
import LoadingOverlay from '../../Components/UI/LoadingOverlay.vue'

const props = defineProps({
  today: String,
  view: { type: String, default: 'month' },
  date: { type: String, default: null },
  divisionOptions: { type: Array, default: () => [] },
})

const currentView = ref(props.view || 'month')
const currentDate = ref(props.date || props.today)
const selectedDay = ref(null)
const selectedDivisionIds = ref([])
const q = ref('')
const events = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingEvent = ref(null)

function startOfWeek(date) {
  const d = new Date(date)
  const day = (d.getDay() + 6) % 7 // Mon=0
  d.setDate(d.getDate() - day)
  d.setHours(0,0,0,0)
  return d
}

function endOfWeek(date) {
  const d = startOfWeek(date)
  d.setDate(d.getDate() + 6)
  d.setHours(23,59,59,999)
  return d
}

function startOfMonth(date) {
  const d = new Date(date); d.setDate(1); d.setHours(0,0,0,0); return d
}

function endOfMonth(date) {
  const d = new Date(date); d.setMonth(d.getMonth()+1, 0); d.setHours(23,59,59,999); return d
}

function parseYMD(s) {
  if (!s) return new Date()
  const [y,m,d] = s.split('-').map(n => parseInt(n,10))
  return new Date(y, (m||1)-1, d||1)
}

function ymd(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth()+1).padStart(2,'0')
  const da = String(d.getDate()).padStart(2,'0')
  return `${y}-${m}-${da}`
}

const range = computed(() => {
  const d = parseYMD(currentDate.value)
  if (currentView.value === 'day') {
    const s = new Date(d); s.setHours(0,0,0,0);
    const e = new Date(d); e.setHours(23,59,59,999);
    return { start: s, end: e }
  }
  if (currentView.value === 'week') {
    return { start: startOfWeek(d), end: endOfWeek(d) }
  }
  // month with 1 week buffer either side
  const sm = startOfMonth(d)
  const em = endOfMonth(d)
  const s = startOfWeek(sm); s.setDate(s.getDate() - 7)
  const e = endOfWeek(em); e.setDate(e.getDate() + 7)
  return { start: s, end: e }
})

// counts for selected date's week and month
const weeklyCount = computed(() => {
  const base = new Date(currentDate.value)
  const ws = startOfWeek(base)
  const we = endOfWeek(base)
  return events.value.filter(e => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= we && n >= ws) || e.all_day
  }).length
})

const monthlyCount = computed(() => {
  const base = new Date(currentDate.value)
  const ms = startOfMonth(base)
  const me = endOfMonth(base)
  return events.value.filter(e => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= me && n >= ms) || e.all_day
  }).length
})

const todaysCount = computed(() => {
  const ds = new Date(props.today + 'T00:00:00')
  const de = new Date(props.today + 'T23:59:59')
  return events.value.filter(e => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= de && n >= ds) || e.all_day
  }).length
})

// Month title for the current calendar context
const monthTitle = computed(() => {
  const d = parseYMD(currentDate.value)
  try {
    return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
  } catch (e) {
    return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
  }
})

// Display date in DD/MM/YYYY for header pill
const displayDate = computed(() => {
  const d = parseYMD(currentDate.value)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yy = d.getFullYear()
  return `${dd}/${mm}/${yy}`
})

async function fetchEvents() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('start', range.value.start.toISOString())
    params.set('end', range.value.end.toISOString())
    if (q.value) params.set('q', q.value)
    for (const id of selectedDivisionIds.value) params.append('division', id)
    const res = await fetch(`/api/events?${params.toString()}`)
    const json = await res.json()
    events.value = json.data || []
  } finally {
    loading.value = false
  }
}

watchEffect(() => {
  fetchEvents()
  // sync URL
  router.visit(`/calendar`, { method: 'get', data: {
    view: currentView.value,
    date: currentDate.value,
  }, replace: true, preserveState: true, preserveScroll: true })
})

function go(delta, unit) {
  const d = parseYMD(currentDate.value)
  if (unit === 'day') d.setDate(d.getDate() + delta)
  if (unit === 'week') d.setDate(d.getDate() + delta*7)
  if (unit === 'month') d.setMonth(d.getMonth() + delta)
  currentDate.value = ymd(d)
}

function openCreate(slotDate, slotStartAt) {
  editingEvent.value = null
  selectedDay.value = slotDate || currentDate.value
  showForm.value = true
}

function openEdit(evt) {
  editingEvent.value = evt
  showForm.value = true
}

function onSaved() { showForm.value = false; fetchEvents() }

async function onDelete(evt) {
  if (!evt?.id) return
  if (!confirm('Hapus kegiatan ini?')) return
  const res = await fetch(`/api/events/${evt.id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } })
  if (!res.ok) {
    try {
      const data = await res.json(); alert(data?.message || 'Gagal menghapus kegiatan')
    } catch (e) { const t = await res.text(); alert(t || 'Gagal menghapus kegiatan') }
    return
  }
  fetchEvents()
}
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="max-w-7xl mx-auto space-y-4">
      <!-- Dashboard header -->
      <div class="rounded-2xl bg-gradient-to-r from-emerald-600 to-green-600 px-5 py-6 shadow-md transition-all duration-300 hover:shadow-xl">
        <div class="text-white space-y-3">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-16 h-16 md:w-20 md:h-20">
                <path d="M6 2a1 1 0 0 1 1 1v1h10V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 1 1 2 0v1zm14 6H4v11h16V8zM4 6h16V5h-1v1a1 1 0 1 1-2 0V5H7v1a1 1 0 1 1-2 0V5H4v1z" />
              </svg>
              <div>
                <div class="text-2xl md:text-3xl font-bold">Pengadilan Tinggi Agama Surabaya</div>
                <div class="text-white/80 text-base">Jadwal kegiatan terpusat dan transparan</div>
              </div>
            </div>
            <div class="flex items-center gap-3 text-sm md:text-base flex-nowrap">
              <button class="h-11 w-48 inline-flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-900 shadow-sm hover:bg-emerald-50 hover:border-emerald-300 active:scale-95 transition" @click="currentDate = today">Hari Ini</button>
              <input type="date" v-model="currentDate" class="h-11 w-48 px-4 rounded-xl bg-white border border-gray-200 text-gray-900 shadow-sm focus:ring-4 focus:ring-emerald-300" />
              <input v-model="q" placeholder="Cari judul/lokasi/deskripsi" class="h-11 w-48 px-4 rounded-xl bg-white border border-gray-200 text-gray-900 shadow-sm focus:ring-4 focus:ring-emerald-300" />
              <button class="h-11 w-48 inline-flex items-center justify-center rounded-xl bg-white border border-gray-200 text-emerald-700 shadow-sm hover:bg-emerald-50 hover:border-emerald-300 active:scale-95 transition" @click="openCreate()">Tambah Kegiatan</button>
            </div>
          </div>
          <div class="flex gap-4 text-white text-xl">
            <!-- Hari Ini -->
            <div class="flex-1 rounded-2xl bg-white/10 px-6 py-5 transition-all duration-300 hover:bg-white/20 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7">
                  <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                </svg>
                <div class="opacity-90 font-medium">Hari Ini</div>
              </div>
              <div class="text-4xl font-extrabold leading-none tabular-nums">{{ todaysCount }}</div>
            </div>
            <!-- Minggu Ini -->
            <div class="flex-1 rounded-2xl bg-white/10 px-6 py-5 transition-all duration-300 hover:bg-white/20 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7">
                  <path d="M19 3H5a2 2 0 0 0-2 2v3h18V5a2 2 0 0 0-2-2zM3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10H3v9z"/>
                </svg>
                <div class="opacity-90 font-medium">Minggu Ini</div>
              </div>
              <div class="text-4xl font-extrabold leading-none tabular-nums">{{ weeklyCount }}</div>
            </div>
            <!-- Bulan Ini -->
            <div class="flex-1 rounded-2xl bg-white/10 px-6 py-5 transition-all duration-300 hover:bg-white/20 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7">
                  <path d="M7 11h10v2H7v-2zm0 4h10v2H7v-2zM6 3a2 2 0 0 0-2 2v1h16V5a2 2 0 0 0-2-2H6zm14 5H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                </svg>
                <div class="opacity-90 font-medium">Bulan Ini</div>
              </div>
              <div class="text-4xl font-extrabold leading-none tabular-nums">{{ monthlyCount }}</div>
            </div>
          </div>
        </div>
      </div>

      

      <!-- Calendar grid -->
      <div class="bg-white rounded-2xl shadow-sm border p-5 relative transition-all duration-300 hover:shadow-lg">
        <div class="relative flex items-center justify-center mb-3">
          <button class="absolute left-0 inline-flex items-center justify-center h-12 w-12 rounded-full bg-emerald-50 text-emerald-700 hover:bg-emerald-100 active:scale-95 transition" @click="go(-1, 'month')" aria-label="Bulan sebelumnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
          </button>
          <div class="text-2xl md:text-3xl font-bold text-emerald-600 capitalize">{{ monthTitle }}</div>
          <button class="absolute right-0 inline-flex items-center justify-center h-12 w-12 rounded-full bg-emerald-50 text-emerald-700 hover:bg-emerald-100 active:scale-95 transition" @click="go(1, 'month')" aria-label="Bulan berikutnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M8.25 4.5 15.75 12l-7.5 7.5"/></svg>
          </button>
        </div>
        <div class="mb-3 text-center">
          <div class="flex flex-wrap justify-center gap-2">
            <label v-for="d in divisionOptions" :key="d.id" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-base cursor-pointer select-none transition-all hover:shadow-sm"
                   :class="selectedDivisionIds.includes(d.id) ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-white border-gray-300 text-gray-700'">
              <input type="checkbox" :value="d.id" v-model="selectedDivisionIds" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-400 h-5 w-5" />
              <span>{{ d.name }}</span>
            </label>
          </div>
        </div>
        <MonthView
          :date="currentDate"
          :events="events"
          @select-day="d => { currentDate = d; selectedDay = d }"
          @open-create="openCreate"
          @open-edit="openEdit"
        />
        <LoadingOverlay v-if="loading" message="Memuat kegiatan…" />
      </div>

      <!-- Selected day's activities (single card) -->
<div class="bg-white rounded-2xl shadow-sm border p-5 relative transition-all duration-300 hover:shadow-lg">
  <div class="mb-3 text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-emerald-600 to-green-600 bg-clip-text text-transparent">Kegiatan {{ selectedDay || currentDate }}</div>
  <SidebarDayList bare :date="selectedDay || currentDate" :events="events" @open-edit="openEdit" @delete-event="onDelete" />
  <LoadingOverlay v-if="loading" message="Memuat kegiatan..." />
</div>

      <!-- Selected day's timeline 06:00 - 18:00 -->
      <div class="bg-white rounded-2xl shadow-sm border p-5 relative transition-all duration-300 hover:shadow-lg">
        <div class="px-2 py-1 text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-emerald-600 to-green-600 bg-clip-text text-transparent">
          Timeline (06:00 - 18:00) — {{ selectedDay || currentDate }}
        </div>
        <div class="mt-4">
          <DayView :date="selectedDay || currentDate" :events="events" :start-hour="6" :end-hour="18" @open-create="() => openCreate(selectedDay || currentDate)" @open-edit="openEdit" />
        </div>
        <LoadingOverlay v-if="loading" message="Memuat timeline." />
      </div>

<EventFormModal
  v-if="showForm"
  :date="selectedDay || currentDate"
  :event="editingEvent"
  :division-options="divisionOptions"
  @close="showForm=false"
  @saved="onSaved"
/>
    </div>
  </div>
</template>

<style scoped>
</style>






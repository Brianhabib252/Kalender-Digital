<script setup>
import { ref, computed, watchEffect, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import MonthView from '../../Components/Calendar/MonthView.vue'
import DayView from '../../Components/Calendar/DayView.vue'
import SidebarDayList from '../../Components/Calendar/SidebarDayList.vue'
import EventFormModal from '../../Components/Calendar/EventFormModal.vue'
import LoadingOverlay from '../../Components/UI/LoadingOverlay.vue'
import ProfileSettingsModal from '../../Components/Profile/ProfileSettingsModal.vue'
import SuccessPopup from '../../Components/UI/SuccessPopup.vue'

const props = defineProps({
  today: String,
  view: { type: String, default: 'month' },
  date: { type: String, default: null },
  divisionOptions: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props?.auth?.user ?? null)
const showProfileModal = ref(false)

const role = computed(() => user.value?.role ?? 'viewer')
const canCreate = computed(() => ['admin', 'editor'].includes(role.value))
const canEdit = computed(() => role.value !== 'viewer')
const canDelete = computed(() => role.value === 'admin' || role.value === 'editor')

let sanctumBootstrapped = false

async function ensureSanctumCookie() {
  if (sanctumBootstrapped) return
  await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  sanctumBootstrapped = true
}

function xsrfToken() {
  const value = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1]

  return value ? decodeURIComponent(value) : ''
}


const currentView = ref(props.view || 'month')
const currentDate = ref(props.date || props.today)
const selectedDay = ref(null)
const selectedDivisionIds = ref([])
const q = ref('')
const events = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingEvent = ref(null)
const showSuccess = ref(false)
const successMessage = ref('')
let successTimer = null

onBeforeUnmount(() => {
  clearTimeout(successTimer)
})

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

function openProfileModal() {
  showProfileModal.value = true
}

function closeProfileModal() {
  showProfileModal.value = false
}

async function fetchEvents() {
  loading.value = true
  try {
    await ensureSanctumCookie()
    const params = new URLSearchParams()
    params.set('start', range.value.start.toISOString())
    params.set('end', range.value.end.toISOString())
    if (q.value) params.set('q', q.value)
    for (const id of selectedDivisionIds.value) params.append('division', id)
    const res = await fetch(`/api/events?${params.toString()}`, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
      },
    })
    const json = await res.json()
    events.value = json.data || []
  } finally {
    loading.value = false
  }
}

watchEffect(() => {
  // Touch reactive dependencies synchronously so changes trigger this effect
  const _view = currentView.value
  const _date = currentDate.value
  const _q = q.value
  const _divKey = selectedDivisionIds.value.join(',')

  fetchEvents()
  // sync URL (only view/date go to query)
  router.visit(`/calendar`, { method: 'get', data: {
    view: _view,
    date: _date,
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
  if (!canCreate.value) return
  editingEvent.value = null
  selectedDay.value = slotDate || currentDate.value
  showForm.value = true
}

function openEdit(evt) {
  editingEvent.value = evt
  showForm.value = true
}

function triggerSuccess(message) {
  successMessage.value = message
  showSuccess.value = true
  clearTimeout(successTimer)
  successTimer = setTimeout(() => {
    showSuccess.value = false
  }, 2000)
}

function onSaved(kind = 'saved') {
  showForm.value = false
  fetchEvents()
  if (kind === 'created') {
    triggerSuccess('Kegiatan berhasil dibuat')
  } else if (kind === 'updated') {
    triggerSuccess('Kegiatan berhasil diperbarui')
  } else {
    triggerSuccess('Perubahan berhasil disimpan')
  }
}

async function onDelete(evt) {
  if (!canDelete.value) return
  if (!evt?.id) return
  if (!confirm('Hapus kegiatan ini?')) return
  await ensureSanctumCookie()
  const res = await fetch(`/api/events/${evt.id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
    },
  })
  if (!res.ok) {
    if ([401, 403, 419].includes(res.status)) {
      alert('Anda tidak memiliki akses untuk mengubah atau menghapus data ini')
      return
    }
    try {
      const data = await res.json(); alert(data?.message || 'Gagal menghapus kegiatan')
    } catch (e) { const t = await res.text(); alert(t || 'Gagal menghapus kegiatan') }
    return
  }
  fetchEvents()
  triggerSuccess('Kegiatan berhasil dihapus')
}
</script>

<template>
  <section class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-sky-50 px-4 py-10 md:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
      <!-- Dashboard header -->
      <div class="rounded-3xl border border-emerald-100 bg-gradient-to-r from-emerald-100 via-emerald-50 to-white px-6 py-8 shadow-[0_35px_120px_-60px_rgba(16,185,129,0.45)] transition-all duration-300 hover:shadow-[0_45px_160px_-70px_rgba(16,185,129,0.5)]">
        <div class="space-y-6 text-emerald-900">
          <div class="grid gap-6 items-stretch xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex h-full items-start gap-5">
              <div class="hidden sm:flex h-20 w-20 items-center justify-center rounded-2xl bg-emerald-200/70 text-emerald-700 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10">
                  <path d="M6 2a1 1 0 0 1 1 1v1h10V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 1 1 2 0v1zm14 6H4v11h16V8zM4 6h16V5h-1v1a1 1 0 1 1-2 0V5H7v1a1 1 0 1 1-2 0V5H4v1z" />
                </svg>
              </div>
              <div class="space-y-3">
                <p class="text-3xl font-semibold uppercase tracking-[0.45em] text-emerald-700 md:text-4xl">Kalender Digital</p>
                <h1 class="text-3xl font-semibold leading-tight text-emerald-900 md:text-4xl">Pengadilan Tinggi Agama Surabaya</h1>
                <p class="max-w-2xl text-base text-emerald-700 md:text-lg">Jadwal kegiatan terpusat dan transparan demi koordinasi yang rapi di setiap divisi.</p>
              </div>
            </div>

            <div v-if="user" class="flex h-full w-full max-w-xs flex-col items-center rounded-2xl border border-emerald-200 bg-white/90 px-5 py-5 text-center shadow-[0_15px_60px_-50px_rgba(16,185,129,0.6)] backdrop-blur-sm">
              <div class="space-y-2">
                <div class="text-xl font-semibold text-emerald-700">{{ user.name }}</div>
              </div>
              <button
                type="button"
                class="mt-6 inline-flex w-full items-center justify-center rounded-lg border border-emerald-200 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-300/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300"
                @click="openProfileModal"
              >
                Kelola Profil
              </button>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button class="h-11 w-full rounded-xl border border-emerald-200 bg-white text-sm font-semibold text-emerald-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95" @click="currentDate = today">
              Hari Ini
            </button>
            <label class="flex h-11 w-full items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-white px-4 text-sm text-emerald-700 shadow-sm focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-200">
              <span class="hidden sm:inline whitespace-nowrap text-emerald-500/80">Pilih tanggal</span>
              <input type="date" v-model="currentDate" class="h-8 w-full rounded-md border border-transparent bg-transparent text-right focus:border-emerald-300 focus:outline-none" />
            </label>
            <div class="flex h-11 w-full items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 text-sm shadow-sm focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-200">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 text-emerald-500"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" stroke-linejoin="round" d="m16.5 16.5 3 3"/></svg>
              <input v-model="q" placeholder="Cari judul/lokasi/deskripsi" class="h-full w-full bg-transparent text-emerald-700 placeholder:text-emerald-400 focus:outline-none" />
            </div>
            <button
              class="h-11 w-full rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 text-sm font-semibold text-white shadow-lg shadow-emerald-300/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!canCreate"
              @click="openCreate()"
            >
              Tambah Kegiatan
            </button>
          </div>

          <div class="grid gap-4 text-emerald-800 text-xl sm:grid-cols-2 xl:grid-cols-3">
            <!-- Hari Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
                <div class="opacity-90 font-medium">Hari Ini</div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-emerald-600">{{ todaysCount }}</div>
            </div>
            <!-- Minggu Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-sky-100 text-sky-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M19 3H5a2 2 0 0 0-2 2v3h18V5a2 2 0 0 0-2-2zM3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10H3v9z"/></svg></span>
                <div class="opacity-90 font-medium">Minggu Ini</div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-sky-600">{{ weeklyCount }}</div>
            </div>
            <!-- Bulan Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-amber-100 text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M7 11h10v2H7v-2zm0 4h10v2H7v-2zM6 3a2 2 0 0 0-2 2v1h16V5a2 2 0 0 0-2-2H6zm14 5H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></span>
                <div class="opacity-90 font-medium">Bulan Ini</div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-amber-600">{{ monthlyCount }}</div>
            </div>
          </div>
        </div>
      </div>

      

      <!-- Calendar grid -->
      <div class="relative rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)]">
        <div class="relative flex items-center justify-center mb-3">
          <button class="absolute left-0 inline-flex items-center justify-center h-12 w-12 rounded-full border border-emerald-200 bg-emerald-100 text-emerald-700 transition hover:bg-emerald-200 active:scale-95" @click="go(-1, 'month')" aria-label="Bulan sebelumnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
          </button>
          <div class="text-2xl md:text-3xl font-bold text-emerald-600 capitalize">{{ monthTitle }}</div>
          <button class="absolute right-0 inline-flex items-center justify-center h-12 w-12 rounded-full border border-emerald-200 bg-emerald-100 text-emerald-700 transition hover:bg-emerald-200 active:scale-95" @click="go(1, 'month')" aria-label="Bulan berikutnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M8.25 4.5 15.75 12l-7.5 7.5"/></svg>
          </button>
        </div>
        <div class="mb-4 text-center">
          <div class="flex flex-wrap justify-center gap-2">
            <label
              v-for="d in divisionOptions" :key="d.id"
              class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium cursor-pointer select-none transition shadow-sm"
              :class="selectedDivisionIds.includes(d.id)
                ? 'bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 border-transparent text-white shadow-md'
                : 'bg-white text-emerald-600 border-emerald-200 hover:border-emerald-300 hover:bg-emerald-50'"
            >
              <input type="checkbox" :value="d.id" v-model="selectedDivisionIds" class="h-4 w-4 rounded-full border-emerald-200 text-emerald-500 focus:ring-emerald-400" />
              <span>{{ d.name }}</span>
            </label>
          </div>
        </div>
        <MonthView
          :date="currentDate"
          :events="events"
          :can-create="canCreate"
          :can-edit="canEdit"
          @select-day="d => { currentDate = d; selectedDay = d }"
          @open-create="openCreate"
          @open-edit="openEdit"
        />
        <LoadingOverlay v-if="loading" message="Memuat data..." />
      </div>

      <!-- Selected day's activities (single card) -->
      <div class="relative rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)]">
        <div class="mb-3 text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 bg-clip-text text-transparent">Kegiatan {{ selectedDay || currentDate }}</div>
        <SidebarDayList
          bare
          :date="selectedDay || currentDate"
          :events="events"
          :can-edit="canEdit"
          :can-delete="canDelete"
          @open-edit="openEdit"
          @delete-event="onDelete"
        />
        <LoadingOverlay v-if="loading" message="Memuat data..." />
      </div>

      <!-- Selected day's timeline 06:00 - 18:00 -->
      <div class="relative rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)]">
        <div class="px-2 py-1 text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 bg-clip-text text-transparent">
          Timeline (06:00 - 18:00) - {{ selectedDay || currentDate }}
        </div>
        <div class="mt-4">
          <DayView
            :date="selectedDay || currentDate"
            :events="events"
            :start-hour="6"
            :end-hour="18"
            :can-create="canCreate"
            :can-edit="canEdit"
            @open-create="() => openCreate(selectedDay || currentDate)"
            @open-edit="openEdit"
          />
        </div>
        <LoadingOverlay v-if="loading" message="Memuat data..." />
      </div>

      <ProfileSettingsModal
        v-if="showProfileModal && user"
        :user="user"
        @close="closeProfileModal"
      />

      <SuccessPopup :visible="showSuccess" :message="successMessage" />

      <EventFormModal
        v-if="showForm"
        :date="selectedDay || currentDate"
        :event="editingEvent"
        :division-options="divisionOptions"
        :can-edit="canEdit"
        :can-delete="canDelete"
        @close="showForm=false"
        @saved="onSaved"
      />
    </div>
  </section>
</template>

<style scoped>
</style>

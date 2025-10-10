<script setup>
import { router } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { HIJRI_MONTH_NAMES } from '@/lib/hijri.js'
import { describeHoliday, GREGORIAN_MONTH_NAMES } from '@/lib/holidays.js'

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => ['inactive', 'viewer', 'editor', 'admin'] },
  adminEmail: { type: String, default: '' },
  holidays: { type: Array, default: () => [] },
})

// Local editable copy
const rows = ref(props.users.map(u => ({ ...u })))
const holidays = ref((props.holidays || []).map(h => ({ ...h })))

const saving = reactive({})
const notices = ref('')
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const userLogs = ref([])
const eventLogs = ref([])
const loadingUserLogs = ref(false)
const loadingEventLogs = ref(false)
const selectedEventDate = ref(new Date().toISOString().slice(0, 10))

const holidayNotice = ref('')
const holidayError = ref('')
const holidaySaving = ref(false)
const deletingHolidayId = ref(null)

const holidayForm = reactive({
  name: '',
  calendarType: 'gregorian',
  gregorianMonth: '',
  gregorianDay: '',
  gregorianYear: '',
  hijriMonth: '',
  hijriDay: '',
  hijriYear: '',
})

const includesGregorian = computed(() => ['gregorian', 'both'].includes(holidayForm.calendarType))
const includesHijri = computed(() => ['hijri', 'both'].includes(holidayForm.calendarType))

const hijriMonthOptions = HIJRI_MONTH_NAMES.map((label, index) => ({ value: index + 1, label }))
const gregorianMonthOptions = GREGORIAN_MONTH_NAMES.map((label, index) => ({ value: index + 1, label }))

const calendarTypeLabels = {
  gregorian: 'Masehi',
  hijri: 'Hijriah',
  both: 'Masehi & Hijriah',
}

const calendarTypeOrder = { gregorian: 0, hijri: 1, both: 2 }
let holidayCsrfReady = false

watch(() => props.holidays, (value) => {
  holidays.value = (value || []).map(h => ({ ...h }))
  sortHolidays()
})

async function fetchUserLogs() {
  loadingUserLogs.value = true
  try {
    const params = new URLSearchParams()
    if (selectedDate.value)
      params.set('date', selectedDate.value)
    const res = await fetch(`/admin/users/logs?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    userLogs.value = data?.data || []
  }
  finally {
    loadingUserLogs.value = false
  }
}

async function fetchEventLogs() {
  loadingEventLogs.value = true
  try {
    const params = new URLSearchParams()
    if (selectedEventDate.value)
      params.set('date', selectedEventDate.value)
    const res = await fetch(`/admin/events/logs?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    eventLogs.value = data?.data || []
  }
  finally {
    loadingEventLogs.value = false
  }
}

onMounted(() => {
  fetchUserLogs()
  fetchEventLogs()
  sortHolidays()
})

function goBack() {
  router.visit('/calendar', { preserveScroll: true })
}

function newChange(item, key) {
  const pair = item?.changes?.[key]
  if (Array.isArray(pair))
    return pair[1] ?? pair[0] ?? null
  return null
}

function displayDivisions(item) {
  const v = newChange(item, 'division_ids')
  if (Array.isArray(v))
    return v.length ? v.join(', ') : '[]'
  return v ?? '-'
}

function holidayTypeLabel(type) {
  return calendarTypeLabels[type] ?? 'Tidak dikenal'
}

function holidayDescriptionText(holiday) {
  return describeHoliday(holiday, HIJRI_MONTH_NAMES)
}

function sortHolidays() {
  holidays.value.sort((a, b) => {
    const typeDiff = (calendarTypeOrder[a?.calendar_type] ?? 99) - (calendarTypeOrder[b?.calendar_type] ?? 99)
    if (typeDiff !== 0)
      return typeDiff

    const gmDiff = (a?.gregorian_month ?? 0) - (b?.gregorian_month ?? 0)
    if (gmDiff !== 0)
      return gmDiff

    const gdDiff = (a?.gregorian_day ?? 0) - (b?.gregorian_day ?? 0)
    if (gdDiff !== 0)
      return gdDiff

    const hmDiff = (a?.hijri_month ?? 0) - (b?.hijri_month ?? 0)
    if (hmDiff !== 0)
      return hmDiff

    const hdDiff = (a?.hijri_day ?? 0) - (b?.hijri_day ?? 0)
    if (hdDiff !== 0)
      return hdDiff

    return (a?.name || '').localeCompare(b?.name || '', 'id', { sensitivity: 'base' })
  })
}

function resetHolidayForm() {
  holidayForm.name = ''
  holidayForm.calendarType = 'gregorian'
  holidayForm.gregorianMonth = ''
  holidayForm.gregorianDay = ''
  holidayForm.gregorianYear = ''
  holidayForm.hijriMonth = ''
  holidayForm.hijriDay = ''
  holidayForm.hijriYear = ''
}

function askConfirmation(message) {
  // eslint-disable-next-line no-alert
  return window.confirm(message)
}

function toIntOrNull(value) {
  if (value === null || value === undefined || value === '')
    return null
  const parsed = Number.parseInt(String(value), 10)
  return Number.isNaN(parsed) ? null : parsed
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function xsrfToken() {
  const value = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1]
  return value ? decodeURIComponent(value) : ''
}

async function ensureHolidayCsrf() {
  if (holidayCsrfReady)
    return
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
  holidayCsrfReady = true
}

async function createHoliday() {
  if (holidaySaving.value)
    return
  holidayError.value = ''
  holidayNotice.value = ''

  if (!holidayForm.name.trim()) {
    holidayError.value = 'Nama hari libur wajib diisi.'
    return
  }

  const payload = {
    name: holidayForm.name.trim(),
    calendar_type: holidayForm.calendarType,
    gregorian_month: toIntOrNull(holidayForm.gregorianMonth),
    gregorian_day: toIntOrNull(holidayForm.gregorianDay),
    gregorian_year: toIntOrNull(holidayForm.gregorianYear),
    hijri_month: toIntOrNull(holidayForm.hijriMonth),
    hijri_day: toIntOrNull(holidayForm.hijriDay),
    hijri_year: toIntOrNull(holidayForm.hijriYear),
  }

  holidaySaving.value = true
  try {
    await ensureHolidayCsrf()
    const res = await fetch('/admin/holidays', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
        ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
      },
      body: JSON.stringify(payload),
    })
    if (!res.ok) {
      if (res.status === 422) {
        const data = await res.json().catch(() => ({}))
        const message = Object.values(data?.errors || {}).flat().join('\n') || data?.message || 'Gagal menambahkan hari libur.'
        holidayError.value = message
      }
      else if ([401, 403].includes(res.status)) {
        holidayError.value = 'Anda tidak memiliki izin untuk menambahkan tanggal merah.'
      }
      else {
        const text = await res.text().catch(() => '')
        holidayError.value = text || 'Gagal menambahkan hari libur.'
      }
      return
    }
    const data = await res.json().catch(() => ({}))
    if (data?.data) {
      holidays.value.push(data.data)
      sortHolidays()
      holidayNotice.value = 'Tanggal merah berhasil ditambahkan.'
      resetHolidayForm()
    }
  }
  catch (error) {
    console.error(error)
    holidayError.value = 'Tidak dapat terhubung ke server. Coba lagi nanti.'
  }
  finally {
    holidaySaving.value = false
  }
}

async function deleteHoliday(holiday) {
  if (!holiday?.id || deletingHolidayId.value === holiday.id)
    return
  holidayError.value = ''
  holidayNotice.value = ''
  if (!askConfirmation(`Hapus tanggal merah "${holiday.name}"?`)) {
    return
  }
  deletingHolidayId.value = holiday.id
  try {
    await ensureHolidayCsrf()
    const token = csrfToken() || xsrfToken()
    const res = await fetch(`/admin/holidays/${holiday.id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
        ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
      },
      body: JSON.stringify(token ? { _token: token } : {}),
    })
    if (!res.ok) {
      if ([401, 403].includes(res.status)) {
        holidayError.value = 'Anda tidak memiliki izin untuk menghapus tanggal merah.'
      }
      else {
        const text = await res.text().catch(() => '')
        holidayError.value = text || 'Gagal menghapus tanggal merah.'
      }
      return
    }
    holidays.value = holidays.value.filter(item => item.id !== holiday.id)
    holidayNotice.value = 'Tanggal merah dihapus.'
  }
  catch (error) {
    console.error(error)
    holidayError.value = 'Tidak dapat terhubung ke server. Coba lagi nanti.'
  }
  finally {
    deletingHolidayId.value = null
  }
}

async function saveRow(idx) {
  const u = rows.value[idx]
  if (!u)
    return
  if (saving[u.id])
    return
  saving[u.id] = true
  notices.value = ''
  router.put(`/admin/users/${u.id}`, {
    name: u.name,
    email: u.email,
    nip: u.nip || null,
    phone: u.phone || null,
    role: u.role,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      notices.value = 'Perubahan tersimpan.'
    },
    onError: (errors) => {
      const message = Object.values(errors || {}).flat().join('\n') || 'Gagal menyimpan perubahan'
      notices.value = message
    },
    onFinish: () => {
      saving[u.id] = false
    },
  })
}

function isSuperAdmin(u) {
  return u?.email && props.adminEmail && u.email === props.adminEmail
}
</script>

<template>
  <section class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-sky-50 px-4 py-10 md:px-8">
    <div class="mx-auto max-w-7xl space-y-8">
      <div class="rounded-3xl border border-emerald-100 bg-emerald-50/80 px-6 py-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="space-y-1">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-800">
              Manajemen Pengguna
            </h1>
            <p class="text-sm text-emerald-800/75">
              Kelola data akun, atur tanggal merah, dan pantau aktivitas terbaru tim admin.
            </p>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
              @click="goBack"
            >
              <span aria-hidden="true">&larr;</span>
              <span>Kembali ke Kalender</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="space-y-6">
          <section class="rounded-2xl border border-emerald-100 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-emerald-700 sm:text-xl">
                  Daftar Pengguna
                </h2>
                <p class="text-sm text-emerald-700/80">
                  Hanya admin yang dapat mengubah peran pengguna. Akun super admin tidak dapat diubah perannya.
                </p>
              </div>
              <div v-if="rows.length" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">
                {{ rows.length }} akun aktif
              </div>
            </div>
            <div v-if="notices" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
              {{ notices }}
            </div>
            <div class="mt-4 overflow-hidden rounded-xl border border-gray-100">
              <div class="overflow-x-auto">
                <table class="min-w-[52rem] divide-y divide-gray-200 text-sm">
                  <thead class="bg-emerald-50/60 text-gray-700">
                    <tr>
                      <th class="px-3 py-2 text-left font-semibold">
                        Nama
                      </th>
                      <th class="px-3 py-2 text-left font-semibold">
                        Email
                      </th>
                      <th class="px-3 py-2 text-left font-semibold">
                        NIP
                      </th>
                      <th class="px-3 py-2 text-left font-semibold">
                        Telepon
                      </th>
                      <th class="px-3 py-2 text-left font-semibold">
                        Peran
                      </th>
                      <th class="px-3 py-2">
                        <span class="sr-only">Aksi</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="(u, idx) in rows" :key="u.id" class="hover:bg-emerald-50/40">
                      <td class="px-3 py-2 align-top">
                        <input
                          v-model="u.name"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        >
                      </td>
                      <td class="px-3 py-2 align-top">
                        <input
                          v-model="u.email"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                          :disabled="isSuperAdmin(u)"
                        >
                      </td>
                      <td class="px-3 py-2 align-top">
                        <input
                          v-model="u.nip"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        >
                      </td>
                      <td class="px-3 py-2 align-top">
                        <input
                          v-model="u.phone"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        >
                      </td>
                      <td class="px-3 py-2 align-top">
                        <select
                          v-model="u.role"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                          :disabled="isSuperAdmin(u)"
                        >
                          <option v-for="r in props.roles" :key="r" :value="r">
                            {{ r }}
                          </option>
                        </select>
                      </td>
                      <td class="px-3 py-2 text-right">
                        <button
                          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                          :disabled="saving[u.id]"
                          @click="saveRow(idx)"
                        >
                          <span v-if="saving[u.id]" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent" />
                          <span>Simpan</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-emerald-700 sm:text-xl">
                  Tanggal Merah Kalender
                </h2>
                <p class="text-sm text-emerald-700/80">
                  Tambahkan hari libur nasional dan keagamaan agar kalender menandainya sebagai tanggal merah di tampilan Masehi maupun Hijriah.
                </p>
              </div>
              <div v-if="holidays.length" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">
                {{ holidays.length }} tanggal tersimpan
              </div>
            </div>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
              <form class="space-y-4 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 shadow-inner" @submit.prevent="createHoliday">
                <div class="space-y-2">
                  <label class="text-sm font-semibold text-emerald-700">Nama Hari Libur</label>
                  <input
                    v-model="holidayForm.name"
                    class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-emerald-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    placeholder="cth: Hari Kemerdekaan"
                    required
                  >
                </div>
                <div class="space-y-2">
                  <label class="text-sm font-semibold text-emerald-700">Jenis Kalender</label>
                  <select
                    v-model="holidayForm.calendarType"
                    class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-emerald-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  >
                    <option value="gregorian">
                      Masehi
                    </option>
                    <option value="hijri">
                      Hijriah
                    </option>
                    <option value="both">
                      Keduanya
                    </option>
                  </select>
                </div>
                <div v-if="includesGregorian" class="space-y-3 rounded-lg border border-emerald-100 bg-white/90 px-3 py-3 shadow-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-emerald-500">
                    Tanggal Masehi
                  </div>
                  <div class="grid gap-3 sm:grid-cols-3">
                    <select v-model="holidayForm.gregorianMonth" class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                      <option value="">
                        Bulan
                      </option>
                      <option v-for="opt in gregorianMonthOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                    <input
                      v-model="holidayForm.gregorianDay"
                      type="number"
                      min="1"
                      max="31"
                      class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Hari"
                    >
                    <input
                      v-model="holidayForm.gregorianYear"
                      type="number"
                      min="1900"
                      max="2100"
                      class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Tahun (opsional)"
                    >
                  </div>
                  <p class="text-xs text-emerald-600/70">
                    Kosongkan tahun jika tanggal berlaku setiap tahun.
                  </p>
                </div>
                <div v-if="includesHijri" class="space-y-3 rounded-lg border border-emerald-100 bg-white/90 px-3 py-3 shadow-sm">
                  <div class="text-xs font-semibold uppercase tracking-wide text-emerald-500">
                    Tanggal Hijriah
                  </div>
                  <div class="grid gap-3 sm:grid-cols-3">
                    <select v-model="holidayForm.hijriMonth" class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                      <option value="">
                        Bulan
                      </option>
                      <option v-for="opt in hijriMonthOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                    <input
                      v-model="holidayForm.hijriDay"
                      type="number"
                      min="1"
                      max="30"
                      class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Hari"
                    >
                    <input
                      v-model="holidayForm.hijriYear"
                      type="number"
                      min="1300"
                      max="1600"
                      class="rounded-lg border border-emerald-200 px-3 py-2 text-sm text-emerald-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Tahun (opsional)"
                    >
                  </div>
                  <p class="text-xs text-emerald-600/70">
                    Kosongkan tahun jika tanggal berlaku setiap tahun.
                  </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                  <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="holidaySaving"
                  >
                    <span v-if="holidaySaving" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-r-transparent" />
                    <span>{{ holidaySaving ? 'Menyimpan...' : 'Simpan Tanggal Merah' }}</span>
                  </button>
                  <button type="button" class="text-sm font-semibold text-emerald-600 hover:underline" @click="resetHolidayForm">
                    Reset
                  </button>
                </div>
                <div v-if="holidayError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600">
                  {{ holidayError }}
                </div>
                <div v-else-if="holidayNotice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-600">
                  {{ holidayNotice }}
                </div>
              </form>
              <div class="space-y-3 rounded-xl border border-emerald-100 bg-white/80 p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                  <h3 class="text-base font-semibold text-emerald-700">
                    Daftar Hari Libur
                  </h3>
                  <span v-if="holidays.length" class="text-xs font-semibold uppercase tracking-wide text-emerald-500">{{ holidays.length }} tanggal</span>
                </div>
                <div v-if="!holidays.length" class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/50 px-3 py-4 text-sm text-emerald-700/80">
                  Belum ada tanggal merah tersimpan.
                </div>
                <ul v-else class="space-y-3">
                  <li
                    v-for="holiday in holidays"
                    :key="holiday.id"
                    class="flex items-start justify-between gap-3 rounded-lg border border-emerald-100 bg-white px-3 py-3 shadow-sm transition hover:border-emerald-200"
                  >
                    <div class="space-y-1">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-emerald-700">{{ holiday.name }}</span>
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-600">
                          {{ holidayTypeLabel(holiday.calendar_type) }}
                        </span>
                      </div>
                      <div class="text-xs text-emerald-600/80">
                        {{ holidayDescriptionText(holiday) }}
                      </div>
                    </div>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-sm font-medium text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="deletingHolidayId === holiday.id"
                      @click="deleteHoliday(holiday)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M9 3a1 1 0 0 0-1 1v1H5.5A1.5 1.5 0 0 0 4 6.5V7h16v-.5A1.5 1.5 0 0 0 18.5 5H16V4a1 1 0 0 0-1-1H9zm10 5H5l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12z" /></svg>
                      <span>{{ deletingHolidayId === holiday.id ? 'Menghapus...' : 'Hapus' }}</span>
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-6">
          <section class="rounded-2xl border border-emerald-100 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-base font-semibold text-emerald-700">
                  Perubahan Pengguna
                </h2>
                <p class="text-xs text-emerald-700/70">
                  Filter berdasarkan tanggal untuk melihat aktivitas pengelolaan akun.
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <input
                  v-model="selectedDate"
                  type="date"
                  class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                >
                <button
                  class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                  @click="fetchUserLogs(); fetchEventLogs()"
                >
                  Tampilkan
                </button>
              </div>
            </div>
            <div class="mt-4 space-y-3">
              <div v-if="loadingUserLogs" class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700/80">
                Memuat log...
              </div>
              <div v-else-if="!userLogs.length" class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700/80">
                Belum ada log untuk tanggal ini.
              </div>
              <ul v-else class="max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                <li v-for="item in userLogs" :key="item.id" class="rounded-lg border border-slate-200 bg-white px-3 py-3 shadow-sm">
                  <div class="text-xs text-slate-500">
                    {{ new Date(item.at).toLocaleString() }}
                  </div>
                  <div class="mt-1 text-sm text-slate-800">
                    <span class="font-semibold">{{ item.actor?.name }}</span>
                    (<span class="text-slate-600">{{ item.actor?.email }}</span>)
                    mengubah
                    <span class="font-semibold">{{ item.user?.name }}</span>
                    (<span class="text-slate-600">{{ item.user?.email }}</span>)
                  </div>
                  <div class="mt-2 text-sm">
                    <div class="text-slate-600">
                      Perubahan:
                    </div>
                    <ul class="mt-1 grid gap-1 sm:grid-cols-2">
                      <li v-for="(pair, key) in item.changes" :key="key" class="text-slate-700">
                        <span class="font-medium">{{ key }}</span>:
                        <span class="mr-1 line-through text-red-600/80">{{ Array.isArray(pair) ? (pair?.[0] ?? '-') : '-' }}</span>
                        ->
                        <span class="ml-1 text-emerald-700">{{ Array.isArray(pair) ? (pair?.[1] ?? '-') : '-' }}</span>
                      </li>
                    </ul>
                  </div>
                </li>
              </ul>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-white/95 p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-base font-semibold text-emerald-700">
                  Perubahan Kalender
                </h2>
                <p class="text-xs text-emerald-700/70">
                  Pantau daftar kegiatan yang dibuat, diperbarui, atau dihapus.
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <input
                  v-model="selectedEventDate"
                  type="date"
                  class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                >
                <button
                  class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                  @click="fetchEventLogs()"
                >
                  Tampilkan
                </button>
              </div>
            </div>
            <div class="mt-4 space-y-3">
              <div v-if="loadingEventLogs" class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700/80">
                Memuat log...
              </div>
              <div v-else-if="!eventLogs.length" class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700/80">
                Belum ada log kalender untuk tanggal ini.
              </div>
              <ul v-else class="max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                <li v-for="item in eventLogs" :key="item.id" class="rounded-lg border border-slate-200 bg-white px-3 py-3 shadow-sm">
                  <div class="text-xs text-slate-500">
                    {{ new Date(item.at).toLocaleString() }}
                  </div>
                  <div class="mt-1 text-sm text-slate-800">
                    <span class="font-semibold">{{ item.actor?.name }}</span>
                    (<span class="text-slate-600">{{ item.actor?.email }}</span>)
                    <span> {{ item.action === 'create_event' ? 'menambahkan' : (item.action === 'update_event' ? 'mengubah' : 'menghapus') }} kegiatan</span>
                    <span class="font-semibold">{{ item.event?.title || '(kegiatan terhapus)' }}</span>
                  </div>
                  <div class="mt-2 text-sm">
                    <div class="text-slate-600">
                      Detail Kegiatan:
                    </div>
                    <ul class="mt-1 grid gap-1 sm:grid-cols-2">
                      <li class="text-slate-700">
                        <span class="font-medium">Judul</span>: {{ newChange(item, 'title') ?? (item.event?.title || '-') }}
                      </li>
                      <li class="text-slate-700">
                        <span class="font-medium">Mulai</span>: {{ newChange(item, 'start_at') || '-' }}
                      </li>
                      <li class="text-slate-700">
                        <span class="font-medium">Selesai</span>: {{ newChange(item, 'end_at') || '-' }}
                      </li>
                      <li class="text-slate-700">
                        <span class="font-medium">Divisi</span>: {{ displayDivisions(item) }}
                      </li>
                    </ul>
                  </div>
                </li>
              </ul>
            </div>
          </section>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
</style>

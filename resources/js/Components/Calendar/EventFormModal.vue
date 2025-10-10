<script setup>
import { computed, ref, watch } from 'vue'
import ConfirmPopup from '../ui/ConfirmPopup.vue'

const props = defineProps({
  date: { type: String, required: true },
  event: { type: Object, default: null },
  divisionOptions: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: true },
  canDelete: { type: Boolean, default: true },
})
const emit = defineEmits(['close', 'saved', 'failed', 'deleted'])

const title = ref('')
const description = ref('')
const location = ref('')
const allDay = ref(false)
const startTime = ref('09:00')
const endTime = ref('10:00')
const divisionIds = ref([])
const participantIdsCsv = ref('')

// Recurrence state
const recurrenceEnabled = ref(false)
const recurrenceType = ref('weekly') // 'weekly' | 'monthly'
const recurrenceInterval = ref(1)
const recurrenceDays = ref([]) // 1..7 (Senin=1..Minggu=7)
const recurrenceMonthDay = ref(1)
const recurrenceUntil = ref('')

watch(() => props.event, (e) => {
  if (e) {
    title.value = e.title || ''
    description.value = e.description || ''
    location.value = e.location || ''
    allDay.value = !!e.all_day
    if (e.start_at)
      startTime.value = e.start_at.slice(11, 16)
    if (e.end_at)
      endTime.value = e.end_at.slice(11, 16)
    divisionIds.value = (e.divisions || []).map(x => x.id)
    const summaryText = typeof e.participant_summary === 'string' ? e.participant_summary : ''
    if (summaryText.trim()) {
      participantIdsCsv.value = summaryText
    }
    else {
      participantIdsCsv.value = (e.participants || [])
        .map(x => x?.name || x?.email || (x?.id != null ? `ID ${x.id}` : ''))
        .filter(Boolean)
        .join(', ')
    }
    recurrenceEnabled.value = false
  }
  else {
    title.value = ''
    description.value = ''
    location.value = ''
    allDay.value = false
    startTime.value = '09:00'
    endTime.value = '10:00'
    divisionIds.value = []
    participantIdsCsv.value = ''
    recurrenceEnabled.value = false
    recurrenceType.value = 'weekly'
    recurrenceInterval.value = 1
    recurrenceDays.value = []
    recurrenceMonthDay.value = 1
    recurrenceUntil.value = ''
  }
}, { immediate: true })

const isEdit = computed(() => !!props.event?.id)

const displayDateLabel = computed(() => {
  try {
    const base = props.date ? new Date(`${props.date}T00:00:00`) : null
    if (!base || Number.isNaN(base.getTime()))
      return props.date || ''
    const dayName = base.toLocaleDateString('id-ID', { weekday: 'long' })
    const numeric = base.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })
    return `${dayName} ${numeric}`
  }
  catch {
    return props.date || ''
  }
})

const timeRangeLabel = computed(() => {
  if (allDay.value)
    return 'Sepanjang hari'
  return `${startTime.value} - ${endTime.value}`
})

const divisionSummary = computed(() => {
  if (!divisionIds.value.length)
    return 'Belum ada divisi'
  const names = props.divisionOptions
    .filter(d => divisionIds.value.includes(d.id))
    .map(d => d.name)
  if (!names.length)
    return 'Belum ada divisi'
  if (names.length === 1)
    return names[0]
  return `${names.length} divisi dipilih`
})

const saving = ref(false)
const formReadOnly = computed(() => !props.canEdit)
const showDeleteConfirm = ref(false)

async function submit() {
  if (saving.value)
    return
  if (formReadOnly.value)
    return
  saving.value = true
  await ensureSanctumCookie()
  const payload = {
    title: title.value,
    description: description.value || null,
    location: location.value || null,
    start_at: allDay.value ? (`${props.date}T07:30:00`) : (`${props.date}T${startTime.value}:00`),
    end_at: allDay.value ? (`${props.date}T16:00:00`) : (`${props.date}T${endTime.value}:00`),
    all_day: false,
    division_ids: divisionIds.value,
  }
  const participants = participantIdsCsv.value.split(',').map(s => Number.parseInt(s.trim(), 10)).filter(Boolean)
  if (participants.length > 0)
    payload.participant_user_ids = participants

  const summaryText = participantIdsCsv.value.trim()
  payload.participant_summary = summaryText || null

  if (recurrenceEnabled.value) {
    payload.recurrence_type = recurrenceType.value
    payload.recurrence_interval = Number(recurrenceInterval.value) || 1
    if (recurrenceType.value === 'weekly') {
      payload.recurrence_days = recurrenceDays.value
    }
    else if (recurrenceType.value === 'monthly') {
      payload.recurrence_month_days = [Number(recurrenceMonthDay.value) || 1]
    }
    if (recurrenceUntil.value)
      payload.recurrence_until = recurrenceUntil.value
  }

  const url = isEdit.value ? `/api/events/${props.event.id}` : '/api/events'
  const method = isEdit.value ? 'PUT' : 'POST'
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
    },
    body: JSON.stringify(payload),
  })
  if (!res.ok) {
    if ([401, 403, 419].includes(res.status)) {
      saving.value = false
      emit('failed', 'Anda tidak memiliki akses untuk mengubah atau menghapus data ini')
      return
    }
    const message = await toErrorMessage(res, 'Gagal menyimpan kegiatan')
    saving.value = false
    emit('failed', message)
    return
  }
  saving.value = false
  emit('saved', isEdit.value ? 'updated' : 'created')
}

const deleting = ref(false)

let sanctumBootstrapped = false

async function ensureSanctumCookie() {
  if (sanctumBootstrapped)
    return
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

const deleteConfirmMessage = computed(() => {
  const titleValue = props.event?.title
  return titleValue
    ? `Kegiatan "${titleValue}" akan dihapus dari kalender.`
    : 'Kegiatan akan dihapus dari kalender dan tidak dapat dikembalikan.'
})

function requestDelete() {
  if (!isEdit.value)
    return
  if (deleting.value)
    return
  if (formReadOnly.value || !props.canDelete)
    return
  showDeleteConfirm.value = true
}

function cancelDelete() {
  if (deleting.value)
    return
  showDeleteConfirm.value = false
}

async function confirmDelete() {
  if (deleting.value)
    return
  showDeleteConfirm.value = false
  await performDelete()
}

async function toErrorMessage(response, fallback = 'Terjadi kesalahan') {
  if (!response)
    return fallback
  try {
    const data = await response.clone().json()
    if (data?.errors) {
      const merged = Object.values(data.errors).flat().filter(Boolean)
      if (merged.length)
        return merged.join('\n')
    }
    if (typeof data?.message === 'string' && data.message.trim()) {
      return data.message
    }
  }
  catch {
    // ignore JSON parsing issues
  }
  try {
    const text = await response.text()
    if (text)
      return text
  }
  catch {
    // ignore text parsing issues
  }
  return fallback
}

async function performDelete() {
  if (!isEdit.value)
    return
  if (deleting.value)
    return
  deleting.value = true
  await ensureSanctumCookie()
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  const res = await fetch(`/api/events/${props.event.id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
    },
  })
  if (!res.ok) {
    if ([401, 403, 419].includes(res.status)) {
      deleting.value = false
      emit('failed', 'Anda tidak memiliki akses untuk mengubah atau menghapus data ini')
      return
    }
    const message = await toErrorMessage(res, 'Gagal menghapus kegiatan')
    deleting.value = false
    emit('failed', message)
    return
  }
  deleting.value = false
  emit('deleted')
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 px-3 py-6 sm:px-4 sm:py-8">
    <div class="flex w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-emerald-500/20 max-h-[94vh] sm:max-h-[88vh]">
      <header class="relative bg-gradient-to-r from-indigo-500 via-emerald-500 to-teal-500 px-6 py-5 text-white sm:px-8 sm:py-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/70">
              Kalender Digital
            </p>
            <h2 class="text-2xl font-semibold tracking-tight md:text-3xl">
              {{ isEdit ? 'Edit Kegiatan' : 'Kegiatan Baru' }}
            </h2>
            <p class="text-sm text-white/80">
              {{ title ? `Sedang menyunting: ${title}` : 'Lengkapi detail kegiatan di bawah untuk menyimpannya pada kalender.' }}
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="isEdit" class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Mode Edit</span>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
              @click="$emit('close')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              Tutup
            </button>
          </div>
        </div>
        <div class="mt-4 grid gap-3 text-xs font-semibold uppercase tracking-[0.25em] text-white/80 sm:grid-cols-3">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M7 3v2M17 3v2M4 7h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 5h8m-8 4h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span>{{ displayDateLabel }}</span>
          </div>
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" /></svg>
            <span>{{ timeRangeLabel }}</span>
          </div>
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.5" /><path d="M4 20.5c0-3.315 3.134-6 8-6s8 2.685 8 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
            <span>{{ divisionSummary }}</span>
          </div>
        </div>
      </header>
      <div class="flex-1 overflow-y-auto bg-slate-50">
        <div class="space-y-6 px-6 py-6">
          <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-200/30">
            <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-emerald-600">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><path d="M8 4v2m8-2v2M4 9h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              Informasi Utama
            </h3>
            <div class="space-y-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Judul Kegiatan</label>
                <input
                  v-model="title"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                  :readonly="formReadOnly"
                  :disabled="formReadOnly"
                  placeholder="cth: Rapat koordinasi mingguan"
                >
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-600">Tanggal</label>
                  <input
                    type="date"
                    :value="date"
                    disabled
                    class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  >
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-600">Sepanjang hari</label>
                  <label
                    class="inline-flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-medium shadow-sm transition focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-200" :class="[
                      formReadOnly ? 'bg-slate-100 text-slate-500' : 'bg-white text-slate-700 hover:border-emerald-300',
                    ]"
                  >
                    <span :class="allDay ? 'text-emerald-600' : 'text-slate-500'">{{ allDay ? 'Aktif' : 'Nonaktif' }}</span>
                    <input
                      v-model="allDay"
                      type="checkbox"
                      class="h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-400"
                      :disabled="formReadOnly"
                    >
                  </label>
                </div>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-600">Mulai</label>
                  <input v-model="startTime" type="time" :disabled="allDay || formReadOnly" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100">
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-600">Selesai</label>
                  <input v-model="endTime" type="time" :disabled="allDay || formReadOnly" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100">
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-200/30">
            <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-emerald-600">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 21s6-4.35 6-9a6 6 0 1 0-12 0c0 4.65 6 9 6 9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" /></svg>
              Detail Kegiatan
            </h3>
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-slate-600">Lokasi</label>
                <input
                  v-model="location"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                  :readonly="formReadOnly"
                  :disabled="formReadOnly"
                  placeholder="cth: Ruang rapat lantai 3"
                >
              </div>
              <div class="space-y-2 sm:col-span-2">
                <label class="block text-sm font-medium text-slate-600">Deskripsi</label>
                <textarea
                  v-model="description"
                  rows="3"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                  :readonly="formReadOnly"
                  :disabled="formReadOnly"
                  placeholder="Tambahkan catatan penting atau agenda rapat"
                />
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-200/30">
            <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-emerald-600">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.5" /><path d="M4 20c0-3.314 3.333-6 8-6s8 2.686 8 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M5 5h4M5 8h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              Divisi &amp; Peserta
            </h3>
            <div class="space-y-5">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-500">
                  Divisi Terlibat
                </p>
                <div
                  v-if="divisionOptions.length"
                  class="mt-3 flex flex-wrap gap-2" :class="[formReadOnly ? 'opacity-60 pointer-events-none' : '']"
                >
                  <label
                    v-for="d in divisionOptions"
                    :key="d.id"
                    class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700 shadow-sm"
                  >
                    <input
                      v-model="divisionIds"
                      type="checkbox"
                      :value="d.id"
                      class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-400"
                      :disabled="formReadOnly"
                    >
                    <span>{{ d.name }}</span>
                  </label>
                </div>
                <p v-else class="mt-3 text-sm text-slate-500">
                  Belum ada divisi yang tersedia.
                </p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Nama atau ID Peserta (pisahkan dengan koma)</label>
                <input
                  v-model="participantIdsCsv"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:bg-slate-100"
                  :readonly="formReadOnly"
                  :disabled="formReadOnly"
                  placeholder="cth: Budi, Adit, Dina atau 12, 34, 78"
                >
                <p class="mt-1 text-xs text-slate-500">
                  Gunakan ID pengguna untuk menautkan peserta secara otomatis bila tersedia.
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-200/30">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <h3 class="flex items-center gap-2 text-sm font-semibold text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M7 4h10a3 3 0 0 1 3 3v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M7 20H5a3 3 0 0 1-3-3v-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="m7 16 3-3-3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M17 8l-3 3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Pengulangan
              </h3>
              <label class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-600">
                <input v-model="recurrenceEnabled" type="checkbox" class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-400" :disabled="formReadOnly">
                Aktif
              </label>
            </div>
            <div
              v-if="recurrenceEnabled"
              class="mt-4 space-y-4" :class="[formReadOnly ? 'opacity-60 pointer-events-none' : '']"
            >
              <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-600">Tipe</label>
                <select
                  v-model="recurrenceType"
                  class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  :disabled="formReadOnly"
                >
                  <option value="weekly">
                    Mingguan
                  </option>
                  <option value="monthly">
                    Bulanan
                  </option>
                </select>
                <label class="text-sm font-medium text-slate-600">Interval</label>
                <input
                  v-model.number="recurrenceInterval"
                  type="number"
                  min="1"
                  class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  :disabled="formReadOnly"
                >
                <span v-if="recurrenceType === 'weekly'" class="text-xs text-slate-500">(setiap n minggu)</span>
                <span v-else class="text-xs text-slate-500">(setiap n bulan)</span>
              </div>
              <div v-if="recurrenceType === 'weekly'">
                <p class="mb-2 text-sm font-medium text-slate-600">
                  Pilih hari
                </p>
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="(nm, idx) in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']"
                    :key="idx"
                    class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700 shadow-sm"
                  >
                    <input
                      v-model="recurrenceDays"
                      type="checkbox"
                      :value="idx + 1"
                      class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-400"
                      :disabled="formReadOnly"
                    >
                    <span>{{ nm }}</span>
                  </label>
                </div>
              </div>
              <div v-else>
                <p class="mb-2 text-sm font-medium text-slate-600">
                  Tanggal setiap bulan
                </p>
                <input
                  v-model.number="recurrenceMonthDay"
                  type="number"
                  min="1"
                  max="31"
                  class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  :disabled="formReadOnly"
                >
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Sampai tanggal (opsional)</label>
                <input
                  v-model="recurrenceUntil"
                  type="date"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  :disabled="formReadOnly"
                >
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">
              Aktifkan pengulangan jika kegiatan berlangsung secara berkala.
            </p>
          </section>
        </div>
      </div>
      <div class="border-t border-slate-200 bg-white px-6 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <button
            v-if="isEdit && props.canDelete"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="formReadOnly || deleting"
            @click="requestDelete"
          >
            <span v-if="deleting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
            <span>{{ deleting ? 'Menghapus...' : 'Hapus' }}</span>
          </button>
          <div class="flex flex-col gap-2 sm:ml-auto sm:flex-row sm:items-center sm:gap-3">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-200"
              @click="$emit('close')"
            >
              Batal
            </button>
            <button
              type="button"
              :disabled="saving || formReadOnly"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submit"
            >
              <span v-if="saving" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-r-transparent" />
              <span>Simpan</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <ConfirmPopup
      :visible="showDeleteConfirm"
      title="Hapus kegiatan ini?"
      :message="deleteConfirmMessage"
      confirm-text="Hapus"
      cancel-text="Batal"
      :loading="deleting"
      @cancel="cancelDelete"
      @confirm="confirmDelete"
    />
  </div>
</template>

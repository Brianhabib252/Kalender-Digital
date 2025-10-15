<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue'
import DayView from '../../Components/Calendar/DayView.vue'
import EventFormModal from '../../Components/Calendar/EventFormModal.vue'
import MonthView from '../../Components/Calendar/MonthView.vue'
import SidebarDayList from '../../Components/Calendar/SidebarDayList.vue'
import ProfileSettingsModal from '../../Components/Profile/ProfileSettingsModal.vue'
import ConfirmPopup from '../../Components/ui/ConfirmPopup.vue'
import ErrorPopup from '../../Components/ui/ErrorPopup.vue'
import LoadingOverlay from '../../Components/ui/LoadingOverlay.vue'
import SuccessPopup from '../../Components/ui/SuccessPopup.vue'
import AnimatedBubbleBackground from '@/components/Decor/AnimatedBubbleBackground.vue'
import { formatHijriDate, formatHijriMonth, gregorianToHijri, hijriMonthLength, hijriMonthRange, hijriToGregorianDate, shiftHijriMonth } from '../../lib/hijri.js'

const props = defineProps({
  today: String,
  view: { type: String, default: 'month' },
  date: { type: String, default: null },
  divisionOptions: { type: Array, default: () => [] },
  participantOptions: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props?.auth?.user ?? null)
const participantOptions = computed(() => props.participantOptions ?? [])
const showProfileModal = ref(false)

const role = computed(() => user.value?.role ?? 'viewer')
const canCreate = computed(() => ['admin', 'editor'].includes(role.value))
const canEdit = computed(() => ['admin', 'editor'].includes(role.value))
const canDelete = computed(() => role.value === 'admin' || role.value === 'editor')

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

const currentView = ref(props.view || 'month')
const currentDate = ref(props.date || props.today)
const selectedDay = ref(null)
const selectedDivisionIds = ref([])
const q = ref('')
const events = ref([])
const holidays = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingEvent = ref(null)
const showSuccess = ref(false)
const successMessage = ref('')
let successTimer = null
const showError = ref(false)
const errorMessage = ref('')
let errorTimer = null
const showDeleteConfirm = ref(false)
const deleteConfirmTitle = ref('')
const deleteConfirmMessage = ref('')
const pendingDeleteEvent = ref(null)
const deleteLoading = ref(false)

const showNotificationModal = ref(false)
const notificationsSupported = typeof window !== 'undefined' && typeof Notification !== 'undefined'
const notificationPermission = ref(notificationsSupported ? Notification.permission : 'denied')
const notificationStorageKey = 'calendar.notification.topics'
const notificationLeadMs = 15 * 60 * 1000
const notificationTopics = [
  { id: 'kesekretariatan', label: 'Kesekretariatan', targets: ['kesekretariatan', 'seluruh pegawai'] },
  { id: 'kepaniteraan', label: 'Kepaniteraan', targets: ['kepaniteraan', 'seluruh pegawai'] },
  { id: 'hakim', label: 'Hakim', targets: ['hakim', 'seluruh pegawai'] },
]
const notificationTargetMap = notificationTopics.reduce((map, topic) => {
  map[topic.id] = topic.targets
  return map
}, {})
const selectedNotificationTopics = ref([])
const notificationTimers = new Map()
let notificationInitialized = false

const calendarSystem = ref('gregorian')
const isHijri = computed(() => calendarSystem.value === 'hijri')

const initialCalendar = (() => {
  try {
    const url = page.url || ''
    const query = url.includes('?') ? url.split('?')[1] : ''
    const value = new URLSearchParams(query).get('calendar')
    return value === 'hijri' ? 'hijri' : 'gregorian'
  }
  catch {
    return 'gregorian'
  }
})()
if (initialCalendar === 'hijri') {
  calendarSystem.value = 'hijri'
}

onBeforeUnmount(() => {
  clearTimeout(successTimer)
  clearTimeout(errorTimer)
  clearScheduledNotifications()
})

function startOfWeek(date) {
  const d = new Date(date)
  const day = (d.getDay() + 6) % 7 // Mon=0
  d.setDate(d.getDate() - day)
  d.setHours(0, 0, 0, 0)
  return d
}

function endOfWeek(date) {
  const d = startOfWeek(date)
  d.setDate(d.getDate() + 6)
  d.setHours(23, 59, 59, 999)
  return d
}

function startOfMonth(date) {
  const d = new Date(date); d.setDate(1); d.setHours(0, 0, 0, 0); return d
}

function endOfMonth(date) {
  const d = new Date(date); d.setMonth(d.getMonth() + 1, 0); d.setHours(23, 59, 59, 999); return d
}

function parseYMD(s) {
  if (!s)
    return new Date()
  const [y, m, d] = s.split('-').map(n => Number.parseInt(n, 10))
  return new Date(y, (m || 1) - 1, d || 1)
}

function ymd(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const da = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${da}`
}

function formatLocale(date, options) {
  try {
    return date.toLocaleDateString('id-ID', options)
  }
  catch {
    return date.toLocaleDateString(undefined, options)
  }
}

function formatGregorianMonthLabel(date) {
  return formatLocale(date, { month: 'long', year: 'numeric' })
}

function formatGregorianLong(date) {
  return formatLocale(date, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

function formatGregorianShort(date) {
  return formatLocale(date, { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const monthSpan = computed(() => {
  const base = parseYMD(currentDate.value)
  if (isHijri.value) {
    const range = hijriMonthRange(base)
    return {
      start: new Date(range.start),
      end: new Date(range.end),
      daysInMonth: range.daysInMonth,
      hijriMonth: range.hijriMonth,
      hijriYear: range.hijriYear,
    }
  }
  const start = startOfMonth(base)
  const end = endOfMonth(base)
  const daysInMonth = new Date(base.getFullYear(), base.getMonth() + 1, 0).getDate()
  return { start, end, daysInMonth, hijriMonth: null, hijriYear: null }
})

const range = computed(() => {
  const d = parseYMD(currentDate.value)
  if (currentView.value === 'day') {
    const s = new Date(d); s.setHours(0, 0, 0, 0)
    const e = new Date(d); e.setHours(23, 59, 59, 999)
    return { start: s, end: e }
  }
  if (currentView.value === 'week') {
    return { start: startOfWeek(d), end: endOfWeek(d) }
  }
  const span = monthSpan.value
  const s = startOfWeek(span.start); s.setDate(s.getDate() - 7)
  const e = endOfWeek(span.end); e.setDate(e.getDate() + 7)
  return { start: s, end: e }
})
// counts for selected date's week and month
const weeklyCount = computed(() => {
  const base = new Date(currentDate.value)
  const ws = startOfWeek(base)
  const we = endOfWeek(base)
  return events.value.filter((e) => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= we && n >= ws) || e.all_day
  }).length
})

const monthlyCount = computed(() => {
  const ms = monthSpan.value.start
  const me = monthSpan.value.end
  return events.value.filter((e) => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= me && n >= ms) || e.all_day
  }).length
})

const todaysCount = computed(() => {
  const ds = new Date(`${props.today}T00:00:00`)
  const de = new Date(`${props.today}T23:59:59`)
  return events.value.filter((e) => {
    const s = new Date(e.start_at); const n = new Date(e.end_at)
    return (s <= de && n >= ds) || e.all_day
  }).length
})

// Month title for the current calendar context
const monthTitle = computed(() => {
  const d = parseYMD(currentDate.value)
  if (isHijri.value) {
    return formatHijriMonth(d)
  }
  return formatGregorianMonthLabel(d)
})

const selectedDayLabel = computed(() => {
  const base = parseYMD(selectedDay.value || currentDate.value)
  if (isHijri.value) {
    const hijri = formatHijriDate(base)
    const greg = formatGregorianShort(base)
    return `${hijri} (${greg})`
  }
  return formatGregorianLong(base)
})

const hasActiveNotifications = computed(() => selectedNotificationTopics.value.length > 0)
const notificationSummary = computed(() => {
  if (!selectedNotificationTopics.value.length)
    return 'Tidak aktif'
  const labels = notificationTopics
    .filter(topic => selectedNotificationTopics.value.includes(topic.id))
    .map(topic => topic.label)
  return labels.join(', ')
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
    if (q.value)
      params.set('q', q.value)
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
  }
  finally {
    loading.value = false
  }
}

async function fetchHolidays() {
  try {
    await ensureSanctumCookie()
    const res = await fetch('/api/holidays', {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
      },
    })

    if (!res.ok) {
      const message = await toErrorMessage(res, 'Gagal memuat hari libur')
      triggerError(message)
      return
    }

    const json = await res.json()
    holidays.value = Array.isArray(json.data) ? json.data : []
  }
  catch (error) {
    console.error('Failed to load holidays', error)
    triggerError('Tidak dapat memuat data hari libur. Coba lagi nanti.')
  }
}
onMounted(() => {
  fetchHolidays()

  if (notificationsSupported) {
    notificationPermission.value = Notification.permission

    try {
      const stored = window.localStorage.getItem(notificationStorageKey)
      if (stored) {
        const parsed = JSON.parse(stored)
        if (Array.isArray(parsed)) {
          const restored = parsed.filter(id => notificationTargetMap[id])
          selectedNotificationTopics.value = restored
        }
      }
    }
    catch (error) {
      console.warn('Gagal memuat preferensi notifikasi', error)
    }

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistration('/notification-sw.js')
        .then((existing) => {
          if (!existing) {
            navigator.serviceWorker.register('/notification-sw.js').catch(() => {})
          }
        })
        .catch(() => {
          navigator.serviceWorker.register('/notification-sw.js').catch(() => {})
        })
    }
  }

  setTimeout(() => {
    notificationInitialized = true
    rescheduleEventNotifications()
  }, 0)
})

watchEffect(() => {
  // Touch reactive dependencies synchronously so changes trigger this effect
  const viewValue = currentView.value
  const dateValue = currentDate.value
  const systemValue = calendarSystem.value

  void q.value
  void selectedDivisionIds.value.join(',')

  fetchEvents()
  // sync URL (only view/date go to query)
  router.visit(`/calendar`, { method: 'get', data: {
    view: viewValue,
    date: dateValue,
    calendar: systemValue,
  }, replace: true, preserveState: true, preserveScroll: true })
})

watch(events, () => {
  if (!notificationInitialized)
    return
  rescheduleEventNotifications()
}, { deep: true })

watch(selectedNotificationTopics, async (newValue, oldValue) => {
  if (!notificationInitialized)
    return

  if (!notificationsSupported) {
    if (newValue.length) {
      triggerError('Browser Anda tidak mendukung notifikasi.')
      selectedNotificationTopics.value = []
    }
    return
  }

  const previouslyActive = Array.isArray(oldValue) && oldValue.length > 0
  const nowActive = newValue.length > 0

  if (nowActive && !previouslyActive) {
    const granted = await ensureNotificationPermission()
    if (!granted) {
      selectedNotificationTopics.value = []
      return
    }
    triggerSuccess('Notifikasi kalender aktif')
  }
  else if (!nowActive && previouslyActive) {
    triggerSuccess('Notifikasi kalender dinonaktifkan')
  }

  updateStoredNotificationTopics(newValue)
  rescheduleEventNotifications()
}, { deep: true })

function closeNotificationModal() {
  showNotificationModal.value = false
}

function updateStoredNotificationTopics(topics) {
  if (typeof window === 'undefined')
    return
  try {
    window.localStorage.setItem(notificationStorageKey, JSON.stringify(topics))
  }
  catch (error) {
    console.warn('Gagal menyimpan preferensi notifikasi', error)
  }
}

function clearScheduledNotifications() {
  for (const timeout of notificationTimers.values())
    clearTimeout(timeout)
  notificationTimers.clear()
}

function normalizeDivisionName(name) {
  if (typeof name === 'string')
    return name.trim().toLowerCase()
  if (name == null)
    return ''
  return String(name).trim().toLowerCase()
}

function parseEventDateTime(str) {
  if (!str)
    return null
  const match = str.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (match) {
    const [, year, month, day, hour, minute, second] = match
    return new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hour),
      Number(minute),
      Number(second || '0'),
    )
  }
  const dt = new Date(str)
  return Number.isNaN(dt.getTime()) ? null : dt
}

function eventMatchesSelectedTopics(event) {
  if (!selectedNotificationTopics.value.length)
    return false
  const divisions = Array.isArray(event?.divisions) ? event.divisions : []
  if (!divisions.length)
    return false
  const normalized = new Set(
    divisions
      .map(div => normalizeDivisionName(div?.name))
      .filter(Boolean),
  )
  if (!normalized.size)
    return false
  return selectedNotificationTopics.value.some((topicId) => {
    const targets = notificationTargetMap[topicId] || []
    return targets.some(target => normalized.has(target))
  })
}

function formatTimeForNotification(dateString) {
  const dt = parseEventDateTime(dateString)
  if (!dt)
    return ''
  try {
    return dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  }
  catch {
    return dt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
  }
}

async function ensureNotificationPermission() {
  if (!notificationsSupported)
    return false
  if (notificationPermission.value === 'granted')
    return true
  if (notificationPermission.value === 'denied') {
    triggerError('Izin notifikasi diblokir di pengaturan browser Anda.')
    return false
  }
  try {
    const result = await Notification.requestPermission()
    notificationPermission.value = result
    if (result === 'granted')
      return true
    triggerError('Izin notifikasi diperlukan agar pengingat dapat dikirim.')
    return false
  }
  catch (error) {
    console.error('Gagal meminta izin notifikasi', error)
    triggerError('Gagal meminta izin notifikasi.')
    return false
  }
}

async function showEventNotification(event) {
  if (!notificationsSupported || notificationPermission.value !== 'granted')
    return

  const title = event?.title || 'Pengingat Kegiatan'
  const timeText = formatTimeForNotification(event?.start_at)
  const locationText = (event?.location && event.location.trim()) ? event.location.trim() : 'Lokasi belum ditentukan'
  const bodyLines = []
  if (timeText)
    bodyLines.push(`Waktu: ${timeText}`)
  bodyLines.push(`Lokasi: ${locationText}`)

  const options = {
    body: bodyLines.join('\n'),
    tag: `event-${event?.id ?? 'unknown'}-${event?.start_at ?? ''}`,
    data: {
      eventId: event?.id ?? null,
      startAt: event?.start_at ?? null,
    },
    icon: '/favicon.ico',
    badge: '/favicon.ico',
  }

  try {
    let registration = null
    if ('serviceWorker' in navigator) {
      try {
        registration = await navigator.serviceWorker.getRegistration('/notification-sw.js')
        if (!registration)
          registration = await navigator.serviceWorker.getRegistration()
      }
      catch {
        registration = null
      }
    }
    if (registration?.showNotification) {
      registration.showNotification(title, options)
    }
    else {
      // Fallback to direct notification if there is no service worker
      // eslint-disable-next-line no-new
      new Notification(title, options)
    }
  }
  catch (error) {
    console.error('Gagal menampilkan notifikasi kegiatan', error)
  }
}

function rescheduleEventNotifications() {
  clearScheduledNotifications()

  if (!notificationsSupported)
    return
  if (notificationPermission.value !== 'granted')
    return
  if (!selectedNotificationTopics.value.length)
    return
  if (!Array.isArray(events.value) || !events.value.length)
    return

  const now = Date.now()

  for (const event of events.value) {
    if (!event || !event.start_at)
      continue
    if (!eventMatchesSelectedTopics(event))
      continue
    const start = parseEventDateTime(event.start_at)
    if (!start)
      continue
    const startMs = start.getTime()
    if (Number.isNaN(startMs))
      continue
    if (startMs <= now)
      continue

    const triggerAt = startMs - notificationLeadMs
    let delay = triggerAt - now
    if (delay < 0)
      delay = 0

    const key = `${event.id ?? 'event'}_${event.start_at}`
    const timerId = setTimeout(() => {
      void showEventNotification(event)
      notificationTimers.delete(key)
    }, delay)
    notificationTimers.set(key, timerId)
  }
}

function go(delta, unit) {
  const d = parseYMD(currentDate.value)
  if (unit === 'day') {
    d.setDate(d.getDate() + delta)
  }
  else if (unit === 'week') {
    d.setDate(d.getDate() + delta * 7)
  }
  else if (unit === 'month') {
    if (isHijri.value) {
      const hijri = gregorianToHijri(d)
      const target = shiftHijriMonth(hijri.year, hijri.month, delta)
      const maxDay = hijriMonthLength(target.year, target.month)
      const day = Math.min(hijri.day, maxDay)
      const nextDate = hijriToGregorianDate(target.year, target.month, day)
      currentDate.value = ymd(nextDate)
      return
    }
    d.setMonth(d.getMonth() + delta)
  }
  currentDate.value = ymd(d)
}

function setCalendarSystem(system) {
  if (calendarSystem.value === system)
    return
  calendarSystem.value = system
}

function openCreate(slotDate) {
  if (!canCreate.value)
    return
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

function triggerError(message) {
  errorMessage.value = message || 'Terjadi kesalahan'
  showError.value = true
  clearTimeout(errorTimer)
  errorTimer = setTimeout(() => {
    showError.value = false
  }, 2500)
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
    // ignore JSON parsing issues, fall back to text handling
  }
  try {
    const text = await response.text()
    if (text)
      return text
  }
  catch {
    // ignore text parsing issues, use fallback
  }
  return fallback
}

function onFailed(message) {
  triggerError(message || 'Terjadi kesalahan')
}

function onSaved(kind = 'saved') {
  showForm.value = false
  fetchEvents()
  if (kind === 'created') {
    triggerSuccess('Kegiatan berhasil dibuat')
  }
  else if (kind === 'updated') {
    triggerSuccess('Kegiatan berhasil diperbarui')
  }
  else if (kind === 'deleted') {
    triggerSuccess('Kegiatan berhasil dihapus')
  }
  else {
    triggerSuccess('Perubahan berhasil disimpan')
  }
}

function onDeleted() {
  onSaved('deleted')
}

async function onDelete(evt) {
  if (!canDelete.value)
    return
  if (!evt?.id)
    return
  pendingDeleteEvent.value = evt
  deleteConfirmTitle.value = 'Hapus kegiatan ini?'
  deleteConfirmMessage.value = evt?.title
    ? `Kegiatan "${evt.title}" akan dihapus dari kalender.`
    : 'Kegiatan akan dihapus dari kalender dan tidak dapat dikembalikan.'
  showDeleteConfirm.value = true
}

function cancelDelete() {
  if (deleteLoading.value)
    return
  showDeleteConfirm.value = false
  pendingDeleteEvent.value = null
}

async function confirmDelete() {
  if (!pendingDeleteEvent.value || deleteLoading.value)
    return
  deleteLoading.value = true
  const target = pendingDeleteEvent.value
  try {
    await performDelete(target)
  }
  finally {
    deleteLoading.value = false
    showDeleteConfirm.value = false
    pendingDeleteEvent.value = null
  }
}

async function performDelete(evt, attempt = 0) {
  try {
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
      if (res.status === 419 && attempt === 0) {
        sanctumBootstrapped = false
        await ensureSanctumCookie()
        return performDelete(evt, attempt + 1)
      }
      if ([401, 403].includes(res.status)) {
        triggerError('Anda tidak memiliki akses untuk mengubah atau menghapus data ini')
        return false
      }
      if (res.status === 419) {
        triggerError('Sesi Anda telah berakhir. Muat ulang halaman dan coba lagi.')
        return false
      }
      const message = await toErrorMessage(res, 'Gagal menghapus kegiatan')
      triggerError(message)
      return false
    }
    fetchEvents()
    triggerSuccess('Kegiatan berhasil dihapus')
    return true
  }
  catch {
    triggerError('Tidak dapat terhubung ke server. Coba lagi nanti.')
    return false
  }
}
</script>

<template>
  <section class="relative isolate min-h-screen overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-sky-50 px-3 py-8 sm:px-5 md:px-8 md:py-10">
    <AnimatedBubbleBackground />
    <div class="relative z-10 mx-auto w-full max-w-6xl space-y-6 lg:space-y-8">
      <!-- Dashboard header -->
      <div class="rounded-3xl border border-emerald-300 bg-gradient-to-r from-emerald-300 via-emerald-200 to-emerald-100 px-5 py-7 shadow-[0_28px_100px_-45px_rgba(6,95,70,0.6)] transition-all duration-300 hover:shadow-[0_45px_160px_-55px_rgba(6,95,70,0.7)] sm:px-6 md:px-8">
        <div class="space-y-6 text-emerald-900">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex flex-1 flex-col justify-center gap-4 rounded-2xl bg-white/40 p-5 text-center shadow-inner md:flex-row md:items-center md:gap-6 md:text-left">
              <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-200/80 text-emerald-700 shadow-inner md:mx-0 md:h-20 md:w-20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10">
                  <path d="M6 2a1 1 0 0 1 1 1v1h10V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 1 1 2 0v1zm14 6H4v11h16V8zM4 6h16V5h-1v1a1 1 0 1 1-2 0V5H7v1a1 1 0 1 1-2 0V5H4v1z" />
                </svg>
              </div>
              <div class="space-y-3">
                <p class="text-sm font-semibold uppercase tracking-[0.35em] text-emerald-600 sm:text-base">
                  Kalender Digital
                </p>
                <h1 class="text-2xl font-bold leading-tight text-emerald-900 sm:text-3xl md:text-4xl">
                  Pengadilan Tinggi Agama Surabaya
                </h1>
                <p class="mx-auto max-w-xl text-sm text-emerald-700 sm:text-base md:mx-0 md:text-lg">
                  Jadwal kegiatan terpusat dan transparan demi koordinasi yang rapi di setiap divisi.
                </p>
              </div>
            </div>

              <div class="flex w-full flex-col gap-3 lg:max-w-sm xl:max-w-xs lg:items-end">
                <div v-if="user" class="flex w-full flex-col items-center gap-2 rounded-2xl border border-emerald-200 bg-white/90 px-3 py-3 text-center shadow-[0_12px_48px_-40px_rgba(16,185,129,0.6)] backdrop-blur-sm">
                  <div class="text-sm font-semibold text-emerald-700 sm:text-base">
                    {{ user.name }}
                  </div>
                  <button
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-lg border border-emerald-200 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-emerald-300/30 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300 sm:text-sm"
                  @click="openProfileModal"
                >
                  Kelola Profil
                </button>
              </div>
              <div class="flex h-12 w-full items-center gap-2 rounded-2xl border border-emerald-200 bg-white/80 px-5 text-sm text-emerald-700 shadow-sm focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-200 lg:self-end">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 text-emerald-500"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" stroke-linejoin="round" d="m16.5 16.5 3 3" /></svg>
                <input v-model="q" placeholder="Cari judul/lokasi/deskripsi" class="h-full w-full bg-transparent text-sm text-emerald-700 placeholder:text-emerald-400 focus:outline-none">
              </div>
              <button
                class="flex h-12 w-full items-center justify-center rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 text-sm font-semibold text-white shadow-lg shadow-emerald-300/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 sm:h-14"
                :disabled="!canCreate"
                @click="openCreate()"
              >
                Tambah Kegiatan
              </button>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
              type="button"
              class="flex h-12 w-full items-center justify-between rounded-xl border border-emerald-200 bg-white px-4 text-left text-sm font-semibold text-emerald-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 sm:h-14"
              @click="showNotificationModal = true"
            >
              <span class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 2a5 5 0 0 0-5 5v2.05c0 .314-.093.621-.266.882L5.17 12.938A2 2 0 0 0 6.794 16h10.412a2 2 0 0 0 1.624-3.062l-1.564-3.006A1.75 1.75 0 0 1 16 9.05V7a5 5 0 0 0-5-5zm0 20a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22z" /></svg>
                </span>
                <span class="flex flex-col items-start">
                  <span>Notifikasi</span>
                  <span class="text-xs font-medium" :class="hasActiveNotifications ? 'text-emerald-500' : 'text-emerald-400'">
                    {{ hasActiveNotifications ? notificationSummary : 'Tidak aktif' }}
                  </span>
                </span>
              </span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 9 3.75 3.75L15.75 9" /></svg>
            </button>
            <button class="flex h-12 w-full items-center justify-center rounded-xl border border-emerald-200 bg-white text-sm font-semibold text-emerald-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 sm:h-14" @click="currentDate = today">
              Hari Ini
            </button>
            <label class="flex h-12 w-full items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-white px-4 text-sm text-emerald-700 shadow-sm focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-200 sm:h-14">
              <span class="hidden sm:inline whitespace-nowrap text-emerald-500/80">Pilih tanggal</span>
              <input v-model="currentDate" type="date" class="h-9 w-full rounded-md border border-transparent bg-transparent text-right focus:border-emerald-300 focus:outline-none">
            </label>
            <div class="flex w-full flex-col gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-sm sm:h-14 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:py-0">
              <div class="flex w-full flex-col gap-2 sm:flex-1 sm:h-full sm:flex-row sm:items-center">
                <button
                  type="button"
                  class="flex h-auto w-full items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold transition sm:flex-1 sm:h-10 sm:py-0"
                  :class="calendarSystem === 'gregorian' ? 'bg-emerald-500 text-white shadow-inner shadow-emerald-300/40' : 'bg-emerald-50 text-emerald-600 border border-emerald-200'"
                  @click="setCalendarSystem('gregorian')"
                >
                  Masehi
                </button>
                <button
                  type="button"
                  class="flex h-auto w-full items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold transition sm:flex-1 sm:h-10 sm:py-0"
                  :class="calendarSystem === 'hijri' ? 'bg-emerald-500 text-white shadow-inner shadow-emerald-300/40' : 'bg-emerald-50 text-emerald-600 border border-emerald-200'"
                  @click="setCalendarSystem('hijri')"
                >
                  Hijriah
                </button>
              </div>
            </div>
          </div>
          <div class="grid gap-4 text-emerald-800 text-xl sm:grid-cols-2 xl:grid-cols-3">
            <!-- Hari Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" /></svg></span>
                <div class="opacity-90 font-medium">
                  Hari Ini
                </div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-emerald-600">
                {{ todaysCount }}
              </div>
            </div>
            <!-- Minggu Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-sky-100 text-sky-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M19 3H5a2 2 0 0 0-2 2v3h18V5a2 2 0 0 0-2-2zM3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10H3v9z" /></svg></span>
                <div class="opacity-90 font-medium">
                  Minggu Ini
                </div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-sky-600">
                {{ weeklyCount }}
              </div>
            </div>
            <!-- Bulan Ini -->
            <div class="flex items-center justify-between rounded-3xl border border-emerald-100 bg-white px-6 py-5 transition-all duration-300 hover:border-emerald-200">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-amber-100 text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M7 11h10v2H7v-2zm0 4h10v2H7v-2zM6 3a2 2 0 0 0-2 2v1h16V5a2 2 0 0 0-2-2H6zm14 5H4v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /></svg></span>
                <div class="opacity-90 font-medium">
                  Bulan Ini
                </div>
              </div>
              <div class="text-3xl font-extrabold leading-none tabular-nums text-amber-600">
                {{ monthlyCount }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="relative rounded-3xl border border-emerald-100 bg-white p-4 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)] sm:p-6">
          <div class="relative mb-4 flex items-center justify-between gap-2">
            <button class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-emerald-200 bg-emerald-100 text-emerald-700 transition hover:bg-emerald-200 active:scale-95 sm:h-12 sm:w-12" aria-label="Bulan sebelumnya" @click="go(-1, 'month')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </button>
            <div class="flex-1 text-center text-xl font-bold capitalize text-emerald-600 sm:text-2xl md:text-3xl">
              {{ monthTitle }}
            </div>
            <button class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-emerald-200 bg-emerald-100 text-emerald-700 transition hover:bg-emerald-200 active:scale-95 sm:h-12 sm:w-12" aria-label="Bulan berikutnya" @click="go(1, 'month')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7"><path d="M8.25 4.5 15.75 12l-7.5 7.5" /></svg>
            </button>
          </div>
          <div class="mb-4 text-center">
            <div class="flex flex-wrap justify-center gap-2">
              <label
                v-for="d in divisionOptions" :key="d.id"
                class="inline-flex items-center gap-2 rounded-full border px-5 py-2 text-sm font-medium cursor-pointer select-none transition shadow-sm"
                :class="selectedDivisionIds.includes(d.id)
                  ? 'bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 border-transparent text-white shadow-md'
                  : 'bg-white text-emerald-600 border-emerald-200 hover:border-emerald-300 hover:bg-emerald-50'"
              >
                <input v-model="selectedDivisionIds" type="checkbox" :value="d.id" class="h-4 w-4 rounded-full border-emerald-200 text-emerald-500 focus:ring-emerald-400">
                <span>{{ d.name }}</span>
              </label>
            </div>
          </div>
          <div class="-mx-3 overflow-x-auto pb-2 sm:mx-0">
            <div class="min-w-[680px] sm:min-w-0">
              <MonthView
                :date="currentDate"
                :events="events"
                :holidays="holidays"
                :calendar-system="calendarSystem"
                :can-create="canCreate"
                :can-edit="canEdit"
                @select-day="d => { currentDate = d; selectedDay = d }"
                @open-create="openCreate"
                @open-edit="openEdit"
              />
            </div>
          </div>
          <LoadingOverlay v-if="loading" message="Memuat data..." />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <div class="relative rounded-3xl border border-emerald-100 bg-white p-4 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)] sm:p-6">
            <div class="mb-3 text-xl font-extrabold text-emerald-700 capitalize sm:text-2xl md:text-3xl">
              Kegiatan {{ selectedDayLabel }}
            </div>
            <SidebarDayList
              bare
              :date="selectedDay || currentDate"
              :events="events"
              :holidays="holidays"
              :can-edit="canEdit"
              :can-delete="canDelete"
              @open-edit="openEdit"
              @delete-event="onDelete"
            />
            <LoadingOverlay v-if="loading" message="Memuat data..." />
          </div>

          <div class="relative rounded-3xl border border-emerald-100 bg-white p-4 shadow-lg transition-all duration-300 hover:shadow-[0_45px_120px_-60px_rgba(16,185,129,0.35)] sm:p-6">
            <div class="px-1 py-1 text-xl font-extrabold text-emerald-700 capitalize sm:text-2xl md:text-3xl">
              Timeline {{ selectedDayLabel }}
            </div>
            <div class="mt-4 -mx-3 overflow-x-auto pb-2 sm:mx-0">
              <div class="min-w-[540px] sm:min-w-0">
                <DayView
                  :date="selectedDay || currentDate"
                  :events="events"
                  :holidays="holidays"
                  :display-label="selectedDayLabel"
                  :start-hour="6"
                  :end-hour="18"
                  :can-create="canCreate"
                  :can-edit="canEdit"
                  @open-create="() => openCreate(selectedDay || currentDate)"
                  @open-edit="openEdit"
                />
              </div>
            </div>
            <LoadingOverlay v-if="loading" message="Memuat data..." />
          </div>
        </div>
      </div>

      <ProfileSettingsModal
        v-if="showProfileModal && user"
        :user="user"
        @close="closeProfileModal"
      />

      <div
        v-if="showNotificationModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-emerald-900/30 px-4 py-8 backdrop-blur-sm"
        @click.self="closeNotificationModal"
      >
        <div class="relative w-full max-w-md rounded-3xl border border-emerald-200 bg-white p-6 shadow-2xl">
          <button
            type="button"
            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full border border-emerald-200 text-emerald-500 transition hover:bg-emerald-50"
            aria-label="Tutup pengaturan notifikasi"
            @click="closeNotificationModal"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6m0 12L6 6" /></svg>
          </button>
          <div class="mb-4 flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-600">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 2a5 5 0 0 0-5 5v2.05c0 .314-.093.621-.266.882L5.17 12.938A2 2 0 0 0 6.794 16h10.412a2 2 0 0 0 1.624-3.062l-1.564-3.006A1.75 1.75 0 0 1 16 9.05V7a5 5 0 0 0-5-5zm0 20a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22z" /></svg>
            </span>
            <div>
              <h2 class="text-lg font-semibold text-emerald-700">Pengingat kegiatan</h2>
              <p class="text-sm text-emerald-500">
                Notifikasi akan dikirim 15 menit sebelum kegiatan dimulai untuk topik yang Anda pilih.
              </p>
            </div>
          </div>
          <div class="space-y-2">
            <label
              v-for="topic in notificationTopics"
              :key="topic.id"
              class="flex items-start gap-3 rounded-xl border border-transparent px-3 py-2 transition hover:border-emerald-200 hover:bg-emerald-50"
            >
              <input
                v-model="selectedNotificationTopics"
                :value="topic.id"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-emerald-300 text-emerald-500 focus:ring-emerald-400"
                :disabled="!notificationsSupported"
              >
              <div class="flex flex-col">
                <span class="text-sm font-semibold text-emerald-700">
                  {{ topic.label }}
                </span>
                <span class="text-xs text-emerald-500">
                  Termasuk kegiatan {{ topic.label.toLowerCase() }} dan seluruh pegawai.
                </span>
              </div>
            </label>
          </div>
          <p class="mt-4 text-xs leading-relaxed text-emerald-400">
            Pastikan browser diizinkan mengirim notifikasi dan tetap aktif di perangkat Anda.
          </p>
          <div
            v-if="!notificationsSupported"
            class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700"
          >
            Browser Anda tidak mendukung notifikasi.
          </div>
          <div
            v-else-if="notificationPermission === 'denied'"
            class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600"
          >
            Notifikasi diblokir. Aktifkan izin notifikasi di pengaturan browser lalu coba lagi.
          </div>
        </div>
      </div>

      <SuccessPopup :visible="showSuccess" :message="successMessage" />
      <ErrorPopup :visible="showError" :message="errorMessage" />
      <ConfirmPopup
        :visible="showDeleteConfirm"
        :title="deleteConfirmTitle"
        :message="deleteConfirmMessage"
        confirm-text="Hapus"
        cancel-text="Batal"
        :loading="deleteLoading"
        @cancel="cancelDelete"
        @confirm="confirmDelete"
      />

      <EventFormModal
        v-if="showForm"
        :date="selectedDay || currentDate"
        :event="editingEvent"
        :division-options="divisionOptions"
        :participant-options="participantOptions"
        :can-edit="canEdit"
        :can-delete="canDelete"
        @close="showForm = false"
        @saved="onSaved"
        @failed="onFailed"
        @deleted="onDeleted"
      />
    </div>
  </section>
</template>

<style scoped>
</style>

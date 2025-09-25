<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
  date: { type: String, required: true },
  event: { type: Object, default: null },
  divisionOptions: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: true },
  canDelete: { type: Boolean, default: true },
})
const emit = defineEmits(['close','saved','error'])

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
    if (e.start_at) startTime.value = e.start_at.slice(11,16)
    if (e.end_at) endTime.value = e.end_at.slice(11,16)
    divisionIds.value = (e.divisions || []).map(x => x.id)
    participantIdsCsv.value = (e.participants || []).map(x => x.id).join(',')
    recurrenceEnabled.value = false
  } else {
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

const saving = ref(false)

async function submit() {
  if (saving.value) return
  if (formReadOnly.value) return
  saving.value = true
  try {
    await ensureSanctumCookie()
  } catch (e) {
    emit('error', 'Gagal mempersiapkan permintaan. Periksa koneksi Anda.')
    saving.value = false
    return
  }
  const payload = {
    title: title.value,
    description: description.value || null,
    location: location.value || null,
    start_at: allDay.value ? (props.date + 'T07:30:00') : (props.date + 'T' + startTime.value + ':00'),
    end_at: allDay.value ? (props.date + 'T16:00:00') : (props.date + 'T' + endTime.value + ':00'),
    all_day: false,
    division_ids: divisionIds.value,
  }
  const participants = participantIdsCsv.value.split(',').map(s => parseInt(s.trim(),10)).filter(Boolean)
  if (participants.length > 0) payload.participant_user_ids = participants

  if (recurrenceEnabled.value) {
    payload.recurrence_type = recurrenceType.value
    payload.recurrence_interval = Number(recurrenceInterval.value) || 1
    if (recurrenceType.value === 'weekly') {
      payload.recurrence_days = recurrenceDays.value
    } else if (recurrenceType.value === 'monthly') {
      payload.recurrence_month_days = [Number(recurrenceMonthDay.value) || 1]
    }
    if (recurrenceUntil.value) payload.recurrence_until = recurrenceUntil.value
  }

  const url = isEdit.value ? `/api/events/${props.event.id}` : '/api/events'
  const method = isEdit.value ? 'PUT' : 'POST'
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  let res
  try {
    res = await fetch(url, {
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
  } catch (e) {
    emit('error', 'Tidak dapat mengirim data ke server. Coba lagi.')
    saving.value = false
    return
  }
  if (!res.ok) {
    if ([401, 403, 419].includes(res.status)) {      emit('close')
      deleting.value = false
      return
    }
    try {    } catch (e) {    }
    emit('close')
    deleting.value = false
    return
  }
  deleting.value = false
  emit('saved', 'deleted')
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b">
        <div class="text-lg font-semibold text-gray-800">{{ isEdit ? 'Ubah' : 'Buat' }} Kegiatan</div>
        <button class="px-2 py-1 rounded hover:bg-gray-100" @click="$emit('close')">Ã¢Å“â€¢</button>
      </div>
      <div class="space-y-4 p-5 overflow-y-auto flex-1">
        <div>
          <label class="block text-sm mb-1 text-gray-700">Judul</label>
          <input
            v-model="title"
            class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 disabled:bg-gray-100"
            :readonly="formReadOnly"
            :disabled="formReadOnly"
          />
        </div>
        <div class="grid grid-cols-2 gap-3 items-end">
          <div>
            <label class="block text-sm mb-1 text-gray-700">Tanggal</label>
            <input type="date" :value="date" disabled class="w-full border rounded-lg px-3 py-2 bg-gray-50 text-gray-600" />
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" v-model="allDay" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" :disabled="formReadOnly" /> <span>Sepanjang hari</span></label>
          <div>
            <label class="block text-sm mb-1 text-gray-700">Mulai</label>
            <input type="time" v-model="startTime" :disabled="allDay || formReadOnly" class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100" />
          </div>
          <div>
            <label class="block text-sm mb-1 text-gray-700">Selesai</label>
            <input type="time" v-model="endTime" :disabled="allDay || formReadOnly" class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100" />
          </div>
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-700">Lokasi</label>
          <input v-model="location" class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100" :readonly="formReadOnly" :disabled="formReadOnly" />
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-700">Deskripsi</label>
          <textarea v-model="description" class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100" rows="3" :readonly="formReadOnly" :disabled="formReadOnly" />
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-700">Divisi Terlibat</label>
          <div class="flex flex-wrap gap-3">
            <label v-for="d in divisionOptions" :key="d.id" class="flex items-center gap-2">
              <input type="checkbox" :value="d.id" v-model="divisionIds" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" :disabled="formReadOnly" />
              <span>{{ d.name }}</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm mb-1 text-gray-700">Peserta (ID user, pisahkan koma)</label>
          <input v-model="participantIdsCsv" placeholder="cth: 12,45,71" class="w-full border rounded-lg px-3 py-2 disabled:bg-gray-100" :readonly="formReadOnly" :disabled="formReadOnly" />
        </div>

        <div class="border-t pt-3">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-gray-700">Pengulangan</label>
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="recurrenceEnabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" :disabled="formReadOnly" /> Aktif
            </label>
          </div>
          <div v-if="recurrenceEnabled" class="mt-3 space-y-3" :class="formReadOnly ? 'opacity-60 pointer-events-none' : ''">
            <div class="flex items-center gap-3 flex-wrap">
              <label class="text-sm">Tipe</label>
              <select v-model="recurrenceType" class="border rounded-lg px-2 py-1" :disabled="formReadOnly">
                <option value="weekly">Mingguan</option>
                <option value="monthly">Bulanan</option>
              </select>
              <label class="text-sm">Interval</label>
              <input type="number" min="1" v-model.number="recurrenceInterval" class="w-20 border rounded-lg px-2 py-1" :disabled="formReadOnly" />
              <span class="text-xs text-gray-500" v-if="recurrenceType==='weekly'">(setiap n minggu)</span>
              <span class="text-xs text-gray-500" v-else>(setiap n bulan)</span>
            </div>
            <div v-if="recurrenceType==='weekly'">
              <div class="text-sm text-gray-700 mb-1">Pilih hari</div>
              <div class="flex flex-wrap gap-2">
                <label v-for="(nm, idx) in ['Sen','Sel','Rab','Kam','Jum','Sab','Min']" :key="idx" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm cursor-pointer select-none">
                  <input type="checkbox" :value="idx+1" v-model="recurrenceDays" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400" :disabled="formReadOnly" />
                  <span>{{ nm }}</span>
                </label>
              </div>
            </div>
            <div v-else>
              <div class="text-sm text-gray-700 mb-1">Tanggal setiap bulan</div>
              <input type="number" min="1" max="31" v-model.number="recurrenceMonthDay" class="w-24 border rounded-lg px-2 py-1" :disabled="formReadOnly" />
            </div>
            <div>
              <label class="block text-sm mb-1 text-gray-700">Sampai tanggal (opsional)</label>
              <input type="date" v-model="recurrenceUntil" class="border rounded-lg px-2 py-1" :disabled="formReadOnly" />
            </div>
          </div>
        </div>
      </div>
      <div class="px-5 py-4 border-t flex items-center justify-between gap-2">
        <button
          v-if="isEdit && props.canDelete"
          class="px-4 py-2 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 active:scale-95 transition disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="formReadOnly || deleting"
          @click="doDelete"
        >
          {{ deleting ? 'Menghapus...' : 'Hapus' }}
        </button>
        <div class="ml-auto flex items-center gap-2">
          <button class="px-4 py-2 rounded-lg border hover:bg-gray-50 active:scale-95 transition" @click="$emit('close')">Batal</button>
          <button
            :disabled="saving || formReadOnly"
            class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 active:scale-95 transition disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2"
            @click="submit"
          >
            <span v-if="saving" class="inline-block h-4 w-4 border-2 border-current border-r-transparent rounded-full animate-spin"></span>
            <span>Simpan</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>




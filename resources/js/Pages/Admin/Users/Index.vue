<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => ['viewer','editor','admin'] },
  adminEmail: { type: String, default: '' },
})

const page = usePage()
const authUser = computed(() => page.props?.auth?.user ?? null)

// Local editable copy
const rows = ref(props.users.map(u => ({ ...u })))

const saving = reactive({})
const notices = ref('')
const selectedDate = ref(new Date().toISOString().slice(0,10))
const userLogs = ref([])
const eventLogs = ref([])
const loadingUserLogs = ref(false)
const loadingEventLogs = ref(false)
const selectedEventDate = ref(new Date().toISOString().slice(0,10))

async function fetchUserLogs() {
  loadingUserLogs.value = true
  try {
    const params = new URLSearchParams()
    if (selectedDate.value) params.set('date', selectedDate.value)
    const res = await fetch(`/admin/users/logs?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    userLogs.value = data?.data || []
  } finally {
    loadingUserLogs.value = false
  }
}

async function fetchEventLogs() {
  loadingEventLogs.value = true
  try {
    const params = new URLSearchParams()
    if (selectedEventDate.value) params.set('date', selectedEventDate.value)
    const res = await fetch(`/admin/events/logs?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    eventLogs.value = data?.data || []
  } finally {
    loadingEventLogs.value = false
  }
}

onMounted(() => {
  fetchUserLogs()
  fetchEventLogs()
})

function goBack() {
  router.visit('/calendar', { preserveScroll: true })
}

function newChange(item, key) {
  const pair = item?.changes?.[key]
  if (Array.isArray(pair)) return pair[1] ?? pair[0] ?? null
  return null
}

function displayDivisions(item) {
  const v = newChange(item, 'division_ids')
  if (Array.isArray(v)) return v.length ? v.join(', ') : '[]'
  return v ?? '-'
}

async function saveRow(idx) {
  const u = rows.value[idx]
  if (!u) return
  if (saving[u.id]) return
  saving[u.id] = true
  notices.value = ''
  router.put(`/admin/users/${u.id}`,
  {
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
      alert(message)
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
    <div class="mx-auto max-w-6xl space-y-6">
      <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 px-5 py-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold text-emerald-700">Manajemen Pengguna</h1>
            <p class="text-sm text-emerald-800/80">Hanya admin yang dapat mengubah peran pengguna. Akun super admin tidak dapat diubah perannya.</p>
          </div>
          <div>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50"
              @click="goBack"
            >
              ← Kembali ke Kalender
            </button>
          </div>
        </div>
      </div>

      
      <!-- Notices (save feedback) -->
      <div v-if="notices" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-emerald-800">{{ notices }}</div>

      <!-- User management table (moved above calendar changes) -->
      <div class="overflow-x-auto rounded-2xl border border-emerald-100 bg-white p-4">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-emerald-50/50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Nama</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Email</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">NIP</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Telepon</th>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Peran</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(u, idx) in rows" :key="u.id" class="hover:bg-emerald-50/30">
              <td class="px-3 py-2">
                <input v-model="u.name" class="w-56 rounded-md border px-2 py-1" />
              </td>
              <td class="px-3 py-2">
                <input v-model="u.email" class="w-64 rounded-md border px-2 py-1 disabled:bg-gray-100" :disabled="isSuperAdmin(u)" />
              </td>
              <td class="px-3 py-2">
                <input v-model="u.nip" class="w-40 rounded-md border px-2 py-1" />
              </td>
              <td class="px-3 py-2">
                <input v-model="u.phone" class="w-40 rounded-md border px-2 py-1" />
              </td>
              <td class="px-3 py-2">
                <select v-model="u.role" class="w-36 rounded-md border px-2 py-1 disabled:bg-gray-100" :disabled="isSuperAdmin(u)">
                  <option v-for="r in props.roles" :key="r" :value="r">{{ r }}</option>
                </select>
              </td>
              <td class="px-3 py-2 text-right">
                <button
                  class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700 active:scale-95 disabled:opacity-60"
                  :disabled="saving[u.id]"
                  @click="saveRow(idx)"
                >
                  <span v-if="saving[u.id]" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent"></span>
                  <span>Simpan</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Event logs box (separate card) -->
      <div class="rounded-2xl border border-emerald-100 bg-white p-5">
        <!-- Event logs section -->
        <div class="mb-4 flex items-center gap-3">
          <div class="text-sm font-semibold text-emerald-700">Perubahan Kalender</div>
          <input type="date" v-model="selectedEventDate" class="rounded-md border px-2 py-1" />
          <button @click="fetchEventLogs()" class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700 hover:bg-emerald-100">Tampilkan</button>
        </div>
        <div v-if="loadingEventLogs" class="text-sm text-slate-500">Memuat log...</div>
        <div v-else>
          <div v-if="!eventLogs.length" class="text-sm text-slate-500">Belum ada log kalender untuk tanggal ini.</div>
          <ul v-else class="space-y-3">
            <li v-for="item in eventLogs" :key="item.id" class="rounded-lg border border-slate-200 p-3">
              <div class="text-xs text-slate-500">{{ new Date(item.at).toLocaleString() }}</div>
              <div class="text-sm text-slate-800">
                <span class="font-semibold">{{ item.actor?.name }}</span>
                (<span class="text-slate-600">{{ item.actor?.email }}</span>)
                <span> {{ item.action === 'create_event' ? 'menambahkan' : (item.action === 'update_event' ? 'mengubah' : 'menghapus') }} kegiatan</span>
                <span class="font-semibold">{{ item.event?.title || '(kegiatan terhapus)' }}</span>
              </div>
              <div class="mt-2 text-sm">
                <div class="text-slate-600">Detail Kegiatan:</div>
                <ul class="mt-1 grid gap-1 sm:grid-cols-2">
                  <li class="text-slate-700"><span class="font-medium">Judul</span>: {{ newChange(item, 'title') ?? (item.event?.title || '-') }}</li>
                  <li class="text-slate-700"><span class="font-medium">Mulai</span>: {{ newChange(item, 'start_at') || '-' }}</li>
                  <li class="text-slate-700"><span class="font-medium">Selesai</span>: {{ newChange(item, 'end_at') || '-' }}</li>
                  <li class="text-slate-700"><span class="font-medium">Divisi</span>: {{ displayDivisions(item) }}</li>
                </ul>
              </div>
            </li>
          </ul>
        </div>
      </div>

      

      <!-- Logs viewer -->
      <div class="rounded-2xl border border-emerald-100 bg-white p-5">
        <div class="mb-4 flex items-center gap-3">
          <div class="text-sm font-semibold text-emerald-700">Perubahan Pengguna</div>
          <input type="date" v-model="selectedDate" class="rounded-md border px-2 py-1" />
          <button @click="fetchUserLogs(); fetchEventLogs()" class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700 hover:bg-emerald-100">Tampilkan</button>
        </div>
        <div v-if="loadingUserLogs" class="text-sm text-slate-500">Memuat log...</div>
        <div v-else>
          <div v-if="!userLogs.length" class="text-sm text-slate-500">Belum ada log untuk tanggal ini.</div>
          <ul v-else class="space-y-3">
            <li v-for="item in userLogs" :key="item.id" class="rounded-lg border border-slate-200 p-3">
              <div class="text-xs text-slate-500">{{ new Date(item.at).toLocaleString() }}</div>
              <div class="text-sm text-slate-800">
                <span class="font-semibold">{{ item.actor?.name }}</span>
                (<span class="text-slate-600">{{ item.actor?.email }}</span>)
                mengubah
                <span class="font-semibold">{{ item.user?.name }}</span>
                (<span class="text-slate-600">{{ item.user?.email }}</span>)
              </div>
              <div class="mt-2 text-sm">
                <div class="text-slate-600">Perubahan:</div>
                <ul class="mt-1 grid gap-1 sm:grid-cols-2">
                  <li v-for="(pair, key) in item.changes" :key="key" class="text-slate-700">
                    <span class="font-medium">{{ key }}</span>:
                    <span class="line-through text-red-600/80 mr-1">{{ Array.isArray(pair) ? (pair?.[0] ?? '-' ) : '-' }}</span>
                    ->
                    <span class="text-emerald-700 ml-1">{{ Array.isArray(pair) ? (pair?.[1] ?? '-' ) : '-' }}</span>
                  </li>
                </ul>
              </div>
            </li>
          </ul>
        </div>
      </div>

      
    </div>
  </section>
</template>

<style scoped>
</style>

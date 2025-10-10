<script setup>
import { router } from '@inertiajs/vue3'
import { computed, inject, ref } from 'vue'

const props = defineProps({
  user: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const loggingOut = ref(false)
const route = inject('route')

const name = computed(() => props.user?.name ?? '-')
const email = computed(() => props.user?.email ?? '-')
const nip = computed(() => props.user?.nip ?? '-')
const phone = computed(() => props.user?.phone ?? '-')
const isAdmin = computed(() => (props.user?.role ?? 'viewer') === 'admin')

async function handleLogout() {
  if (loggingOut.value) {
    return
  }

  loggingOut.value = true

  try {
    await router.post(route('logout'), {}, { preserveScroll: true })
  }
  finally {
    loggingOut.value = false
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    emit('close')
  }
}

function goToUserManagement() {
  if (!isAdmin.value)
    return
  try {
    router.visit(route('admin.users.index'))
  }
  finally {
    emit('close')
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/45 backdrop-blur-sm px-4"
    tabindex="0"
    @click.self="emit('close')"
    @keydown="handleKeydown"
  >
    <div class="flex w-full max-w-xl flex-col rounded-[28px] border border-emerald-100 bg-white shadow-[0_45px_120px_-80px_rgba(16,185,129,0.6)]">
      <div class="relative border-b border-emerald-50 px-6 pt-6 pb-4 text-center text-emerald-800">
        <button
          type="button"
          class="absolute right-6 top-6 inline-flex h-10 w-10 items-center justify-center rounded-full border border-emerald-200 text-emerald-600 transition hover:bg-emerald-50"
          aria-label="Tutup"
          @click="emit('close')"
        >
          &times;
        </button>
        <h2 class="text-xl font-semibold md:text-2xl">
          Kelola Profil
        </h2>
        <p class="text-xs text-emerald-600/80 md:text-sm">
          Ringkasan akun dan tindakan cepat.
        </p>
      </div>

      <div class="max-h-[65vh] overflow-y-auto px-6 pb-6 pt-6">
        <div class="space-y-6">
          <div class="text-center space-y-2">
            <div class="text-xs uppercase tracking-[0.35em] text-emerald-500">
              Pengguna aktif
            </div>
            <div class="text-lg font-semibold text-emerald-700">
              {{ name }}
            </div>
          </div>

          <dl class="space-y-3 text-sm text-slate-700">
            <div class="flex flex-col gap-1 border-b border-emerald-100 pb-3">
              <dt class="text-xs uppercase tracking-wide text-slate-400">
                Email
              </dt>
              <dd class="font-medium">
                {{ email }}
              </dd>
            </div>
            <div class="flex flex-col gap-1 border-b border-emerald-100 pb-3">
              <dt class="text-xs uppercase tracking-wide text-slate-400">
                NIP
              </dt>
              <dd class="font-medium">
                {{ nip }}
              </dd>
            </div>
            <div class="flex flex-col gap-1">
              <dt class="text-xs uppercase tracking-wide text-slate-400">
                Telepon
              </dt>
              <dd class="font-medium">
                {{ phone }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3 border-t border-emerald-50 px-6 py-5">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!isAdmin"
          title="Buka manajemen pengguna"
          @click="goToUserManagement"
        >
          Manajemen Pengguna
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-300/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300 disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="loggingOut"
          @click="handleLogout"
        >
          <span v-if="loggingOut" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent" />
          <span>{{ loggingOut ? 'Memproses...' : 'Keluar dari akun' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>

<script setup>
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
  user: { type: Object, required: true },
})
defineEmits(['close'])

const loggingOut = ref(false)

const name = computed(() => props.user?.name || '-')
const nip = computed(() => props.user?.nip || '-')
const email = computed(() => props.user?.email || '-')
const phone = computed(() => props.user?.phone || '-')

async function handleLogout() {
  if (loggingOut.value)
    return
  loggingOut.value = true
  try {
    await router.post(route('logout'), {}, { preserveScroll: true })
  }
  finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b">
        <div class="text-lg font-semibold text-gray-800">
          Profil Saya
        </div>
        <button class="px-2 py-1 rounded hover:bg-gray-100" aria-label="Tutup" @click="$emit('close')">
          &times;
        </button>
      </div>

      <div class="p-5 overflow-y-auto flex-1">
        <dl class="space-y-4 text-sm">
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">
              Nama
            </dt>
            <dd class="text-right font-medium text-slate-700">
              {{ name }}
            </dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">
              NIP
            </dt>
            <dd class="text-right font-medium text-slate-700">
              {{ nip }}
            </dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">
              Email
            </dt>
            <dd class="text-right font-medium text-slate-700">
              {{ email }}
            </dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">
              Nomor Telepon
            </dt>
            <dd class="text-right font-medium text-slate-700">
              {{ phone }}
            </dd>
          </div>
        </dl>
      </div>

      <div class="px-5 py-4 border-t flex items-center justify-end gap-2">
        <button class="px-4 py-2 rounded-lg border hover:bg-gray-50 active:scale-95 transition" @click="$emit('close')">
          Tutup
        </button>
        <button :disabled="loggingOut" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2" @click="handleLogout">
          <span v-if="loggingOut" class="inline-block h-4 w-4 border-2 border-current border-r-transparent rounded-full animate-spin" />
          <span>Keluar</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>

<script setup>
const props = defineProps({
  visible: { type: Boolean, default: false },
  title: { type: String, default: 'Konfirmasi' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Hapus' },
  cancelText: { type: String, default: 'Batal' },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <transition name="fade-pop">
    <div
      v-if="visible"
      class="fixed inset-0 z-[2150] flex items-center justify-center bg-black/40 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md rounded-3xl bg-white px-10 py-8 text-center shadow-[0_45px_120px_-40px_rgba(248,113,113,0.55)]">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z" />
          </svg>
        </div>
        <h3 class="mt-4 text-xl font-semibold text-gray-800">
          {{ title }}
        </h3>
        <p v-if="message" class="mt-2 text-sm text-gray-600 whitespace-pre-line">
          {{ message }}
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading"
            @click="$emit('cancel')"
          >
            {{ cancelText }}
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-500 via-rose-500 to-pink-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-300/40 transition hover:from-red-400 hover:via-rose-400 hover:to-pink-400 disabled:cursor-not-allowed disabled:opacity-70"
            :disabled="loading"
            @click="$emit('confirm')"
          >
            <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
            <span>{{ confirmText }}</span>
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-pop-enter-active,
.fade-pop-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}
.fade-pop-enter-from,
.fade-pop-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>

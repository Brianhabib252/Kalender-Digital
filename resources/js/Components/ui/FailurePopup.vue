<template>
  <transition name="fade-pop">
    <div
      v-if="visible"
      class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
      <div class="relative flex flex-col items-center gap-4 rounded-3xl bg-white px-12 py-10 text-center shadow-[0_45px_120px_-40px_rgba(244,63,94,0.45)]">
        <div class="failure-cross h-16 w-16">
          <svg viewBox="0 0 52 52" class="failure-cross__svg">
            <circle cx="26" cy="26" r="25" class="failure-cross__circle" fill="none" />
            <path class="failure-cross__line failure-cross__line--one" fill="none" d="M18 18 L34 34" />
            <path class="failure-cross__line failure-cross__line--two" fill="none" d="M34 18 L18 34" />
          </svg>
        </div>
        <p class="text-lg font-semibold text-rose-600">{{ message }}</p>
      </div>
    </div>
  </transition>
</template>

<script setup>
const props = defineProps({
  visible: { type: Boolean, default: false },
  message: { type: String, default: 'Terjadi kesalahan. Silakan coba lagi.' },
})
</script>

<style scoped>
.fade-pop-enter-active,
.fade-pop-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.fade-pop-enter-from,
.fade-pop-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

.failure-cross__svg {
  width: 100%;
  height: 100%;
}
.failure-cross__circle {
  stroke: #f87171;
  stroke-width: 3;
  stroke-dasharray: 166;
  stroke-dashoffset: 166;
  animation: circle 0.6s ease forwards;
}
.failure-cross__line {
  stroke: #f87171;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 24;
  stroke-dashoffset: 24;
}
.failure-cross__line--one {
  animation: cross-line 0.35s 0.2s ease forwards;
}
.failure-cross__line--two {
  animation: cross-line 0.35s 0.35s ease forwards;
}

@keyframes circle {
  to { stroke-dashoffset: 0; }
}

@keyframes cross-line {
  to { stroke-dashoffset: 0; }
}
</style>

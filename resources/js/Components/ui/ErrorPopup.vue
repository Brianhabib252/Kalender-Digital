<script setup>
const props = defineProps({
  visible: { type: Boolean, default: false },
  message: { type: String, default: 'Terjadi kesalahan' },
})
</script>

<template>
  <transition name="fade-pop">
    <div
      v-if="visible"
      class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
      <div class="relative flex flex-col items-center gap-4 rounded-3xl bg-white px-12 py-10 text-center shadow-[0_45px_120px_-40px_rgba(248,113,113,0.55)]">
        <div class="error-x h-16 w-16">
          <svg viewBox="0 0 52 52" class="error-x__svg">
            <circle cx="26" cy="26" r="25" class="error-x__circle" fill="none" />
            <path class="error-x__line" fill="none" d="M18 18 L34 34" />
            <path class="error-x__line" fill="none" d="M34 18 L18 34" />
          </svg>
        </div>
        <p class="text-lg font-semibold text-red-600">
          {{ message }}
        </p>
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

.error-x {
  position: relative;
}
.error-x__svg {
  width: 100%;
  height: 100%;
}
.error-x__circle {
  stroke: #f87171;
  stroke-width: 3;
  stroke-dasharray: 166;
  stroke-dashoffset: 166;
  animation: circle 0.6s ease forwards;
}
.error-x__line {
  stroke: #dc2626;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 23;
  stroke-dashoffset: 23;
  animation: check 0.35s 0.2s ease forwards;
}

@keyframes circle {
  to {
    stroke-dashoffset: 0;
  }
}

@keyframes check {
  to {
    stroke-dashoffset: 0;
  }
}
</style>

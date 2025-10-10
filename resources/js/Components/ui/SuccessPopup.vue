<script setup>
const props = defineProps({
  visible: { type: Boolean, default: false },
  message: { type: String, default: 'Berhasil disimpan' },
})
</script>

<template>
  <transition name="fade-pop">
    <div
      v-if="visible"
      class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40 backdrop-blur-sm"
    >
      <div class="relative flex flex-col items-center gap-4 rounded-3xl bg-white px-12 py-10 text-center shadow-[0_45px_120px_-40px_rgba(16,185,129,0.55)]">
        <div class="success-check h-16 w-16">
          <svg viewBox="0 0 52 52" class="success-check__svg">
            <circle cx="26" cy="26" r="25" class="success-check__circle" fill="none" />
            <path class="success-check__check" fill="none" d="M16 26 l8 8 12-16" />
          </svg>
        </div>
        <p class="text-lg font-semibold text-emerald-700">
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

.success-check {
  position: relative;
}
.success-check__svg {
  width: 100%;
  height: 100%;
}
.success-check__circle {
  stroke: #34d399;
  stroke-width: 3;
  stroke-dasharray: 166;
  stroke-dashoffset: 166;
  animation: circle 0.6s ease forwards;
}
.success-check__check {
  stroke: #059669;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 48;
  stroke-dashoffset: 48;
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

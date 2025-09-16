<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: { type: Object, required: true }
})

const palette = [
  'bg-indigo-100 text-indigo-800 ring-indigo-300',
  'bg-emerald-100 text-emerald-800 ring-emerald-300',
  'bg-amber-100 text-amber-800 ring-amber-300',
  'bg-sky-100 text-sky-800 ring-sky-300',
  'bg-rose-100 text-rose-800 ring-rose-300'
]

const colorClass = computed(() => {
  const key = (props.event?.divisions?.[0]?.id ?? props.event?.id ?? 0)
  return palette[key % palette.length]
})
</script>

<template>
  <div :class="'px-3 py-1.5 text-sm rounded-md truncate cursor-pointer ring-1 ring-inset hover:opacity-90 transition transform-gpu duration-200 hover:scale-[1.02] active:scale-95 ' + colorClass">
    <span v-if="!event.all_day" class="font-mono text-[10px] mr-1 opacity-80">{{ event.start_at?.slice(11,16) }}-{{ event.end_at?.slice(11,16) }}</span>
    <span class="font-medium">{{ event.title }}</span>
  </div>
</template>

<style scoped>
</style>

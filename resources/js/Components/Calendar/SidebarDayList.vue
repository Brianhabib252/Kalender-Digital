<script setup>
const props = defineProps({
  date: { type: String, required: true },
  events: { type: Array, default: () => [] },
  bare: { type: Boolean, default: false }, // render only the list (no outer card/header)
})
const emit = defineEmits(['open-edit','delete-event'])

function ofDay(date) {
  const ds = new Date(date+'T00:00:00')
  const de = new Date(date+'T23:59:59')
  return props.events.filter(e => {
    const s = parseLocal(e.start_at); const n = parseLocal(e.end_at)
    return (s <= de && n >= ds) || e.all_day
  })
}

function parseLocal(str){
  const m = str && str.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (m) { const [,y,mo,d,h,mi,s] = m; return new Date(+y, +mo-1, +d, +h, +mi, +(s||0)) }
  return new Date(str)
}

function fmt12(str){
  const d = parseLocal(str)
  if (isNaN(d)) return ''
  let h = d.getHours(); const m = d.getMinutes()
  const am = h < 12
  h = h % 12; if (h === 0) h = 12
  return `${h}:${String(m).padStart(2,'0')} ${am ? 'AM' : 'PM'}`
}

const palette = [
  'from-emerald-400 to-teal-500',
  'from-sky-400 to-indigo-500',
  'from-amber-400 to-orange-500',
  'from-rose-400 to-pink-500',
  'from-purple-400 to-fuchsia-500'
]
function colorClass(e){
  const key = (e?.divisions?.[0]?.id ?? e?.id ?? 0)
  return palette[key % palette.length]
}
</script>

<template>
  <div v-if="!bare" class="bg-white rounded-2xl shadow-sm p-5 border">
    <div class="text-lg font-semibold text-gray-700 mb-3">Kegiatan {{ date }}</div>
    <div v-if="ofDay(date).length===0" class="text-lg text-gray-500">Tidak ada kegiatan.</div>
    <div v-for="e in ofDay(date)" :key="e.id" class="relative mb-4 p-4 rounded-xl border bg-white transition-all hover:shadow-lg">
      <div class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-xl bg-gradient-to-b" :class="colorClass(e)"></div>
      <div class="pl-3 flex items-start gap-3">
        <div class="flex-1 cursor-pointer" @click="() => emit('open-edit', e)">
          <div class="text-lg font-bold text-gray-800 flex items-center gap-2">
            {{ e.title }}
            <span v-if="!e.all_day" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5"><path d="M12 8a1 1 0 0 1 1 1v3.586l2.121 2.121a1 1 0 1 1-1.414 1.414l-2.414-2.414A1.997 1.997 0 0 1 10 13V9a1 1 0 0 1 1-1z"/></svg>
              {{ fmt12(e.start_at) }} - {{ fmt12(e.end_at) }}
            </span>
          </div>
          <div v-if="e.location" class="mt-1 text-sm text-gray-600">{{ e.location }}</div>
          <div v-if="e.divisions && e.divisions.length" class="mt-2 flex flex-wrap gap-1">
            <span v-for="d in e.divisions" :key="d.id" class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700 border">
              {{ d.name }}
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="h-9 w-9 inline-flex items-center justify-center rounded-full border text-gray-700 hover:bg-gray-50 active:scale-95 transition" @click.stop="() => emit('open-edit', e)" title="Ubah">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.313 3 21l1.687-4.5 12.175-13.013z"/></svg>
          </button>
          <button class="h-9 w-9 inline-flex items-center justify-center rounded-full bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 active:scale-95 transition" @click.stop="() => emit('delete-event', e)" title="Hapus">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M9 3a1 1 0 0 0-1 1v1H5.5A1.5 1.5 0 0 0 4 6.5V7h16v-.5A1.5 1.5 0 0 0 18.5 5H16V4a1 1 0 0 0-1-1H9zm10 5H5l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12z"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-else>
    <div v-if="ofDay(date).length===0" class="text-base text-gray-500">Tidak ada kegiatan.</div>
    <div v-for="e in ofDay(date)" :key="e.id" class="relative mb-4 p-4 rounded-xl border bg-white transition-all hover:shadow-lg">
      <div class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-xl bg-gradient-to-b" :class="colorClass(e)"></div>
      <div class="pl-3 flex items-start gap-3">
        <div class="flex-1 cursor-pointer" @click="() => emit('open-edit', e)">
          <div class="text-lg font-bold text-gray-800 flex items-center gap-2">
            {{ e.title }}
            <span v-if="!e.all_day" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5"><path d="M12 8a1 1 0 0 1 1 1v3.586l2.121 2.121a1 1 0 1 1-1.414 1.414l-2.414-2.414A1.997 1.997 0 0 1 10 13V9a1 1 0 0 1 1-1z"/></svg>
              {{ fmt12(e.start_at) }} - {{ fmt12(e.end_at) }}
            </span>
          </div>
          <div v-if="e.location" class="mt-1 text-sm text-gray-600">{{ e.location }}</div>
          <div v-if="e.divisions && e.divisions.length" class="mt-2 flex flex-wrap gap-1">
            <span v-for="d in e.divisions" :key="d.id" class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700 border">
              {{ d.name }}
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="h-9 w-9 inline-flex items-center justify-center rounded-full border text-gray-700 hover:bg-gray-50 active:scale-95 transition" @click.stop="() => emit('open-edit', e)" title="Ubah">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.313 3 21l1.687-4.5 12.175-13.013z"/></svg>
          </button>
          <button class="h-9 w-9 inline-flex items-center justify-center rounded-full bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 active:scale-95 transition" @click.stop="() => emit('delete-event', e)" title="Hapus">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M9 3a1 1 0 0 0-1 1v1H5.5A1.5 1.5 0 0 0 4 6.5V7h16v-.5A1.5 1.5 0 0 0 18.5 5H16V4a1 1 0 0 0-1-1H9zm10 5H5l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12z"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>

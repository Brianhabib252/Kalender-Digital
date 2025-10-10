<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { computed, inject } from 'vue'
import InputError from '@/components/InputError.vue'
import AuthenticationCardLogo from '@/components/LogoRedirect.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Label from '@/components/ui/label/Label.vue'
import { useSeoMetaTags } from '@/composables/useSeoMetaTags.js'

useSeoMetaTags({
  title: 'Register',
})

const route = inject('route')

const form = useForm({
  name: '',
  email: '',
  nip: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const isProcessing = computed(() => form.processing)

function submit() {
  form
    .transform(data => ({
      ...data,
      name: data.name.trim(),
      email: data.email.trim(),
      nip: data.nip.trim(),
      phone: data.phone.trim(),
    }))
    .post(route('register'), {
      onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
  <main class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-sky-50">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-16">
      <div class="grid gap-10 rounded-3xl border border-emerald-100 bg-white/85 p-8 shadow-[0_45px_120px_-60px_rgba(16,185,129,0.55)] md:grid-cols-[1.05fr,0.95fr] md:p-14">
        <section class="flex flex-col justify-between text-emerald-900">
          <div class="space-y-8">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-inner">
              <AuthenticationCardLogo />
            </span>
            <div class="space-y-3">
              <p class="text-xs uppercase tracking-[0.45em] text-emerald-500/90">
                Kalender Digital
              </p>
              <h1 class="text-3xl font-semibold leading-tight md:text-4xl">
                Buat akun pengelola
              </h1>
              <p class="max-w-md text-sm text-emerald-800/80">
                Personalisasi pengalaman kalender, tambahkan kegiatan, dan bagikan informasi antar divisi dengan mudah.
              </p>
            </div>
            <ul class="space-y-3 text-sm text-emerald-800/70">
              <li class="flex items-start gap-3">
                <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400" />
                <span>Kelola jadwal lintas ruangan dengan pengingat yang konsisten.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400" />
                <span>Tandai peserta dan divisi agar semua pihak menerima informasi terbaru.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400" />
                <span>Analisis beban kerja dengan ringkasan mingguan dan bulanan.</span>
              </li>
            </ul>
          </div>
          <div class="hidden items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5 text-xs text-emerald-700 shadow-inner md:flex">
            <svg class="h-6 w-6 text-emerald-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 4h12a2 2 0 0 1 2 2v13l-8-3-8 3V6a2 2 0 0 1 2-2z" fill="currentColor" />
            </svg>
            <p class="max-w-xs leading-relaxed">
              Kalender digital dirancang dengan keamanan berlapis untuk melindungi data kegiatan instansi Anda.
            </p>
          </div>
        </section>
        <section class="rounded-3xl border border-emerald-100 bg-white p-8 text-slate-700 shadow-xl">
          <header class="space-y-1">
            <h2 class="text-2xl font-semibold text-slate-900">
              Daftar akun baru
            </h2>
            <p class="text-sm text-slate-500">
              Lengkapi informasi berikut untuk mulai menggunakan kalender.
            </p>
          </header>

          <form class="mt-8 space-y-6" @submit.prevent="submit">
            <div class="space-y-2">
              <Label for="name" class="text-sm font-medium text-slate-600">Nama lengkap</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="Nama lengkap"
                autocomplete="name"
                autofocus
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-2">
              <Label for="email" class="text-sm font-medium text-slate-600">Email</Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="nama@kantor.go.id"
                autocomplete="username"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-2">
              <Label for="nip" class="text-sm font-medium text-slate-600">NIP</Label>
              <Input
                id="nip"
                v-model="form.nip"
                type="text"
                placeholder="NIP pegawai"
                autocomplete="off"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.nip" />
            </div>

            <div class="space-y-2">
              <Label for="phone" class="text-sm font-medium text-slate-600">Nomor telepon</Label>
              <Input
                id="phone"
                v-model="form.phone"
                type="text"
                placeholder="08xxxxxxxxxx"
                autocomplete="tel"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.phone" />
            </div>

            <div class="space-y-2">
              <Label for="password" class="text-sm font-medium text-slate-600">Password</Label>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-2">
              <Label for="password_confirmation" class="text-sm font-medium text-slate-600">Konfirmasi password</Label>
              <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
              type="submit"
              class="w-full rounded-xl bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 text-white shadow-lg shadow-emerald-400/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300"
              :class="{ 'opacity-70': isProcessing }"
              :disabled="isProcessing"
            >
              {{ isProcessing ? 'Memproses...' : 'Daftar' }}
            </Button>
          </form>

          <p class="mt-6 text-center text-sm text-slate-500">
            Sudah memiliki akun?
            <Link :href="route('login')" class="font-semibold text-emerald-500 hover:text-emerald-400">
              Masuk di sini
            </Link>
          </p>
        </section>
      </div>
    </div>
  </main>
</template>

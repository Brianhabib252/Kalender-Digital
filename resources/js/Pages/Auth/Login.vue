<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { computed, inject } from 'vue'
import InputError from '@/components/InputError.vue'
import AuthenticationCardLogo from '@/components/LogoRedirect.vue'
import AnimatedBubbleBackground from '@/components/Decor/AnimatedBubbleBackground.vue'
import Button from '@/components/ui/button/Button.vue'
import Checkbox from '@/components/ui/checkbox/Checkbox.vue'
import Input from '@/components/ui/input/Input.vue'
import Label from '@/components/ui/label/Label.vue'
import { useSeoMetaTags } from '@/composables/useSeoMetaTags.js'

defineProps({
  canResetPassword: Boolean,
  status: String,
})

const route = inject('route')

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const isProcessing = computed(() => form.processing)

function submit() {
  form
    .transform(data => ({
      ...data,
      email: data.email.trim(),
      remember: data.remember ? 'on' : '',
    }))
    .post(route('login'), {
      onFinish: () => form.reset('password'),
    })
}

useSeoMetaTags({
  title: 'Log in',
})
</script>

<template>
  <main class="relative isolate min-h-screen overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-sky-50">
    <AnimatedBubbleBackground />
    <div class="relative z-10 mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-16">
      <div class="grid gap-10 rounded-3xl border border-emerald-100 bg-white/80 p-8 shadow-[0_45px_120px_-60px_rgba(16,185,129,0.55)] md:grid-cols-[1.05fr,0.95fr] md:p-14">
        <section class="flex flex-col items-center justify-center text-center text-emerald-900">
          <div class="max-w-lg space-y-6">
            <div class="flex items-center justify-center gap-6">
              <span class="flex items-center justify-center">
                <AuthenticationCardLogo />
              </span>
              <p class="text-4xl font-bold uppercase tracking-[0.55em] text-emerald-600 md:text-5xl">
                Kalender Digital
              </p>
            </div>
            <p class="text-base text-emerald-800/85 md:text-lg">
              Kelola agenda lembaga, lihat timeline harian, dan pantau keterlibatan divisi dalam satu tampilan yang konsisten.
            </p>
          </div>
        </section>
        <section class="rounded-3xl border border-emerald-100 bg-white p-8 text-slate-700 shadow-xl">
          <header class="space-y-1">
            <h2 class="text-2xl font-semibold text-slate-900">
              Masuk ke akun
            </h2>
            <p class="text-sm text-slate-500">
              Gunakan kredensial internal Anda untuk melanjutkan.
            </p>
          </header>

          <div v-if="status" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ status }}
          </div>

          <form class="mt-8 space-y-6" @submit.prevent="submit">
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
              <div class="flex items-center justify-between text-sm">
                <Label for="password" class="font-medium text-slate-600">Password</Label>
                <Link
                  v-if="canResetPassword"
                  :href="route('password.request')"
                  class="text-emerald-500 transition hover:text-emerald-400"
                >
                  Lupa password?
                </Link>
              </div>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                class="h-11 w-full rounded-xl border border-emerald-100 bg-white text-slate-800 placeholder:text-slate-400 focus:border-emerald-400 focus:ring-emerald-300"
              />
              <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between text-sm text-slate-500">
              <label class="inline-flex items-center gap-2 font-medium">
                <Checkbox id="remember" v-model:checked="form.remember" name="remember" class="border-emerald-200 text-emerald-500" />
                Ingat saya
              </label>
            </div>

            <Button
              type="submit"
              class="w-full rounded-xl bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 text-white shadow-lg shadow-emerald-400/40 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300"
              :class="{ 'opacity-70': isProcessing }"
              :disabled="isProcessing"
            >
              {{ isProcessing ? 'Memproses...' : 'Masuk' }}
            </Button>
          </form>

          <p class="mt-6 text-center text-sm text-slate-500">
            Belum punya akun?
            <Link :href="route('register')" class="font-semibold text-emerald-500 hover:text-emerald-400">
              Daftar sekarang
            </Link>
          </p>
        </section>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue'
import LinkedAccountsForm from '@/Pages/Profile/Partials/LinkedAccountsForm.vue'
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue'
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue'
import ProfileQuickModal from '@/Components/Profile/ProfileQuickModal.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const summaryOpen = ref(false)

defineProps({
  confirmsTwoFactorAuthentication: {
    type: Boolean,
    default: false,
  },
  sessions: {
    type: Array,
    default: () => [],
  },
  availableOauthProviders: {
    type: Object,
    default: () => {},
  },
  activeOauthProviders: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <AppLayout title="Pengaturan Profil">
    <template #header>
      <div class="rounded-3xl border border-emerald-100 bg-gradient-to-r from-emerald-100 via-emerald-50 to-white p-6 md:p-8 shadow-[0_35px_120px_-60px_rgba(16,185,129,0.35)]">
        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div class="space-y-3 text-emerald-900">
            <p class="text-xs uppercase tracking-[0.45em] text-emerald-500/90">Profil</p>
            <div class="space-y-2">
              <h1 class="text-2xl font-semibold md:text-3xl">Halo, {{ user?.name }}</h1>
              <p class="max-w-xl text-sm text-emerald-600/80">
                Sesuaikan informasi diri, keamanan akun, dan perangkat yang terhubung agar pengalaman menggunakan kalender tetap konsisten.
              </p>
            </div>
          </div>
          <div class="flex flex-col items-end gap-2 text-right text-sm text-emerald-600/80">
            <span class="font-medium text-emerald-600">{{ user?.email }}</span>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-600 shadow-sm transition hover:bg-emerald-50"
              @click="summaryOpen = true"
            >
              Lihat ringkasan
            </button>

            <Link
              :href="route('calendar.index')"
              class="inline-flex items-center gap-2 rounded-xl border border-emerald-300/60 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-400 px-4 py-2 text-xs font-semibold text-slate-900 shadow-lg shadow-emerald-500/30 transition hover:from-emerald-300 hover:via-emerald-400 hover:to-teal-300"
            >
              Kembali ke kalender
            </Link>
          </div>
        </div>
      </div>
    </template>

    <section class="mx-auto max-w-5xl space-y-8 py-6">
      <UpdateProfileInformationForm
        v-if="$page.props.jetstream.canUpdateProfileInformation"
        :user="$page.props.auth.user"
        class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg"
      />

      <LinkedAccountsForm
        v-if="Object.keys(availableOauthProviders).length"
        :available-providers="availableOauthProviders"
        :active-providers="activeOauthProviders"
        class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg"
      />

      <UpdatePasswordForm
        v-if="$page.props.jetstream.canUpdatePassword"
        class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg"
      />

      <TwoFactorAuthenticationForm
        v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
        :requires-confirmation="confirmsTwoFactorAuthentication"
        class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg"
      />

      <LogoutOtherBrowserSessionsForm
        v-if="sessions.length > 0"
        :sessions="sessions"
        class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg"
      />

      <DeleteUserForm
        v-if="$page.props.jetstream.hasAccountDeletionFeatures"
        class="rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-600 shadow-lg"
      />
    </section>
    <ProfileQuickModal v-if="summaryOpen" :user="user" @close="summaryOpen = false" />

  </AppLayout>
</template>

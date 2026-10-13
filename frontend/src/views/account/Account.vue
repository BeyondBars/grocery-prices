<template>
  <PageHeader :title="loggedInUser.name">
    <template v-if="loggedInUser.last_login_at" #description>
      Last login {{ $date(loggedInUser.last_login_at, { format: 'DD/MM/YYYY [at] h:mmA' }) }}
      from {{ loggedInUser.last_login_ip }}
    </template>
  </PageHeader>

  <div class="mb-6 flex gap-1 rounded-lg bg-slate-200/60 p-1 sm:inline-flex">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition sm:flex-none"
      :class="activeTab === tab.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
      @click="setActive(tab.id)"
    >
      {{ tab.label }}
    </button>
  </div>

  <div v-if="activeTab === 1" class="card max-w-xl p-6">
    <h2 class="mb-4 text-base font-semibold text-slate-900">Account details</h2>
    <form novalidate @submit="onSubmit">
      <div class="grid gap-x-4 sm:grid-cols-2">
        <div class="mb-4">
          <label class="form-label" for="first_name">First name</label>
          <input id="first_name" v-model="first_name" class="form-input" type="text" />
        </div>
        <div class="mb-4">
          <label class="form-label" for="last_name">Last name</label>
          <input id="last_name" v-model="last_name" class="form-input" type="text" />
        </div>
      </div>

      <HTBPhoneInput name="mobile" label="Mobile" type="text" />

      <div class="mb-4">
        <label class="form-label" for="email">Email</label>
        <input id="email" v-model="email" class="form-input" type="email" disabled />
      </div>

      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">Update details</button>
      </div>
    </form>
  </div>

  <div v-if="activeTab === 2" class="grid gap-6 xl:grid-cols-2">
    <div class="card p-6">
      <h2 class="mb-4 text-base font-semibold text-slate-900">Change password</h2>
      <form novalidate @submit="onPasswordSubmit">
        <HTBFormField name="current_password" label="Current password" type="password" />
        <HTBFormField name="password" label="New password" type="password" />
        <HTBFormField name="repeat_password" label="Repeat new password" type="password" />
        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary">Change password</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <h2 class="mb-4 text-base font-semibold text-slate-900">2-step verification</h2>
      <form novalidate @submit="onAuthSubmit">
        <label class="mb-4 flex items-center gap-2 text-sm font-medium text-slate-700">
          <input v-model="two_factor_required" name="two_factor_required" class="form-check" type="checkbox" />
          Use 2-step verification when signing in
        </label>

        <fieldset v-if="two_factor_required" class="mb-4">
          <legend class="form-label">Send my codes by</legend>
          <div class="flex gap-6">
            <label v-for="option in options" :key="option.value" class="flex items-center gap-2 text-sm text-slate-700">
              <input v-model="two_factor_method_preferred" class="form-check" type="radio" :value="option.value" />
              {{ option.label }}
            </label>
          </div>
        </fieldset>

        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</template>
<script setup lang="ts">
import PageHeader from '@/components/app/PageHeader.vue'
import { ref, onMounted } from 'vue'
import { isAxiosError } from 'axios'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import { useUserStore } from '@/stores/users'
import { useAppStore } from '@/stores/app'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { http } from '@/utils/http'
import type { TwoFactorMethod } from '@/types/user'
import HTBFormField from '@/components/HTBFormField.vue'
import HTBPhoneInput from '@/components/HTBPhoneInput.vue'

type AccountForm = {
  first_name: string
  last_name: string
  email: string
  mobile: string
  current_password: string
  password: string
  repeat_password: string
  two_factor_required: boolean
  two_factor_method_preferred: TwoFactorMethod
}

const usersStore = useUserStore()
const appStore = useAppStore()
const { loggedInUser } = useLoggedInUser()
const router = useRouter()
const toast = useToast()
const activeTab = ref(1)

// One form backs all three panels (the HTB inputs bind to the nearest form); each submit handler
// only sends the fields of its own panel.
const { handleSubmit, setErrors, setFieldValue } = useForm<AccountForm>({
  validationSchema: yup.object({
    first_name: yup.string(),
    last_name: yup.string(),
    email: yup.string(),
    mobile: yup.string().nullable(),
    current_password: yup.string(),
    password: yup.string(),
    repeat_password: yup.string(),
    two_factor_required: yup.boolean(),
    two_factor_method_preferred: yup.string(),
  }),
  initialValues: {
    first_name: loggedInUser.value.first_name ?? '',
    last_name: loggedInUser.value.last_name ?? '',
    email: loggedInUser.value.email,
    mobile: loggedInUser.value.mobile ?? '',
    current_password: '',
    password: '',
    repeat_password: '',
    two_factor_required: loggedInUser.value.two_factor_required ?? false,
    two_factor_method_preferred: loggedInUser.value.two_factor_method_preferred ?? 'email',
  },
})

const { value: first_name } = useField<string>('first_name')
const { value: last_name } = useField<string>('last_name')
const { value: email } = useField<string>('email')
const { value: two_factor_required } = useField<boolean>('two_factor_required')
const { value: two_factor_method_preferred } = useField<TwoFactorMethod>('two_factor_method_preferred')

const loadUser = async () => {
  await usersStore.fetchUser(loggedInUser.value.id)
  const user = usersStore.user

  // Ensure user data is available before setting values
  if (user) {
    setFieldValue('first_name', user.first_name ?? '')
    setFieldValue('last_name', user.last_name ?? '')
    setFieldValue('email', user.email)
    setFieldValue('mobile', user.mobile ?? '')
    setFieldValue('two_factor_required', user.two_factor_required ?? false)
    setFieldValue('two_factor_method_preferred', user.two_factor_method_preferred ?? 'email')
  }
}

onMounted(loadUser)

const handleError = (error: unknown) => {
  if (isAxiosError(error) && error.response?.data?.errors) {
    setErrors(error.response.data.errors)
    return
  }

  throw error
}

const onSubmit = handleSubmit(async (payload) => {
  try {
    await usersStore.updateUser(loggedInUser.value.id, {
      first_name: payload.first_name,
      last_name: payload.last_name,
      mobile: payload.mobile || null,
    })
    // Keep the session user (header, sidebar, guards) in sync.
    await appStore.getUser()
    toast.success('Account details updated successfully!')
  } catch (error) {
    handleError(error)
  }
})

const onPasswordSubmit = handleSubmit(async (payload) => {
  try {
    await http.put(`users/${loggedInUser.value.id}/password`, {
      current_password: payload.current_password,
      password: payload.password,
      password_confirmation: payload.repeat_password,
    })

    toast.success('Password updated successfully.')
    setFieldValue('current_password', '')
    setFieldValue('password', '')
    setFieldValue('repeat_password', '')
  } catch (error) {
    handleError(error)
  }
})

const onAuthSubmit = handleSubmit(async (payload) => {
  try {
    const user = await appStore.updateTwoFactor({
      two_factor_required: payload.two_factor_required,
      two_factor_method_preferred: payload.two_factor_required ? payload.two_factor_method_preferred : null,
    })

    // A newly chosen method only becomes enabled once a code sent with it has been verified.
    if (user.two_factor_required && user.two_factor_method_preferred !== user.two_factor_method_enabled) {
      router.replace({ name: `two-factor.setup.${user.two_factor_method_preferred}`, query: { change: '1' } })
      return
    }

    toast.success('Two factor updated successfully.')
  } catch (error) {
    handleError(error)
  }
})

const setActive = (tab: number) => {
  activeTab.value = tab
}

const tabs = [
  { id: 1, label: 'Account details' },
  { id: 2, label: 'Security' },
]

const options: { value: TwoFactorMethod; label: string }[] = [
  { value: 'sms', label: 'SMS' },
  { value: 'email', label: 'Email' },
]
</script>

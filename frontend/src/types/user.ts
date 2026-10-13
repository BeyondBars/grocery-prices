import type { RoleResource } from './role'

export type UserStatus = 'enabled' | 'pending' | 'disabled'
export type TwoFactorMethod = 'email' | 'sms'

export type AbilityResource = {
  id: number
  name: string
  title: string | null
  group: string | null
}

/** Shape of App\Http\Resources\UserResource. */
export type UserResource = {
  id: number
  name: string
  first_name: string | null
  last_name: string | null
  email: string
  new_email: string | null
  mobile: string | null
  status: UserStatus
  role?: RoleResource | null
  account_type?: string | null
  provider: string | null
  email_verified: boolean
  terms_accepted: boolean
  last_login_at: string | null
  last_login_ip: string | null
  two_factor_required: boolean | null
  two_factor_method_enabled: TwoFactorMethod | null
  two_factor_method_preferred: TwoFactorMethod | null
  two_factor_verified: boolean | null
  abilities?: AbilityResource[]
}

/** Shape of App\Http\Resources\UserListResource. */
export type UserListResource = {
  id: number
  name: string
  email: string
  mobile: string | null
  role?: string | null
  status: UserStatus
  last_login_at: string | null
}

/** Laravel paginated resource collection. */
export type Paginated<T> = {
  data: T[]
  links: Record<string, string | null>
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

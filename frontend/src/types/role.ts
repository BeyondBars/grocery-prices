import type { AbilityResource } from './user'

/** Shape of App\Http\Resources\RoleResource. */
export type RoleResource = {
  id: number
  name: string
  title: string | null
  description: string | null
  abilities?: AbilityResource[]
}

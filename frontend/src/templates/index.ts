import type { Component } from 'vue'
import AppTemplate from './AppTemplate.vue'
import AuthTemplate from './AuthTemplate.vue'
import EmptyTemplate from './EmptyTemplate.vue'
import ErrorTemplate from './ErrorTemplate.vue'

export type TemplateKeys = 'app' | 'auth' | 'empty' | 'error'

export const templates: { [key in TemplateKeys]: Component } = {
  app: AppTemplate,
  auth: AuthTemplate,
  empty: EmptyTemplate,
  error: ErrorTemplate,
}

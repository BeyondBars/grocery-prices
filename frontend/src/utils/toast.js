import VueToast from 'vue-toast-notification'
import 'vue-toast-notification/dist/theme-sugar.css'

export default {
  install(app) {
    // Register the VueToast plugin
    app.use(VueToast)

    // Attach toast functions to global properties
    app.config.globalProperties.$toast = {
      success(message) {
        app.config.globalProperties.$toastInstance.success(message)
      },
      error(message) {
        app.config.globalProperties.$toastInstance.error(message)
      },
      info(message) {
        app.config.globalProperties.$toastInstance.info(message)
      },
      warning(message) {
        app.config.globalProperties.$toastInstance.warning(message)
      },
      show(message, options = {}) {
        app.config.globalProperties.$toastInstance.open({ ...options, message })
      },
      open(options) {
        app.config.globalProperties.$toastInstance.open(options)
      }
    }

    // Create a reusable toast instance
    app.config.globalProperties.$toastInstance = app.$toast
  }
}

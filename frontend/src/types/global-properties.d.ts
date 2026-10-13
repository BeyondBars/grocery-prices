/** Global helpers registered in main.js. */
declare module 'vue' {
  interface ComponentCustomProperties {
    /** Formats a date with moment (default format "DD/MM/YYYY [at] h:mm a"). */
    $date: (date: string | Date | null | undefined, options?: { format: string }) => string
  }
}

export {}

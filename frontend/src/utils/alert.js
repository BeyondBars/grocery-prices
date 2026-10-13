import Swal from 'sweetalert2'

/**
 * Confirmation dialog. Resolves to true when confirmed, false otherwise.
 *
 * @param {import('sweetalert2').SweetAlertOptions} options
 * @returns {Promise<boolean>}
 */
export default async (options = {}) => {
  const result = await Swal.fire({
    showCancelButton: true,
    confirmButtonText: 'Yes',
    cancelButtonText: 'Cancel',
    buttonsStyling: false,
    ...options,
    customClass: {
      confirmButton: 'btn btn-danger ml-2',
      cancelButton: 'btn btn-secondary',
      ...options.customClass,
    },
  })

  return result.isConfirmed
}

import { ref, computed } from 'vue';

export function useValidation() {
  const failed = ref(false);

  const validateRequired = (value, allowFalse = true) => {
    if (!allowFalse && !value) {
      failed.value = true;
      return false;
    }
    failed.value = false;
    return true;
  };

  return {
    failed,
    validateRequired,
  };
}

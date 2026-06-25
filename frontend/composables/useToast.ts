export type ToastType = 'success' | 'error' | 'info' | 'warning'

export type ToastItem = {
  id: number
  message: string
  type: ToastType
}

const toasts = ref<ToastItem[]>([])
let toastId = 0

export function useToast() {
  function showToast(message: string, type: ToastType = 'info') {
    toasts.value.push({
      id: ++toastId,
      message,
      type,
    })
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  return {
    toasts,
    showToast,
    removeToast,
  }
}

import type { Patient, Staff } from '~/types/booking'

type AuthUser = Patient | Staff
type UserType = 'patient' | 'staff'

const token = ref('')
const userType = ref<UserType | null>(null)
const user = ref<AuthUser | null>(null)

export function useAuth() {
  function loadSession() {
    if (!import.meta.client) return

    const savedType = localStorage.getItem('auth_user_type') as UserType | null
    const savedToken = localStorage.getItem('auth_token') || ''
    const savedUser = localStorage.getItem('auth_user')

    token.value = savedToken
    userType.value = savedType
    user.value = savedUser ? JSON.parse(savedUser) : null
  }

  function setSession(nextToken: string, nextUserType: UserType, nextUser: AuthUser) {
    token.value = nextToken
    userType.value = nextUserType
    user.value = nextUser

    if (!import.meta.client) return

    localStorage.setItem('auth_token', nextToken)
    localStorage.setItem('auth_user_type', nextUserType)
    localStorage.setItem('auth_user', JSON.stringify(nextUser))
  }

  function clearSession() {
    token.value = ''
    userType.value = null
    user.value = null

    if (!import.meta.client) return

    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user_type')
    localStorage.removeItem('auth_user')
    localStorage.removeItem('portal_last_appointment')
  }

  return {
    token,
    userType,
    user,
    loadSession,
    setSession,
    clearSession,
  }
}

import { defineStore } from 'pinia'
import client from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('simlab_token') || null,
    user: JSON.parse(localStorage.getItem('simlab_user') || 'null'),
  }),

  getters: {
    isLoggedIn: (state) => Boolean(state.token && state.user),
    isPetugas: (state) => state.user?.role === 'petugas_lapangan',
    isPengawas: (state) => state.user?.role === 'pengawas_wilayah',
    isDinas: (state) => state.user?.role === 'dinas_provinsi',
  },

  actions: {
    async login(email, password) {
      const { data } = await client.post('/login', { email, password })
      this.token = data.token
      this.user = data.user
      localStorage.setItem('simlab_token', data.token)
      localStorage.setItem('simlab_user', JSON.stringify(data.user))
    },

    async logout() {
      try {
        await client.post('/logout')
      } catch {
        // tetap logout lokal walau request gagal
      }
      this.clear()
    },

    clear() {
      this.token = null
      this.user = null
      localStorage.removeItem('simlab_token')
      localStorage.removeItem('simlab_user')
    },
  },
})

<template>
  <div id="app" class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <router-link to="/" class="text-xl font-bold text-gray-900">
              PDF Parser
            </router-link>
          </div>

          <div class="flex items-center space-x-4">
            <template v-if="authStore.isAuthenticated">
              <router-link
                to="/dashboard"
                class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
              >
                Dashboard
              </router-link>
              <router-link
                to="/uploads"
                class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
              >
                Uploads
              </router-link>
              <router-link
                to="/quota"
                class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
              >
                Storage
              </router-link>
              <button
                @click="logout"
                class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700"
              >
                Logout
              </button>
            </template>

            <template v-else>
              <router-link
                to="/login"
                class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
              >
                Login
              </router-link>
              <router-link
                to="/register"
                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700"
              >
                Register
              </router-link>
            </template>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <router-view />
    </main>

    <!-- Loading overlay -->
    <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
        <p class="mt-2 text-gray-600">Loading...</p>
      </div>
    </div>

    <!-- Toast notifications -->
    <div v-if="notification" class="fixed top-4 right-4 z-50">
      <div
        :class="[
          'rounded-md p-4 shadow-lg',
          notification.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
        ]"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg v-if="notification.type === 'success'" class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium">{{ notification.message }}</p>
          </div>
          <div class="ml-auto pl-3">
            <button
              @click="clearNotification"
              class="inline-flex text-gray-400 hover:text-gray-600"
            >
              <span class="sr-only">Close</span>
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

export default {
  name: 'App',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()
    const loading = ref(false)
    const notification = ref(null)

    const logout = async () => {
      try {
        loading.value = true
        await authStore.logout()
        router.push('/login')
        showNotification('Logged out successfully', 'success')
      } catch (error) {
        showNotification('Logout failed', 'error')
      } finally {
        loading.value = false
      }
    }

    const showNotification = (message, type = 'success') => {
      notification.value = { message, type }
      setTimeout(() => {
        notification.value = null
      }, 5000)
    }

    const clearNotification = () => {
      notification.value = null
    }

    onMounted(() => {
      // Initialize auth state if token exists
      if (localStorage.getItem('token')) {
        authStore.initializeAuth()
      }
    })

    return {
      authStore,
      loading,
      notification,
      logout,
      showNotification,
      clearNotification
    }
  }
}
</script>

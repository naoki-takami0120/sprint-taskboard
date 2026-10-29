<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProjectCreateForm from '@/components/ProjectCreateForm.vue'
import { listProjects } from '@/api/projects'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const projects = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
let requestId = 0

const page = computed(() => Math.max(1, Number.parseInt(route.query.page, 10) || 1))

async function load() {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const result = await listProjects(auth.token, { page: page.value })
    if (id !== requestId) return

    projects.value = result.data
    meta.value = result.meta
  } catch (e) {
    if (id !== requestId) return
    error.value = e.message
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch(page, load, { immediate: true })

function goTo(n) {
  router.push({ query: { page: n } })
}

function onCreated() {
  if (page.value === 1) load()
  else goTo(1)
}
</script>

<template>
  <main class="projects">
    <h1>Projects</h1>

    <ProjectCreateForm @created="onCreated" />

    <p v-if="loading">Loading...</p>

    <div v-else-if="error" role="alert">
      <p class="error">{{ error }}</p>
      <button type="button" @click="load">Retry</button>
    </div>

    <p v-else-if="projects.length === 0">No projects yet.</p>

    <ul v-else>
      <li v-for="project in projects" :key="project.id">
        <strong>{{ project.title }}</strong>
        <span v-if="project.description"> - {{ project.description }}</span>
      </li>
    </ul>

    <nav v-if="meta && meta.last_page > 1" class="pager">
      <button type="button" :disabled="meta.current_page <= 1" @click="goTo(meta.current_page - 1)">
        Previous
      </button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        type="button"
        :disabled="meta.current_page >= meta.last_page"
        @click="goTo(meta.current_page + 1)"
      >
        Next
      </button>
    </nav>
  </main>
</template>

<style scoped>
.projects {
  max-width: 640px;
  margin: 2rem auto;
}
li {
  margin: 0.5rem 0;
}
.pager {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-top: 1.5rem;
}
.error {
  color: #c0392b;
}
</style>

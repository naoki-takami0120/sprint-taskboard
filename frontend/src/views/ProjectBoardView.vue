<script setup>
import { ref, watch } from 'vue'
import BoardColumn from '@/components/BoardColumn.vue'
import { getProject, listMembers } from '@/api/projects'
import { listTasks } from '@/api/tasks'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  id: { type: String, required: true },
})

const auth = useAuthStore()

const STATUSES = [
  { key: 'todo', label: 'To do' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'done', label: 'Done' },
]

function emptyColumns() {
  return Object.fromEntries(STATUSES.map((s) => [s.key, { tasks: [], total: 0 }]))
}

const project = ref(null)
const members = ref({})
const columns = ref(emptyColumns())
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
let requestId = 0

async function load() {
  const current = ++requestId
  loading.value = true
  notFound.value = false
  error.value = ''

  try {
    const [projectResult, membersResult, ...taskResults] = await Promise.all([
      getProject(auth.token, props.id),
      listMembers(auth.token, props.id),
      ...STATUSES.map((s) => listTasks(auth.token, props.id, { status: s.key, perPage: 100 })),
    ])
    if (current !== requestId) return

    project.value = projectResult.data
    members.value = Object.fromEntries(membersResult.data.map((u) => [u.id, u.name]))
    columns.value = Object.fromEntries(
      STATUSES.map((s, i) => [
        s.key,
        { tasks: taskResults[i].data, total: taskResults[i].meta.total },
      ]),
    )
  } catch (e) {
    if (current !== requestId) return
    if (e.status === 404) notFound.value = true
    else error.value = e.message
  } finally {
    if (current === requestId) loading.value = false
  }
}

watch(() => props.id, load, { immediate: true })
</script>

<template>
  <main class="board">
    <RouterLink :to="{ name: 'projects' }">&larr; Projects</RouterLink>

    <p v-if="loading">Loading...</p>

    <div v-else-if="notFound" role="alert">
      <h1>Project not found</h1>
      <p>It may not exist, or you may not be a member of it.</p>
    </div>

    <div v-else-if="error" role="alert">
      <p class="error">{{ error }}</p>
      <button type="button" @click="load">Retry</button>
    </div>

    <template v-else-if="project">
      <h1>{{ project.title }}</h1>
      <p v-if="project.description">{{ project.description }}</p>

      <div class="columns">
        <BoardColumn
          v-for="status in STATUSES"
          :key="status.key"
          :title="status.label"
          :tasks="columns[status.key].tasks"
          :total="columns[status.key].total"
          :members="members"
        />
      </div>
    </template>
  </main>
</template>

<style scoped>
.board {
  max-width: 1000px;
  margin: 2rem auto;
}
.columns {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  margin-top: 1.5rem;
}
.error {
  color: #c0392b;
}
</style>

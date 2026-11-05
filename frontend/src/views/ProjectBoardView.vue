<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BoardColumn from '@/components/BoardColumn.vue'
import TaskForm from '@/components/TaskForm.vue'
import BurndownPanel from '@/components/BurndownPanel.vue'
import RankingPanel from '@/components/RankingPanel.vue'
import { getBurndown, getRanking, listSprints } from '@/api/insights'
import { getProject, listMembers } from '@/api/projects'
import { changeTaskStatus, deleteTask, listTasks } from '@/api/tasks'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  id: { type: String, required: true },
})

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const STATUSES = [
  { key: 'todo', label: 'To do' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'done', label: 'Done' },
]

function emptyColumns() {
  return Object.fromEntries(STATUSES.map((s) => [s.key, { tasks: [], total: 0 }]))
}

function setAssignee(value) {
  router.push(value ? { query: { assignee: value } } : { query: {} })
}

const project = ref(null)
const memberList = ref([])
const columns = ref(emptyColumns())
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
const editing = ref(null) // null, 'new', or the task being edited
const busy = ref(false)
const actionError = ref('')

const ranking = ref(null)
const sprints = ref([])
const sprintId = ref(null)
const burndown = ref(null)
const insightsError = ref('')
let insightsRequestId = 0

const assigneeId = computed(() => {
  const n = Number.parseInt(route.query.assignee, 10)
  return Number.isInteger(n) && n > 0 ? n : null
})

let requestId = 0

async function load({ silent = false } = {}) {
  const current = ++requestId
  if (!silent) loading.value = true
  notFound.value = false
  error.value = ''

  try {
    const [projectResult, membersResult, ...taskResults] = await Promise.all([
      getProject(auth.token, props.id),
      listMembers(auth.token, props.id),
      ...STATUSES.map((s) =>
        listTasks(auth.token, props.id, {
          status: s.key,
          assigneeId: assigneeId.value,
          perPage: 100,
        }),
      ),
    ])
    if (current !== requestId) return

    project.value = projectResult.data
    memberList.value = membersResult.data
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

async function loadInsights() {
  const current = ++insightsRequestId
  insightsError.value = ''

  try {
    const [rankingResult, sprintsResult] = await Promise.all([
      getRanking(auth.token, props.id),
      listSprints(auth.token, props.id),
    ])
    if (current !== insightsRequestId) return

    ranking.value = rankingResult.data
    sprints.value = sprintsResult.data

    if (!sprints.value.some((s) => s.id === sprintId.value)) {
      sprintId.value = sprints.value[0]?.id ?? null
    }

    const burndownResult = sprintId.value ? await getBurndown(auth.token, sprintId.value) : null
    if (current !== insightsRequestId) return

    burndown.value = burndownResult ? burndownResult.data : null
  } catch (e) {
    if (current !== insightsRequestId) return
    insightsError.value = e.message
  }
}

async function selectSprint(id) {
  sprintId.value = id

  try {
    burndown.value = (await getBurndown(auth.token, id)).data
  } catch (e) {
    insightsError.value = e.message
  }
}

function reloadAll() {
  return Promise.all([load({ silent: true }), loadInsights()])
}

watch(
  () => props.id,
  () => {
    editing.value = null
    ranking.value = null
    sprints.value = []
    sprintId.value = null
    burndown.value = null
    load()
    loadInsights()
  },
  { immediate: true },
)
watch(assigneeId, () => load({ silent: true }))

async function run(action) {
  busy.value = true
  actionError.value = ''

  try {
    await action()
  } catch (e) {
    actionError.value = e.message
  } finally {
    busy.value = false
  }

  await reloadAll()
}

function moveTask(task, status) {
  return run(() => changeTaskStatus(auth.token, task.id, status))
}

function removeTask(task) {
  if (!window.confirm(`Delete "${task.title}"?`)) return
  return run(() => deleteTask(auth.token, task.id))
}

async function onSaved() {
  editing.value = null
  await reloadAll()
}
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
      <button type="button" @click="load()">Retry</button>
    </div>

    <template v-else-if="project">
      <div class="heading">
        <h1>{{ project.title }}</h1>
        <button type="button" :disabled="busy" @click="editing = 'new'">New task</button>
      </div>
      <p v-if="project.description">{{ project.description }}</p>

      <p v-if="actionError" class="error" role="alert">{{ actionError }}</p>

      <p v-if="insightsError" class="error" role="alert">{{ insightsError }}</p>

      <div class="insights">
        <RankingPanel :ranking="ranking" />
        <BurndownPanel
          :sprints="sprints"
          :sprint-id="sprintId"
          :burndown="burndown"
          @select="selectSprint"
        />
      </div>

      <div class="filters">
        <label>
          Assignee
          <select :value="assigneeId ?? ''" @change="setAssignee($event.target.value)">
            <option value="">All members</option>
            <option v-for="member in memberList" :key="member.id" :value="member.id">
              {{ member.name }}
            </option>
          </select>
        </label>
      </div>

      <TaskForm
        v-if="editing"
        :key="editing === 'new' ? 'new' : editing.id"
        :project-id="id"
        :members="memberList"
        :task="editing === 'new' ? null : editing"
        @saved="onSaved"
        @cancel="editing = null"
      />

      <div class="columns">
        <BoardColumn
          v-for="status in STATUSES"
          :key="status.key"
          :title="status.label"
          :tasks="columns[status.key].tasks"
          :total="columns[status.key].total"
          :busy="busy"
          @move="moveTask"
          @edit="(task) => (editing = task)"
          @remove="removeTask"
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
.heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
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
.filters {
  margin-top: 1rem;
}
select {
  margin-left: 0.5rem;
  padding: 0.25rem;
}
.insights {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 1rem;
  margin-top: 1rem;
}
</style>

<script setup>
import { reactive, ref } from 'vue'
import { createTask, updateTask } from '@/api/tasks'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  projectId: { type: String, required: true },
  members: { type: Array, required: true },
  task: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'cancel'])

const auth = useAuthStore()
const isEdit = props.task !== null

const form = reactive({
  title: props.task?.title ?? '',
  description: props.task?.description ?? '',
  assignee_id: props.task?.assignee_id ?? null,
  due_date: props.task?.due_date ?? '',
  points: props.task?.points ?? 1,
})

const errors = ref({})
const message = ref('')
const loading = ref(false)

function payload() {
  return {
    title: form.title,
    description: form.description || null,
    assignee_id: form.assignee_id,
    due_date: form.due_date || null,
    points: form.points === '' ? undefined : form.points,
  }
}

async function submit() {
  loading.value = true
  errors.value = {}
  message.value = ''

  try {
    if (isEdit) {
      await updateTask(auth.token, props.task.id, payload())
    } else {
      await createTask(auth.token, props.projectId, payload())
    }
    emit('saved')
  } catch (error) {
    if (error.status === 422) {
      errors.value = error.body.errors
    } else {
      message.value = error.message
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="task-form" @submit.prevent="submit">
    <h2>{{ isEdit ? 'Edit task' : 'New task' }}</h2>

    <label>
      Title
      <input v-model="form.title" type="text" maxlength="255" />
    </label>
    <p v-if="errors.title" class="error">{{ errors.title[0] }}</p>

    <label>
      Description
      <textarea v-model="form.description" rows="3"></textarea>
    </label>
    <p v-if="errors.description" class="error">{{ errors.description[0] }}</p>

    <label>
      Assignee
      <select v-model="form.assignee_id">
        <option :value="null">Unassigned</option>
        <option v-for="member in members" :key="member.id" :value="member.id">
          {{ member.name }}
        </option>
      </select>
    </label>
    <p v-if="errors.assignee_id" class="error">{{ errors.assignee_id[0] }}</p>

    <label>
      Due date
      <input v-model="form.due_date" type="date" />
    </label>
    <p v-if="errors.due_date" class="error">{{ errors.due_date[0] }}</p>

    <label>
      Points
      <input v-model.number="form.points" type="number" min="1" max="100" />
    </label>
    <p v-if="errors.points" class="error">{{ errors.points[0] }}</p>

    <p v-if="message" class="error" role="alert">{{ message }}</p>

    <div class="buttons">
      <button type="submit" :disabled="loading">
        {{ loading ? 'Saving...' : 'Save' }}
      </button>
      <button type="button" :disabled="loading" @click="emit('cancel')">Cancel</button>
    </div>
  </form>
</template>

<style scoped>
.task-form {
  margin: 1.5rem 0;
  padding: 1rem;
  border: 1px solid #ddd;
}
label {
  display: block;
  margin-top: 0.75rem;
}
input,
textarea,
select {
  display: block;
  width: 100%;
  padding: 0.5rem;
}
.buttons {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}
.error {
  color: #c0392b;
  margin: 0.25rem 0 0;
}
</style>

<script setup>
import { reactive, ref } from 'vue'
import { createProject } from '@/api/projects'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['created'])

const auth = useAuthStore()
const form = reactive({ title: '', description: '' })
const errors = ref({})
const message = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  message.value = ''

  try {
    await createProject(auth.token, { title: form.title, description: form.description })
    form.title = ''
    form.description = ''
    emit('created')
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
  <form class="create" @submit.prevent="submit">
    <h2>New project</h2>

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

    <p v-if="message" class="error" role="alert">{{ message }}</p>

    <button type="submit" :disabled="loading">
      {{ loading ? 'Creating...' : 'Create' }}
    </button>
  </form>
</template>

<style scoped>
.create {
  margin-bottom: 2rem;
  padding: 1rem;
  border: 1px solid #ddd;
}
label {
  display: block;
  margin-top: 0.75rem;
}
input,
textarea {
  display: block;
  width: 100%;
  padding: 0.5rem;
}
button {
  margin-top: 1rem;
}
.error {
  color: #c0392b;
  margin: 0.25rem 0 0;
}
</style>

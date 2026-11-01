<script setup>
import { STATUS_LABELS, TRANSITIONS } from '@/constants/taskStatus'

defineProps({
  task: { type: Object, required: true },
  assigneeName: { type: String, default: '' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['move', 'edit', 'remove'])
</script>

<template>
  <article class="card">
    <h3>{{ task.title }}</h3>
    <p class="meta">
      <span>{{ task.points }} pt</span>
      <span v-if="task.due_date">Due {{ task.due_date }}</span>
    </p>
    <p class="meta">{{ assigneeName || 'Unassigned' }}</p>

    <div class="actions">
      <button
        v-for="next in TRANSITIONS[task.status]"
        :key="next"
        type="button"
        :disabled="busy"
        @click="emit('move', task, next)"
      >
        &rarr; {{ STATUS_LABELS[next] }}
      </button>
      <button type="button" :disabled="busy" @click="emit('edit', task)">Edit</button>
      <button type="button" :disabled="busy" @click="emit('remove', task)">Delete</button>
    </div>
  </article>
</template>

<style scoped>
.card {
  margin-bottom: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 4px;
}
h3 {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
}
.meta {
  display: flex;
  gap: 0.75rem;
  margin: 0;
  color: #666;
  font-size: 0.85rem;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-top: 0.5rem;
}
</style>

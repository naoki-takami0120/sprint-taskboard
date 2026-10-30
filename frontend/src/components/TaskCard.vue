<script setup>
defineProps({
  task: { type: Object, required: true },
  assigneeName: { type: String, default: '' },
})
</script>

<template>
  <article class="card">
    <h3>{{ task.title }}</h3>
    <p class="meta">
      <span>{{ task.points }} pt</span>
      <span v-if="task.due_date">Due {{ task.due_date }}</span>
    </p>
    <p class="meta">{{ assigneeName || 'Unassigned' }}</p>
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
</style>

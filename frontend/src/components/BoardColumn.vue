<script setup>
import TaskCard from '@/components/TaskCard.vue'

defineProps({
  title: { type: String, required: true },
  tasks: { type: Array, required: true },
  total: { type: Number, required: true },
  members: { type: Object, required: true },
})
</script>

<template>
  <section class="column">
    <h2>
      {{ title }} <span class="count">({{ total }})</span>
    </h2>

    <p v-if="tasks.length === 0" class="empty">No tasks</p>

    <TaskCard
      v-for="task in tasks"
      :key="task.id"
      :task="task"
      :assignee-name="members[task.assignee_id] ?? ''"
    />

    <p v-if="total > tasks.length" class="empty">Showing {{ tasks.length }} of {{ total }}</p>
  </section>
</template>

<style scoped>
.column {
  padding: 0.75rem;
  background: #f5f5f5;
  border-radius: 6px;
}
h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}
.count,
.empty {
  color: #777;
}
</style>

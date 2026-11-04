<script setup>
const props = defineProps({
  sprints: { type: Array, required: true },
  sprintId: { type: Number, default: null },
  burndown: { type: Object, default: null },
})

const emit = defineEmits(['select'])

function barWidth(remaining) {
  const total = props.burndown?.total_points ?? 0
  if (remaining === null || total === 0) return '0%'
  return `${Math.round((remaining / total) * 100)}%`
}
</script>

<template>
  <section class="panel">
    <h2>Burndown</h2>

    <p v-if="sprints.length === 0" class="muted">No sprints yet.</p>

    <template v-else>
      <label>
        Sprint
        <select :value="sprintId ?? ''" @change="emit('select', Number($event.target.value))">
          <option v-for="sprint in sprints" :key="sprint.id" :value="sprint.id">
            {{ sprint.title }} ({{ sprint.start_date }} - {{ sprint.end_date }})
          </option>
        </select>
      </label>

      <p v-if="!burndown" class="muted">Loading...</p>

      <template v-else>
        <p class="muted">Total: {{ burndown.total_points }} pt</p>
        <table>
          <tr v-for="day in burndown.days" :key="day.date">
            <td>{{ day.date }}</td>
            <td class="bar-cell">
              <div class="bar" :style="{ width: barWidth(day.remaining) }"></div>
            </td>
            <td>{{ day.remaining ?? '-' }}</td>
          </tr>
        </table>
      </template>
    </template>
  </section>
</template>

<style scoped>
.panel {
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
}
h2 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
}
select {
  margin-left: 0.5rem;
  padding: 0.25rem;
}
table {
  width: 100%;
  border-collapse: collapse;
}
td {
  padding: 0.15rem 0.5rem 0.15rem 0;
  font-size: 0.85rem;
}
.bar-cell {
  width: 100%;
}
.bar {
  height: 0.6rem;
  background: #4a90d9;
}
.muted {
  color: #777;
}
</style>

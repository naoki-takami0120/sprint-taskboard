<script setup>
defineProps({
  ranking: { type: Object, default: null },
})
</script>

<template>
  <section class="panel">
    <h2>
      Top this week
      <span v-if="ranking" class="muted">({{ ranking.week }})</span>
    </h2>

    <p v-if="!ranking" class="muted">Loading...</p>
    <p v-else-if="ranking.entries.length === 0" class="muted">No completed points yet.</p>

    <ol v-else>
      <li v-for="entry in ranking.entries" :key="entry.user_id">
        {{ entry.name }} <strong>{{ entry.points }} pt</strong>
      </li>
    </ol>
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
.muted {
  color: #777;
}
</style>

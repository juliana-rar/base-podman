<script setup>
import { ref, onMounted } from 'vue'

// URL de la API de Laravel (puerto exposat al host per Docker)
const API_URL = 'http://localhost:8000'

const tasks = ref([])
const error = ref(null)
const loading = ref(true)

async function fetchTasks() {
  loading.value = true
  error.value = null
  try {
    const res = await fetch(`${API_URL}/api/tasks`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    tasks.value = await res.json()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(fetchTasks)
</script>

<template>
  <main>
    <h1>Tasques (Vue ← Laravel ← MySQL)</h1>

    <p v-if="loading">Carregant tasques…</p>

    <p v-else-if="error" class="error">
      Error connectant amb l'API: {{ error }}
    </p>

    <ul v-else class="list">
      <li v-for="task in tasks" :key="task.id" class="item">
        <span class="badge" :class="{ done: task.done }">
          {{ task.done ? '✔ Feta' : '○ Pendent' }}
        </span>
        <span class="title">{{ task.title }}</span>
      </li>
    </ul>

    <button @click="fetchTasks" :disabled="loading">Refrescar</button>
  </main>
</template>

<style scoped>
main {
  font-family: system-ui, sans-serif;
  max-width: 32rem;
  margin: 4rem auto;
  text-align: center;
}
.list {
  list-style: none;
  padding: 0;
  margin: 1.5rem 0;
  text-align: left;
}
.item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: #f4f4f5;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.5rem;
}
.badge {
  font-size: 0.8rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: #e4e4e7;
  white-space: nowrap;
}
.badge.done {
  background: #dcfce7;
  color: #166534;
}
.title {
  flex: 1;
}
.error {
  color: #dc2626;
}
button {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  border: 1px solid #ccc;
  cursor: pointer;
}
</style>

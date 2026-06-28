<template>
  <section>
    <div class="flex items-end justify-between mb-4">
      <h2 class="text-xl font-semibold tracking-tight text-ink">Métricas</h2>
      <span class="text-xs text-ink-soft" aria-live="polite">{{ updatedLabel }}</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <MetricCard
        label="Total de pedidos"
        :value="metrics?.total_orders ?? 0"
        :loading="loading"
      />
      <MetricCard
        label="Receita"
        :value="metrics?.revenue ?? 0"
        money
        :loading="loading"
        hint="approved + refunded"
      />
      <MetricCard
        label="Ticket médio"
        :value="metrics?.average_ticket ?? 0"
        money
        :loading="loading"
      />
      <MetricCard
        label="Taxa de cancelamento"
        :value="metrics?.cancellation_rate ?? 0"
        suffix="%"
        :loading="loading"
      />
    </div>

    <!-- distribuição por status -->
    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div
        v-for="s in statusList"
        :key="s.key"
        class="glass rounded-xl2 shadow-apple px-4 py-3 flex items-center justify-between"
      >
        <span class="text-sm text-ink-soft capitalize">{{ s.label }}</span>
        <span class="text-lg font-semibold text-ink tabular-nums">
          {{ metrics?.orders_by_status?.[s.key] ?? 0 }}
        </span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useOrdersStore } from '../stores/orders'
import MetricCard from './MetricCard.vue'

const store = useOrdersStore()
const { metrics, metricsLoading: loading } = storeToRefs(store)

const lastUpdated = ref(null)
const now = ref(Date.now())
let refreshTimer = null
let tickTimer = null

const statusList = [
  { key: 'pending', label: 'pendentes' },
  { key: 'approved', label: 'aprovados' },
  { key: 'cancelled', label: 'cancelados' },
  { key: 'refunded', label: 'reembolsados' },
]

const updatedLabel = computed(() => {
  if (!lastUpdated.value) return ''
  const diffMs = now.value - lastUpdated.value
  const min = Math.floor(diffMs / 60000)
  if (min < 1) return 'atualizado agora'
  if (min === 1) return 'atualizado há 1 min'
  return `atualizado há ${min} min`
})

async function load() {
  await store.fetchMetrics()
  lastUpdated.value = Date.now()
  now.value = Date.now()
}

onMounted(() => {
  load()
  // refresh automático a cada 60s, sem recarregar a página
  refreshTimer = setInterval(load, 60000)
  // tick a cada 30s só pra atualizar o texto "há X min"
  tickTimer = setInterval(() => { now.value = Date.now() }, 30000)
})

onUnmounted(() => {
  clearInterval(refreshTimer)
  clearInterval(tickTimer)
})
</script>
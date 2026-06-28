<template>
  <div class="min-h-screen">
    <header class="glass sticky top-0 z-30 border-b border-black/5">
      <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 rounded-xl bg-system-blue flex items-center justify-center shadow-apple">
            <span class="text-white text-sm font-semibold">P</span>
          </div>
          <h1 class="text-lg font-semibold tracking-tight text-ink">Pedidos</h1>
        </div>
        <div class="text-sm text-ink-soft">Gestão de pedidos e afiliados</div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">
      <MetricsPanel />

      <section class="space-y-4">
        <h2 class="text-xl font-semibold tracking-tight text-ink">Pedidos</h2>

        <OrdersFilters v-model="filters" @change="onFiltersChange" />

        <OrdersTable
          :orders="orders"
          :meta="meta"
          :loading="loading"
          :sort-by="filters.sort_by"
          :sort-dir="filters.sort_dir"
          @open="openDrawer"
          @page="goToPage"
          @sort="onSort"
          @bulk-cancel="onBulkCancel"
        />
      </section>
    </main>

    <!-- drawer entra no próximo passo -->
    <OrderDrawer
      :order-id="drawerId"
      :open="drawerOpen"
      @close="closeDrawer"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useOrdersStore } from '../stores/orders'
import MetricsPanel from '../components/MetricsPanel.vue'
import OrdersFilters from '../components/OrdersFilters.vue'
import OrdersTable from '../components/OrdersTable.vue'
import OrderDrawer from '../components/OrderDrawer.vue'

const route = useRoute()
const router = useRouter()
const store = useOrdersStore()
const { orders, meta, loading } = storeToRefs(store)

// filtros locais ligados à store
const filters = reactive({ ...store.filters })

// ===== URL como fonte de verdade =====
// lê os filtros da query string ao montar
function readFromUrl() {
  const q = route.query
  filters.affiliate_id = q.affiliate_id ?? ''
  filters.status = q.status ?? ''
  filters.date_from = q.date_from ?? ''
  filters.date_to = q.date_to ?? ''
  filters.min_value = q.min_value ?? ''
  filters.max_value = q.max_value ?? ''
  filters.sort_by = q.sort_by ?? 'ordered_at'
  filters.sort_dir = q.sort_dir ?? 'desc'
  filters.page = Number(q.page ?? 1)
}

// escreve os filtros atuais na URL (sem recarregar)
function writeToUrl() {
  const query = {}
  Object.entries(filters).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined && !(k === 'page' && v === 1)) {
      query[k] = v
    }
  })
  router.replace({ query })
}

async function reload() {
  store.filters = { ...filters }
  await store.fetchOrders()
}

function onFiltersChange() {
  Object.assign(filters, { page: 1 })
  writeToUrl()
  reload()
}

function onSort({ sort_by, sort_dir }) {
  filters.sort_by = sort_by
  filters.sort_dir = sort_dir
  writeToUrl()
  reload()
}

function goToPage(p) {
  filters.page = p
  writeToUrl()
  reload()
}

async function onBulkCancel(ids, done) {
  // cancela em lote: chama o backend para cada pedido selecionado.
  // só pedidos 'pending' podem ir para 'cancelled' (a máquina de estados
  // no backend rejeita os demais com 422, que ignoramos no lote).
  for (const id of ids) {
    try {
      await store.changeStatus(id, 'cancelled')
    } catch (e) {
      // transição inválida para esse pedido, segue o lote
    }
  }
  await reload()
  if (done) done()
}

// ===== drawer =====
const drawerOpen = ref(false)
const drawerId = ref(null)

function openDrawer(id) {
  drawerId.value = id
  drawerOpen.value = true
}
function closeDrawer() {
  drawerOpen.value = false
  drawerId.value = null
}

// reage ao voltar/avançar do navegador
watch(() => route.query, () => {
  readFromUrl()
  reload()
})

onMounted(() => {
  readFromUrl()
  reload()
})
</script>
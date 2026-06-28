<template>
  <div class="space-y-4">
    <!-- barra de ação em lote (aparece quando há seleção) -->
    <div
      v-if="selectedIds.length > 0"
      class="glass rounded-xl2 shadow-apple px-4 py-3 flex items-center justify-between"
    >
      <span class="text-sm text-ink">
        {{ selectedIds.length }} selecionado(s)
      </span>
      <div class="flex items-center gap-2">
        <button class="apple-btn-ghost" @click="clearSelection">Limpar seleção</button>
        <button
          class="apple-btn"
          :disabled="bulkLoading"
          @click="bulkCancel"
        >
          {{ bulkLoading ? 'Cancelando...' : 'Cancelar selecionados' }}
        </button>
      </div>
    </div>

    <!-- estado vazio -->
    <div
      v-if="!loading && orders.length === 0"
      class="glass rounded-2xl2 shadow-apple p-12 text-center"
    >
      <div class="text-ink font-medium">Nenhum pedido encontrado</div>
      <p class="mt-1 text-sm text-ink-soft">
        Tente ajustar ou limpar os filtros aplicados.
      </p>
    </div>

    <!-- tabela (desktop) -->
    <div v-else class="hidden md:block glass rounded-2xl2 shadow-apple overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-black/5 text-left text-ink-soft">
            <th class="px-4 py-3 w-10">
              <input
                type="checkbox"
                :checked="allSelected"
                aria-label="Selecionar todos"
                @change="toggleAll"
              />
            </th>
            <th v-for="col in columns" :key="col.key" class="px-4 py-3 font-medium">
              <button
                v-if="col.sortable"
                class="inline-flex items-center gap-1 hover:text-ink transition"
                @click="toggleSort(col.key)"
              >
                {{ col.label }}
                <span class="text-xs">{{ sortIndicator(col.key) }}</span>
              </button>
              <span v-else>{{ col.label }}</span>
            </th>
            <th class="px-4 py-3 font-medium text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="o in orders"
            :key="o.id"
            class="border-b border-black/5 last:border-0 hover:bg-white/40 transition cursor-pointer"
            @click="$emit('open', o.id)"
          >
            <td class="px-4 py-3" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.includes(o.id)"
                :aria-label="`Selecionar pedido ${o.id}`"
                @change="toggleOne(o.id)"
              />
            </td>
            <td class="px-4 py-3 font-medium text-ink tabular-nums">#{{ o.id }}</td>
            <td class="px-4 py-3 text-ink">{{ o.affiliate?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-ink tabular-nums">{{ money(o.total_value) }}</td>
            <td class="px-4 py-3">
              <StatusBadge :status="o.status" />
            </td>
            <td class="px-4 py-3 text-ink-soft tabular-nums">{{ formatDate(o.ordered_at) }}</td>
            <td class="px-4 py-3 text-right">
              <button class="apple-btn-ghost text-xs" @click.stop="$emit('open', o.id)">
                Ver
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- cards (mobile) -->
    <div v-if="!loading && orders.length > 0" class="md:hidden space-y-3">
      <div
        v-for="o in orders"
        :key="o.id"
        class="glass rounded-2xl2 shadow-apple p-4"
        @click="$emit('open', o.id)"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              :checked="selectedIds.includes(o.id)"
              :aria-label="`Selecionar pedido ${o.id}`"
              @click.stop
              @change="toggleOne(o.id)"
            />
            <span class="font-semibold text-ink tabular-nums">#{{ o.id }}</span>
          </div>
          <StatusBadge :status="o.status" />
        </div>
        <div class="mt-3 text-sm text-ink-soft">{{ o.affiliate?.name ?? '—' }}</div>
        <div class="mt-1 flex items-center justify-between">
          <span class="text-lg font-semibold text-ink tabular-nums">{{ money(o.total_value) }}</span>
          <span class="text-xs text-ink-soft tabular-nums">{{ formatDate(o.ordered_at) }}</span>
        </div>
      </div>
    </div>

    <!-- skeleton de carregamento -->
    <div v-if="loading" class="glass rounded-2xl2 shadow-apple p-6 space-y-3">
      <div v-for="i in 5" :key="i" class="animate-pulse flex gap-4">
        <div class="h-5 w-10 bg-black/10 rounded"></div>
        <div class="h-5 flex-1 bg-black/10 rounded"></div>
        <div class="h-5 w-24 bg-black/10 rounded"></div>
        <div class="h-5 w-20 bg-black/10 rounded"></div>
      </div>
    </div>

    <!-- paginação -->
    <div
      v-if="!loading && orders.length > 0"
      class="flex items-center justify-between text-sm"
    >
      <span class="text-ink-soft">
        Página {{ meta.current_page }} de {{ meta.last_page }} · {{ meta.total }} pedidos
      </span>
      <div class="flex items-center gap-2">
        <button
          class="apple-btn-ghost"
          :disabled="meta.current_page <= 1"
          @click="$emit('page', meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          class="apple-btn-ghost"
          :disabled="meta.current_page >= meta.last_page"
          @click="$emit('page', meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  orders: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
  loading: { type: Boolean, default: false },
  sortBy: { type: String, default: 'ordered_at' },
  sortDir: { type: String, default: 'desc' },
})

const emit = defineEmits(['open', 'page', 'sort', 'bulk-cancel'])

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'affiliate_id', label: 'Afiliado', sortable: true },
  { key: 'total_value', label: 'Valor', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'ordered_at', label: 'Data', sortable: true },
]

const selectedIds = ref([])
const bulkLoading = ref(false)

const allSelected = computed(() =>
  props.orders.length > 0 && selectedIds.value.length === props.orders.length
)

function toggleAll() {
  if (allSelected.value) selectedIds.value = []
  else selectedIds.value = props.orders.map((o) => o.id)
}

function toggleOne(id) {
  const i = selectedIds.value.indexOf(id)
  if (i === -1) selectedIds.value.push(id)
  else selectedIds.value.splice(i, 1)
}

function clearSelection() {
  selectedIds.value = []
}

async function bulkCancel() {
  bulkLoading.value = true
  try {
    emit('bulk-cancel', [...selectedIds.value], () => {
      selectedIds.value = []
    })
  } finally {
    bulkLoading.value = false
  }
}

function toggleSort(key) {
  let dir = 'asc'
  if (props.sortBy === key) {
    dir = props.sortDir === 'asc' ? 'desc' : 'asc'
  }
  emit('sort', { sort_by: key, sort_dir: dir })
}

function sortIndicator(key) {
  if (props.sortBy !== key) return ''
  return props.sortDir === 'asc' ? '↑' : '↓'
}

function money(v) {
  return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-BR')
}
</script>
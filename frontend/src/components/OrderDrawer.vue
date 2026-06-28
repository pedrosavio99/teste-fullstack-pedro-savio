<template>
  <Teleport to="body">
    <transition name="drawer">
      <div v-if="open" class="drawer-root" role="dialog" aria-modal="true" aria-label="Detalhes do pedido">
        <div class="drawer-backdrop" @click="$emit('close')"></div>

        <aside class="drawer-panel">
          <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
            <h3 class="text-lg font-semibold text-ink">
              Pedido <span class="tabular-nums">#{{ orderId }}</span>
            </h3>
            <button class="apple-btn-ghost" aria-label="Fechar painel" @click="$emit('close')">
              Fechar
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
            <div v-if="loading" class="space-y-3">
              <div v-for="i in 4" :key="i" class="animate-pulse h-5 bg-black/10 rounded"></div>
            </div>

            <template v-else-if="order">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <div class="text-xs text-ink-soft">Afiliado</div>
                  <div class="text-sm font-medium text-ink">{{ order.affiliate?.name ?? '—' }}</div>
                </div>
                <div>
                  <div class="text-xs text-ink-soft">Status atual</div>
                  <StatusBadge :status="order.status" />
                </div>
                <div>
                  <div class="text-xs text-ink-soft">Valor total</div>
                  <div class="text-sm font-semibold text-ink tabular-nums">{{ money(order.total_value) }}</div>
                </div>
                <div>
                  <div class="text-xs text-ink-soft">Data</div>
                  <div class="text-sm text-ink tabular-nums">{{ formatDate(order.ordered_at) }}</div>
                </div>
              </div>

              <div class="rounded-xl2 bg-white/60 p-4">
                <div class="text-sm font-medium text-ink mb-2">Mudar status</div>
                <div v-if="options.length === 0" class="text-xs text-ink-soft">
                  Não há transições disponíveis a partir de "{{ statusLabel(order.status) }}".
                </div>
                <div v-else class="flex items-center gap-2">
                  <select v-model="nextStatus" class="apple-input flex-1" aria-label="Novo status">
                    <option value="" disabled>Selecione...</option>
                    <option v-for="s in options" :key="s" :value="s">{{ statusLabel(s) }}</option>
                  </select>
                  <button class="apple-btn" :disabled="!nextStatus || changing" @click="applyStatus">
                    {{ changing ? 'Aplicando...' : 'Aplicar' }}
                  </button>
                </div>
                <div v-if="errorMsg" class="mt-2 text-xs text-system-red">{{ errorMsg }}</div>
              </div>

              <div>
                <div class="text-sm font-medium text-ink mb-2">Itens</div>
                <div class="space-y-2">
                  <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex items-center justify-between rounded-xl bg-white/50 px-3 py-2 transition hover:bg-white/80"
                  >
                    <div class="min-w-0">
                      <div class="text-sm text-ink truncate">{{ item.product?.title ?? ('Produto #' + item.product_id) }}</div>
                      <div class="text-xs text-ink-soft">Qtd: {{ item.quantity }} · {{ money(item.price) }}</div>
                    </div>
                    <div class="text-sm font-medium text-ink tabular-nums">
                      {{ money(item.quantity * item.price) }}
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <div class="text-sm font-medium text-ink mb-3">Histórico de status</div>
                <ol class="relative border-l border-black/10 ml-2 space-y-4">
                  <li v-for="log in order.status_logs" :key="log.id" class="ml-4">
                    <div class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full ring-4 ring-white/60" :class="dotColor(log.to_status)"></div>
                    <div class="text-sm text-ink">
                      {{ log.from_status ? statusLabel(log.from_status) + ' → ' : '' }}
                      <span class="font-medium">{{ statusLabel(log.to_status) }}</span>
                    </div>
                    <div class="text-xs text-ink-soft tabular-nums">{{ formatDateTime(log.changed_at) }}</div>
                  </li>
                </ol>
              </div>
            </template>
          </div>
        </aside>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useOrdersStore } from '../stores/orders'
import StatusBadge from './StatusBadge.vue'
import { allowedTransitions, STATUS_LABELS } from '../utils/statusMachine'

const props = defineProps({
  orderId: { type: [Number, null], default: null },
  open: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const store = useOrdersStore()
const { selected: order, selectedLoading: loading } = storeToRefs(store)

const nextStatus = ref('')
const changing = ref(false)
const errorMsg = ref('')

const options = computed(() => order.value ? allowedTransitions(order.value.status) : [])

// trava o scroll do fundo enquanto o modal está aberto
watch(() => props.open, (isOpen) => {
  document.body.style.overflow = isOpen ? 'hidden' : ''
})
onUnmounted(() => { document.body.style.overflow = '' })

watch(() => props.orderId, async (id) => {
  errorMsg.value = ''
  nextStatus.value = ''
  if (id) await store.fetchOrder(id)
})

async function applyStatus() {
  if (!nextStatus.value) return
  changing.value = true
  errorMsg.value = ''
  try {
    await store.changeStatus(order.value.id, nextStatus.value)
    nextStatus.value = ''
  } catch (e) {
    const msg = e?.response?.data?.errors?.message
    errorMsg.value = msg || 'Não foi possível mudar o status.'
  } finally {
    changing.value = false
  }
}

function statusLabel(s) { return STATUS_LABELS[s] ?? s }
function money(v) { return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('pt-BR') : '—' }
function formatDateTime(d) { return d ? new Date(d).toLocaleString('pt-BR') : '—' }
function dotColor(status) {
  const map = { pending: 'bg-system-orange', approved: 'bg-system-green', cancelled: 'bg-system-red', refunded: 'bg-ink-soft' }
  return map[status] ?? 'bg-ink-soft'
}
</script>

<style scoped>
.drawer-root { position: fixed; inset: 0; z-index: 50; }
.drawer-backdrop {
  position: absolute; inset: 0;
  background: rgba(0, 0, 0, 0.25);
  backdrop-filter: blur(2px);
}
.drawer-panel {
  position: absolute; right: 0; top: 0;
  height: 100%; width: 100%; max-width: 28rem;
  display: flex; flex-direction: column;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: saturate(180%) blur(20px);
  -webkit-backdrop-filter: saturate(180%) blur(20px);
  box-shadow: -12px 0 40px rgba(0, 0, 0, 0.12);
}
.drawer-enter-active, .drawer-leave-active { transition: opacity 0.3s cubic-bezier(0.28, 0.11, 0.32, 1); }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-active .drawer-panel, .drawer-leave-active .drawer-panel {
  transition: transform 0.35s cubic-bezier(0.28, 0.11, 0.32, 1);
}
.drawer-enter-from .drawer-panel, .drawer-leave-to .drawer-panel { transform: translateX(100%); }
</style>
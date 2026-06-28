<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
    :class="cls"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="dot"></span>
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
})

const map = {
  pending:   { label: 'Pendente',     cls: 'bg-system-orange/10 text-system-orange', dot: 'bg-system-orange' },
  approved:  { label: 'Aprovado',     cls: 'bg-system-green/10 text-system-green',   dot: 'bg-system-green' },
  cancelled: { label: 'Cancelado',    cls: 'bg-system-red/10 text-system-red',       dot: 'bg-system-red' },
  refunded:  { label: 'Reembolsado',  cls: 'bg-ink-soft/10 text-ink-soft',           dot: 'bg-ink-soft' },
}

const label = computed(() => map[props.status]?.label ?? props.status)
const cls = computed(() => map[props.status]?.cls ?? 'bg-black/5 text-ink-soft')
const dot = computed(() => map[props.status]?.dot ?? 'bg-ink-soft')
</script>
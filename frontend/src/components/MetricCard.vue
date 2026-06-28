<template>
  <div class="glass rounded-2xl2 shadow-apple p-6 transition duration-300 ease-apple hover:shadow-apple-md">
    <!-- skeleton enquanto carrega -->
    <template v-if="loading">
      <div class="animate-pulse">
        <div class="h-4 w-24 bg-black/10 rounded-full"></div>
        <div class="mt-4 h-9 w-32 bg-black/10 rounded-lg"></div>
      </div>
    </template>

    <template v-else>
      <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-ink-soft">{{ label }}</span>
      </div>
      <div class="mt-2 text-3xl font-semibold tracking-tight text-ink tabular-nums">
        {{ formattedValue }}
      </div>
      <div v-if="hint" class="mt-1 text-xs text-ink-soft">{{ hint }}</div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  hint: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  money: { type: Boolean, default: false },
})

const formattedValue = computed(() => {
  if (props.money) {
    const n = Number(props.value || 0)
    return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  }
  return props.prefix + props.value + props.suffix
})
</script>
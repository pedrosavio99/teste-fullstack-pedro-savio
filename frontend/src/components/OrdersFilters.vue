<template>
  <div class="glass rounded-2xl2 shadow-apple p-4 sm:p-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <!-- afiliado -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-affiliate">Afiliado (ID)</label>
        <input
          id="f-affiliate"
          v-model="local.affiliate_id"
          type="number"
          min="1"
          placeholder="ex: 1"
          class="apple-input"
          @input="onChange"
        />
      </div>

      <!-- status -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-status">Status</label>
        <select id="f-status" v-model="local.status" class="apple-input" @change="onChange">
          <option value="">Todos</option>
          <option value="pending">Pendente</option>
          <option value="approved">Aprovado</option>
          <option value="cancelled">Cancelado</option>
          <option value="refunded">Reembolsado</option>
        </select>
      </div>

      <!-- data de -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-from">De</label>
        <input id="f-from" v-model="local.date_from" type="date" class="apple-input" @change="onChange" />
      </div>

      <!-- data até -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-to">Até</label>
        <input id="f-to" v-model="local.date_to" type="date" class="apple-input" @change="onChange" />
      </div>

      <!-- valor min -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-min">Valor mín</label>
        <input id="f-min" v-model="local.min_value" type="number" step="0.01" min="0" placeholder="0,00" class="apple-input" @input="onChange" />
      </div>

      <!-- valor max -->
      <div>
        <label class="block text-xs font-medium text-ink-soft mb-1" for="f-max">Valor máx</label>
        <input id="f-max" v-model="local.max_value" type="number" step="0.01" min="0" placeholder="0,00" class="apple-input" @input="onChange" />
      </div>

      <!-- botão limpar -->
      <div class="flex items-end">
        <button
          type="button"
          class="apple-btn-ghost w-full"
          @click="clear"
        >
          Limpar filtros
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { debounce } from '../utils/debounce'

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'change'])

// cópia local dos filtros para edição
const local = reactive({
  affiliate_id: props.modelValue.affiliate_id ?? '',
  status: props.modelValue.status ?? '',
  date_from: props.modelValue.date_from ?? '',
  date_to: props.modelValue.date_to ?? '',
  min_value: props.modelValue.min_value ?? '',
  max_value: props.modelValue.max_value ?? '',
})

// se os filtros externos mudarem (ex: vindos da URL), reflete aqui
watch(() => props.modelValue, (v) => {
  Object.assign(local, {
    affiliate_id: v.affiliate_id ?? '',
    status: v.status ?? '',
    date_from: v.date_from ?? '',
    date_to: v.date_to ?? '',
    min_value: v.min_value ?? '',
    max_value: v.max_value ?? '',
  })
})

const emitChange = debounce(() => {
  emit('update:modelValue', { ...props.modelValue, ...local, page: 1 })
  emit('change')
}, 400)

function onChange() {
  emitChange()
}

function clear() {
  Object.assign(local, {
    affiliate_id: '', status: '', date_from: '', date_to: '',
    min_value: '', max_value: '',
  })
  emit('update:modelValue', {
    ...props.modelValue, affiliate_id: '', status: '', date_from: '',
    date_to: '', min_value: '', max_value: '', page: 1,
  })
  emit('change')
}
</script>
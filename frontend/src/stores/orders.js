import { defineStore } from 'pinia'
import { ordersApi } from '../services/api'

export const useOrdersStore = defineStore('orders', {
  state: () => ({
    // listagem
    orders: [],
    meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    loading: false,

    // filtros (fonte de verdade sincronizada com a URL)
    filters: {
      affiliate_id: '',
      status: '',
      date_from: '',
      date_to: '',
      min_value: '',
      max_value: '',
      sort_by: 'ordered_at',
      sort_dir: 'desc',
      page: 1,
    },

    // métricas
    metrics: null,
    metricsLoading: false,

    // detalhe (drawer)
    selected: null,
    selectedLoading: false,
  }),

  actions: {
    async fetchOrders() {
      this.loading = true
      try {
        // monta os params, removendo os vazios
        const params = {}
        Object.entries(this.filters).forEach(([k, v]) => {
          if (v !== '' && v !== null && v !== undefined) {
            // 'page' vira o param de paginação do Laravel
            if (k === 'page') params.page = v
            else params[k] = v
          }
        })

        const { data } = await ordersApi.list(params)
        this.orders = data.data
        this.meta = data.meta
      } finally {
        this.loading = false
      }
    },

    async fetchMetrics() {
      this.metricsLoading = true
      try {
        const { data } = await ordersApi.metrics()
        this.metrics = data.data
      } finally {
        this.metricsLoading = false
      }
    },

    async fetchOrder(id) {
      this.selectedLoading = true
      try {
        const { data } = await ordersApi.get(id)
        this.selected = data.data
      } finally {
        this.selectedLoading = false
      }
    },

    async changeStatus(id, status) {
      const { data } = await ordersApi.updateStatus(id, status)
      // atualiza o pedido na lista e o selecionado
      await this.fetchOrders()
      await this.fetchMetrics()
      if (this.selected && this.selected.id === id) {
        await this.fetchOrder(id)
      }
      return data
    },

    clearSelected() {
      this.selected = null
    },

    resetFilters() {
      this.filters = {
        affiliate_id: '', status: '', date_from: '', date_to: '',
        min_value: '', max_value: '', sort_by: 'ordered_at',
        sort_dir: 'desc', page: 1,
      }
    },
  },
})
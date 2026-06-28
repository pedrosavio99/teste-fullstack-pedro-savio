import axios from 'axios'

// URL base da API do backend Laravel.
// Em dev, o backend é servido pelo nginx do Docker na porta 8000.
const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

const api = axios.create({
  baseURL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

export default api

// ===== Funções de acesso aos endpoints =====

export const ordersApi = {
  // lista paginada com filtros (recebe um objeto de params)
  list(params = {}) {
    return api.get('/orders', { params })
  },

  // detalhe do pedido com itens e histórico
  get(id) {
    return api.get(`/orders/${id}`)
  },

  // métricas agregadas (com cache no backend)
  metrics() {
    return api.get('/orders/metrics')
  },

  // muda o status de um pedido
  updateStatus(id, status) {
    return api.post(`/orders/${id}/status`, { status })
  },
}

export const affiliatesApi = {
  summary(id) {
    return api.get(`/affiliates/${id}/summary`)
  },
}
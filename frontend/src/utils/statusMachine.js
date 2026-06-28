// Espelho da máquina de estados do backend, usado APENAS para montar o
// dropdown com as transições válidas. A validação real é feita no backend
// (que retorna 422 em transição inválida); aqui é só experiência de uso.
export const TRANSITIONS = {
  pending: ['approved', 'cancelled'],
  approved: ['refunded'],
  cancelled: [],
  refunded: [],
}

export const STATUS_LABELS = {
  pending: 'Pendente',
  approved: 'Aprovado',
  cancelled: 'Cancelado',
  refunded: 'Reembolsado',
}

export function allowedTransitions(status) {
  return TRANSITIONS[status] ?? []
}
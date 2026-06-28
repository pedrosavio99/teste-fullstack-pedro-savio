# Teste Full Stack — Sistema de Gestão de Pedidos

Módulo de gestão de pedidos para uma operação de e-commerce com múltiplos afiliados. O sistema consome a [fakestoreapi](https://fakestoreapi.com) (cada `cart` vira um pedido, cada `user` vira um afiliado), processa os dados de forma assíncrona, persiste localmente em MySQL e expõe um dashboard em Vue com métricas, filtros, controle de status e notificações orientadas a eventos via N8N.

Repositório: https://github.com/pedrosavio99/teste-fullstack-pedro-savio

> A aplicação foi escrita pensando em volume crescente (como se a base pudesse chegar a 500 mil pedidos): índices compostos, agregações no banco, cache, importação assíncrona idempotente e filas com retry.

---

## Sumário

- [Stack](#stack)
- [Arquitetura](#arquitetura)
- [Pré-requisitos](#pré-requisitos)
- [Passo a passo de instalação](#passo-a-passo-de-instalação)
- [Como rodar os testes](#como-rodar-os-testes)
- [Endpoints da API](#endpoints-da-api)
- [Importação assíncrona](#importação-assíncrona)
- [SQL avançado](#sql-avançado)
- [Notificações com N8N](#notificações-com-n8n)
- [Frontend](#frontend)
- [Decisões técnicas e trade-offs](#decisões-técnicas-e-trade-offs)
- [O que ficou fora do escopo](#o-que-ficou-fora-do-escopo)

---

## Stack

**Backend:** PHP 8.4, Laravel 13, MySQL 8, Redis 7 (cache e filas), filas com worker dedicado.
**Frontend:** Vue 3 (Vite), Pinia, Vue Router, Tailwind CSS 3, Axios.
**Infra:** Docker Compose (PHP-FPM, Nginx, MySQL, Redis, Worker, N8N).
**Automação:** N8N para notificações orientadas a eventos.
**Testes:** Pest.

---

## Arquitetura

A aplicação roda inteiramente em containers, orquestrados pelo Docker Compose:

| Serviço | Função |
|---------|--------|
| `app` | PHP-FPM rodando o Laravel (usuário não-root) |
| `nginx` | Servidor HTTP na frente do PHP-FPM |
| `mysql` | Banco de dados (MySQL 8) |
| `redis` | Cache e backend das filas |
| `worker` | Container separado rodando `queue:work` com retry |
| `n8n` | Plataforma de automação de workflows |

O backend segue arquitetura em camadas:

- **Controllers finos** — apenas recebem a request (já validada por Form Request) e devolvem a response padronizada.
- **OrderService** — concentra as regras de negócio e a máquina de estados.
- **OrderRepository** — isola as queries do Eloquent.

> **Imagem sugerida:** uma visão geral do dashboard.
> Salve o print como `docs/img/frontend-dashboard.png` para aparecer aqui:
>
> ![Dashboard](docs/img/frontend-dashboard.png)

---

## Pré-requisitos

- [Docker](https://docs.docker.com/engine/install/) e Docker Compose plugin
- [Node.js](https://nodejs.org) 20+ e npm (apenas para o frontend em desenvolvimento)
- Git

Não é necessário ter PHP, Composer ou MySQL instalados na máquina: tudo roda nos containers.

---

## Passo a passo de instalação

### 1. Clonar o repositório

```bash
git clone git@github.com:pedrosavio99/teste-fullstack-pedro-savio.git
cd teste-fullstack-pedro-savio
```

### 2. Configurar variáveis de ambiente

Copie os arquivos de exemplo e ajuste se necessário:

```bash
# variáveis do Docker (raiz)
cp .env.example .env

# variáveis do Laravel
cp backend/.env.example backend/.env
```

> As senhas no `.env.example` são placeholders (`CHANGE_ME_IN_PRODUCTION`). Para desenvolvimento local podem ser mantidas; em produção devem ser trocadas. Nenhuma credencial real está versionada.

### 3. Subir os containers

```bash
docker compose up -d --build
```

Isso sobe PHP, Nginx, MySQL, Redis, Worker e N8N. Na primeira vez o build demora alguns minutos.

### 4. Preparar o Laravel

```bash
# gerar a chave da aplicação
docker compose exec app php artisan key:generate

# rodar as migrations
docker compose exec app php artisan migrate
```

### 5. Importar os dados

```bash
docker compose exec app php artisan orders:sync
```

O comando enfileira os jobs; o worker processa em segundo plano. Acompanhe com:

```bash
docker compose logs worker -f
```

### 6. Subir o frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

O dashboard fica disponível em `http://localhost:5173`. A API fica em `http://localhost:8000/api`. O N8N fica em `http://localhost:5678`.

### Health check

```bash
curl http://localhost:8000/api/health
```

Retorna o status de MySQL, Redis e worker. Observação: o worker reporta `unknown` até processar o primeiro job com heartbeat; o endpoint não dá falso positivo.

---

## Como rodar os testes

```bash
docker compose exec app php artisan test
```

A suíte (Pest) roda em SQLite em memória, com cache `array` e filas síncronas (configurado em `backend/.env.testing`), garantindo isolamento e velocidade sem depender de infraestrutura externa.

Cobertura:

- **OrderStatusMachineTest** — transições válidas e inválidas da máquina de estados, estados terminais, gravação de auditoria e validação do Form Request.
- **OrderMetricsTest** — cálculo das métricas, comportamento de cache (segunda chamada servida do cache) e invalidação ao mudar status.
- **OrdersSyncCommandTest** — teste de integração do comando `orders:sync` com `Http::fake()`: importação completa, idempotência e preservação de status no re-sync.

---

## Endpoints da API

Todas as respostas seguem o formato padronizado `data` / `meta` / `errors`.

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/health` | Status de MySQL, Redis e worker |
| `GET` | `/api/orders` | Lista paginada (20/página). Filtros: `affiliate_id`, `status`, `date_from`, `date_to`, `min_value`, `max_value`. Ordenação: `sort_by`, `sort_dir` |
| `GET` | `/api/orders/{id}` | Detalhe do pedido com itens e histórico de status |
| `GET` | `/api/orders/metrics` | Métricas agregadas (cache Redis de 5 min, invalidado ao mudar status) |
| `POST` | `/api/orders/{id}/status` | Muda o status via máquina de estados (422 em transição inválida) |
| `GET` | `/api/affiliates/{id}/summary` | Resumo do afiliado: total de pedidos, receita, ticket médio e taxa de cancelamento |

### Máquina de estados

```
pending  → approved | cancelled
approved → refunded
cancelled, refunded → (terminais)
```

Transições inválidas retornam **422** com mensagem clara. Cada transição válida grava um registro em `order_status_logs` (auditoria com status anterior, novo status, usuário responsável e timestamp).

---

## Importação assíncrona

O comando `orders:sync` orquestra a sincronização completa em fases ordenadas via `Bus::chain`:

1. **Afiliados** (`SyncAffiliatesJob`)
2. **Produtos** (`SyncProductsJob`)
3. **Pedidos por página** (`SyncOrdersPageJob`, um job por página)

Essa ordem garante integridade referencial, já que pedidos têm foreign key para afiliados e produtos. Pontos relevantes:

- **Idempotência:** upserts pela chave natural (o `id` da própria fakestoreapi é usado como PK). Rodar o comando N vezes produz o mesmo estado, sem duplicatas.
- **Paginação:** a fakestoreapi não pagina de verdade, então a paginação é simulada por `offset`/`limit`. A arquitetura escala sem mudanças caso a API passe a paginar.
- **Preservação de status:** o upsert de pedidos não sobrescreve o `status`, para não desfazer transições feitas posteriormente.
- **Rate limiting:** via `RateLimiter` do Laravel (janela de 1s) nas chamadas HTTP.
- **Retry:** jobs com 3 tentativas e backoff; falhas vão para `failed_jobs`, reprocessáveis com `php artisan queue:retry`.

---

## SQL avançado

Todas as queries estão em [`queries.sql`](queries.sql) na raiz, cada uma com comentário de objetivo e estratégia.

- **Query A — Ranking de afiliados por receita:** window function `RANK()` sobre a receita líquida (receita bruta menos reembolsos), considerando apenas `approved` e `refunded`.
- **Query B — Cohort dos últimos 6 meses:** CTE recursivo gera o calendário dos meses e `LEFT JOIN` garante que meses sem pedidos apareçam zerados (sem subqueries aninhadas).
- **Query C — Detecção de duplicados:** agrupa por afiliado + dia + valor calculado dos itens, retornando os grupos com os IDs envolvidos. *Validada com inserção temporária de um pedido duplicado, detectado corretamente, e o dado de teste foi removido em seguida.*
- **Query D — Produto mais vendido por afiliado:** `ROW_NUMBER()` com `PARTITION BY affiliate_id`, desempatando por valor.
- **Query E — Otimização (diferencial):** reescreve a consulta lenta eliminando `DATE()` sobre coluna indexada (torna a condição sargável), trocando a subquery correlacionada de soma por uma agregação única via `JOIN`, e restringindo as colunas selecionadas.

---

## Notificações com N8N

A integração é orientada a eventos. Ao mudar o status de um pedido, o `OrderService` dispara o evento `OrderStatusChanged` **após o commit** da transação. Um listener dedicado (`SendOrderStatusWebhook`) enfileira o job `SendOrderWebhookJob`, que faz o `POST` no webhook do N8N. A separação evento → listener → job mantém o service desacoplado da camada de notificação, e falhas no envio não afetam a atualização do pedido.

O job roteia por status, montando o caminho `/webhook/order-approved` ou `/webhook/order-cancelled` a partir do novo status, usando `N8N_WEBHOOK_URL` como base. O job tem 3 tentativas com backoff exponencial (10s, 20s, 40s).

### Contrato do payload

```json
{
  "event": "order.status_changed",
  "order_id": 42,
  "affiliate_id": 7,
  "previous_status": "pending",
  "new_status": "approved",
  "total_value": 259.90,
  "occurred_at": "2024-03-15T14:32:00Z"
}
```

### Workflows

Os dois workflows estão exportados em [`n8n/workflows/`](n8n/workflows/) como JSON.

**Workflow 1 — Pedido aprovado** (`order-approved.json`): recebe o webhook, filtra `new_status = approved`, formata a mensagem de notificação e registra. Os destinos (Slack/Discord/Sheets) são representados por nós HTTP apontando para o webhook.site, conforme autorizado pelo desafio.

> **Imagens sugeridas:** salve os prints como abaixo para aparecerem aqui.
>
> ![Workflow Pedido Aprovado](docs/img/n8n-workflow-aprovado.png)
> ![Execução do Workflow Aprovado](docs/img/n8n-execucao-aprovado.png)

**Workflow 2 — Pedido cancelado** (`order-cancelled.json`): recebe o webhook, filtra `cancelled`, consulta `GET http://nginx/api/affiliates/{id}/summary` na própria API para obter a taxa de cancelamento e, por decisão condicional, dispara um alerta de atenção quando a taxa passa de 30%. O cancelamento é sempre registrado, com os dois caminhos convergindo no registro final.

> ![Workflow Pedido Cancelado](docs/img/n8n-workflow-cancelado.png)
> ![Execução do Workflow Cancelado](docs/img/n8n-execucao-cancelado.png)

### Como importar e testar os workflows localmente

1. Acesse o N8N em `http://localhost:5678` e crie a conta de owner no primeiro acesso.
2. Em cada workflow: menu (`...`) → **Import from File** → selecione o JSON em `n8n/workflows/`.
3. Clique em **Publish** para ativar (o webhook passa a escutar na URL de produção).
4. Dispare uma mudança de status pela API (ex.: aprovar um pedido) e acompanhe a execução na aba **Executions** do workflow.

> Observação: nesta versão o N8N usa conta de owner em vez de basic auth. As variáveis `N8N_USER` e `N8N_PASSWORD` permanecem documentadas no `.env.example` conforme pedido, mas o login real é pela conta criada no primeiro acesso.

---

## Frontend

Dashboard em Vue 3 + Tailwind, com estética inspirada na linguagem visual da Apple (tipografia do sistema, vidro fosco, sombras suaves, cantos arredondados, animações com curva suave).

Funcionalidades:

- **Métricas:** cards com skeleton loader, refresh automático a cada 60s e indicador "atualizado há X min".
- **Tabela avançada:** filtros com debounce de 400ms sincronizados na URL (a URL é a fonte de verdade, pode ser copiada e colada com os filtros aplicados), ordenação clicando no header, seleção múltipla com ação em lote, e estado vazio tratado.
- **Drawer de detalhes:** painel lateral (modal) com detalhes e itens, timeline visual do histórico de status, e dropdown que mostra apenas as transições válidas da máquina de estados, com feedback de erro.
- **Responsividade:** funcional de 360px a 1440px; no mobile a tabela vira lista de cards.
- **Acessibilidade:** `aria-label` nos elementos interativos e foco visível.

> **Imagem sugerida:** o drawer de detalhes aberto, com a timeline.
> Salve o print como `docs/img/frontend-drawer.png`:
>
> ![Drawer de detalhes](docs/img/frontend-drawer.png)

---

## Decisões técnicas e trade-offs

- **`predis` em vez de `phpredis`:** o cliente nativo `phpredis` apresentou instabilidade de conexão no ambiente; optou-se pela biblioteca `predis` (PHP puro), que é estável e não depende de extensão. Trade-off: leve custo de performance em troca de confiabilidade.
- **`id` da API como chave primária:** afiliados, produtos e pedidos usam o `id` da própria fakestoreapi como PK, o que torna o upsert idempotente naturalmente.
- **`decimal` para dinheiro:** valores monetários usam `decimal`, nunca `float`, evitando erros de arredondamento.
- **Snapshot de preço no item:** `order_items.price` guarda o preço no momento do pedido, preservando o valor histórico mesmo se o produto mudar de preço.
- **Cache invalidado, não só por TTL:** as métricas têm TTL de 5 min, mas o cache é explicitamente invalidado a cada mudança de status.
- **Nginx + PHP-FPM:** o PHP-FPM não serve HTTP sozinho; o Nginx foi adicionado como servidor web na frente, padrão da indústria.
- **`changed_by` opcional:** a auditoria registra o usuário responsável, mas o campo é opcional por ora, já que não há camada de autenticação. Viria do usuário autenticado quando a auth existir.
- **Tailwind 3 (não 4):** fixado na versão 3 pela configuração estável e consolidada.
- **Worker é processo de longa duração:** mudanças de código/config exigem `docker compose restart worker`.

---

## O que ficou fora do escopo

- **Autenticação e autorização:** não há login. O `changed_by` da auditoria está preparado para receber o usuário autenticado assim que a camada de auth for adicionada (ex.: Sanctum, já instalado).
- **Google Sheets / Slack reais no N8N:** os destinos de notificação usam nós HTTP mock (webhook.site), conforme autorizado pelo desafio. A integração real exigiria apenas configurar as credenciais OAuth nos nós correspondentes.
- **Paginação real da fonte externa:** a fakestoreapi não pagina; a paginação foi simulada. O `FakeStoreClient` está isolado para que, numa API paginada de verdade, apenas ele precise mudar.
- **Heartbeat do worker no health:** o health reporta o worker como `unknown` até haver um heartbeat recente. A implementação completa do heartbeat (o worker gravando periodicamente no Redis) ficaria como melhoria.
- **Mais cobertura de testes:** a suíte cobre os fluxos críticos pedidos; com mais tempo, cobriria também os filtros da listagem e o resumo do afiliado de forma exaustiva.
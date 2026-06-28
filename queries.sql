-- =====================================================================
-- QUERY A — Ranking de afiliados por receita
-- ---------------------------------------------------------------------
-- Objetivo: listar os top 10 afiliados por receita total, considerando
-- apenas pedidos com status 'approved' e 'refunded'. Inclui nome,
-- quantidade de pedidos, receita bruta, valor reembolsado, receita
-- líquida e uma coluna de ranking.
--
-- Estratégia: agrega os pedidos por afiliado num CTE (faturados), usando
-- SUM com CASE para separar o que é receita bruta (approved + refunded)
-- do que é reembolso (refunded). A window function RANK() ordena por
-- receita líquida sem precisar de subquery. LIMIT 10 traz só o topo.
-- =====================================================================

WITH faturados AS (
    SELECT
        o.affiliate_id,
        COUNT(*)                                                   AS qtd_pedidos,
        SUM(o.total_value)                                         AS receita_bruta,
        SUM(CASE WHEN o.status = 'refunded' THEN o.total_value ELSE 0 END) AS valor_reembolsado
    FROM orders o
    WHERE o.status IN ('approved', 'refunded')
      AND o.deleted_at IS NULL
    GROUP BY o.affiliate_id
)
SELECT
    a.id                                          AS affiliate_id,
    a.name                                        AS afiliado,
    f.qtd_pedidos,
    f.receita_bruta,
    f.valor_reembolsado,
    (f.receita_bruta - f.valor_reembolsado)       AS receita_liquida,
    RANK() OVER (ORDER BY (f.receita_bruta - f.valor_reembolsado) DESC) AS ranking
FROM faturados f
JOIN affiliates a ON a.id = f.affiliate_id
ORDER BY ranking
LIMIT 10;


-- =====================================================================
-- QUERY B — Análise de cohort simplificada (últimos 6 meses)
-- ---------------------------------------------------------------------
-- Objetivo: para cada um dos últimos 6 meses, mostrar total de pedidos
-- novos, aprovados, cancelados e a taxa de aprovação (aprovados/total).
-- Meses sem pedidos devem aparecer com valores zero.
--
-- Estratégia: um CTE recursivo 'meses' gera os 6 meses de referência a
-- partir da data atual (sem depender de existir pedido naquele mês).
-- Outro CTE agrega os pedidos por mês. Um LEFT JOIN do calendário com a
-- agregação garante que meses sem pedido apareçam zerados. Usa CTEs em
-- vez de subqueries aninhadas, conforme pedido. COALESCE zera os nulos.
-- =====================================================================

WITH RECURSIVE meses AS (
    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') AS mes_inicio
    UNION ALL
    SELECT DATE_FORMAT(DATE_ADD(mes_inicio, INTERVAL 1 MONTH), '%Y-%m-01')
    FROM meses
    WHERE mes_inicio < DATE_FORMAT(CURDATE(), '%Y-%m-01')
),
agregado AS (
    SELECT
        DATE_FORMAT(o.ordered_at, '%Y-%m-01') AS mes_inicio,
        COUNT(*)                                                          AS total_pedidos,
        SUM(CASE WHEN o.status = 'approved'  THEN 1 ELSE 0 END)           AS aprovados,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END)           AS cancelados
    FROM orders o
    WHERE o.deleted_at IS NULL
      AND o.ordered_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(o.ordered_at, '%Y-%m-01')
)
SELECT
    DATE_FORMAT(m.mes_inicio, '%Y-%m')           AS mes,
    COALESCE(ag.total_pedidos, 0)                AS total_pedidos,
    COALESCE(ag.aprovados, 0)                    AS aprovados,
    COALESCE(ag.cancelados, 0)                   AS cancelados,
    CASE
        WHEN COALESCE(ag.total_pedidos, 0) = 0 THEN 0
        ELSE ROUND(ag.aprovados / ag.total_pedidos * 100, 2)
    END                                          AS taxa_aprovacao_pct
FROM meses m
LEFT JOIN agregado ag ON ag.mes_inicio = m.mes_inicio
ORDER BY m.mes_inicio;


-- =====================================================================
-- QUERY C — Detecção de pedidos duplicados
-- ---------------------------------------------------------------------
-- Objetivo: encontrar pedidos suspeitos de duplicidade: mesmo
-- affiliate_id, mesmo valor total (SUM(quantity * price) dos itens) e
-- criados no mesmo dia. Retorna os grupos com os IDs envolvidos e o
-- valor duplicado.
--
-- Estratégia: um CTE 'totais' calcula o valor real de cada pedido pelos
-- itens. Agrupa por afiliado + dia + valor e mantém só grupos com mais
-- de um pedido (HAVING COUNT > 1). GROUP_CONCAT lista os IDs do grupo.
-- =====================================================================

WITH totais AS (
    SELECT
        o.id                                  AS order_id,
        o.affiliate_id,
        DATE(o.ordered_at)                    AS dia,
        SUM(oi.quantity * oi.price)           AS valor_pedido
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.deleted_at IS NULL
    GROUP BY o.id, o.affiliate_id, DATE(o.ordered_at)
)
SELECT
    t.affiliate_id,
    t.dia,
    t.valor_pedido                            AS valor_duplicado,
    COUNT(*)                                  AS qtd_pedidos,
    GROUP_CONCAT(t.order_id ORDER BY t.order_id) AS ids_pedidos
FROM totais t
GROUP BY t.affiliate_id, t.dia, t.valor_pedido
HAVING COUNT(*) > 1
ORDER BY t.affiliate_id, t.dia;


-- =====================================================================
-- QUERY D — Produto mais vendido por afiliado
-- ---------------------------------------------------------------------
-- Objetivo: para cada afiliado, retornar o produto mais vendido em
-- quantidade e o valor total gerado. Empate na quantidade: vence o de
-- maior valor.
--
-- Estratégia: CTE 'vendas' soma quantidade e valor por afiliado+produto.
-- CTE 'ranqueado' aplica ROW_NUMBER() com PARTITION BY affiliate_id,
-- ordenando por quantidade desc e valor desc (resolve o empate). O
-- SELECT final mantém só rn = 1. Evita subquery correlacionada.
-- =====================================================================

WITH vendas AS (
    SELECT
        o.affiliate_id,
        oi.product_id,
        SUM(oi.quantity)                AS qtd_vendida,
        SUM(oi.quantity * oi.price)     AS valor_gerado
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.deleted_at IS NULL
    GROUP BY o.affiliate_id, oi.product_id
),
ranqueado AS (
    SELECT
        v.*,
        ROW_NUMBER() OVER (
            PARTITION BY v.affiliate_id
            ORDER BY v.qtd_vendida DESC, v.valor_gerado DESC
        ) AS rn
    FROM vendas v
)
SELECT
    a.id                AS affiliate_id,
    a.name              AS afiliado,
    p.id                AS product_id,
    p.title             AS produto,
    r.qtd_vendida,
    r.valor_gerado
FROM ranqueado r
JOIN affiliates a ON a.id = r.affiliate_id
JOIN products   p ON p.id = r.product_id
WHERE r.rn = 1
ORDER BY a.id;


-- =====================================================================
-- QUERY E — Otimização de query lenta (diferencial)
-- ---------------------------------------------------------------------
-- A query original abaixo era lenta em produção com 500k pedidos:
--
--   SELECT *
--   FROM orders o
--   WHERE o.affiliate_id IN (SELECT id FROM affiliates WHERE status = 'active')
--     AND DATE(o.created_at) >= '2024-01-01'
--     AND (SELECT SUM(oi.quantity * oi.price)
--          FROM order_items oi WHERE oi.order_id = o.id) > 100
--   ORDER BY o.created_at DESC;
--
-- PROBLEMAS DA VERSÃO ORIGINAL:
-- 1) DATE(o.created_at) aplica função sobre a coluna, o que impede o
--    uso de índice em created_at (a condição vira não-sargável e força
--    varredura da tabela inteira).
-- 2) A subquery correlacionada de SUM roda uma vez para CADA pedido
--    candidato (problema N+1 em SQL); com 500k pedidos isso é fatal.
-- 3) SELECT * traz todas as colunas, aumentando I/O e impedindo
--    cobertura por índice.
-- 4) O IN (SELECT ...) pode ser menos eficiente que um JOIN direto
--    dependendo do plano.
--
-- O QUE A VERSÃO OTIMIZADA RESOLVE:
-- 1) Troca DATE(created_at) >= '2024-01-01' por um range direto na
--    coluna (created_at >= '2024-01-01 00:00:00'), que é sargável e
--    usa índice em created_at.
-- 2) Substitui a subquery correlacionada por uma agregação única em
--    order_items (GROUP BY order_id) feita uma só vez, unida via JOIN.
-- 3) Seleciona apenas as colunas necessárias em vez de SELECT *.
-- 4) Usa JOIN com affiliates em vez de IN (subquery).
--
-- ÍNDICES RECOMENDADOS (já contemplados na modelagem):
--   - orders(created_at) para o range e o ORDER BY
--   - orders(affiliate_id) para o JOIN
--   - order_items(order_id) para a agregação
-- =====================================================================

SELECT
    o.id,
    o.affiliate_id,
    o.status,
    o.total_value,
    o.created_at,
    tot.valor_itens
FROM orders o
JOIN affiliates a
    ON a.id = o.affiliate_id
    AND a.status = 'active'
JOIN (
    SELECT oi.order_id, SUM(oi.quantity * oi.price) AS valor_itens
    FROM order_items oi
    GROUP BY oi.order_id
) tot ON tot.order_id = o.id
WHERE o.created_at >= '2024-01-01 00:00:00'
  AND o.deleted_at IS NULL
  AND tot.valor_itens > 100
ORDER BY o.created_at DESC;
<?php
// ============================================================
// GLPI Dashboard API
// Coloque este arquivo em /var/www/html/dashboard/api.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Restrinja ao seu domínio em produção

// ── Configuração do banco ────────────────────────────────────
define('DB_HOST', 'ip_do_servidor_mysql');
define('DB_PORT', 'porta_mysql'); // Ex: 3306
define('DB_NAME', 'nome_do_banco'); // Ex: glpi
define('DB_USER', 'usuario_mysql'); // ← substitua pelo usuário real
define('DB_PASS', 'SUA_SENHA_AQUI'); // ← substitua pela senha real

// ── Período (padrão: 12 meses) ──────────────────────────────
$meses = isset($_GET['meses']) ? (int)$_GET['meses'] : 12;
$meses = in_array($meses, [1, 3, 6, 12]) ? $meses : 12;
$data_inicio = date('Y-m-d', strtotime("-{$meses} months"));

// ── Conexão ─────────────────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha na conexão com o banco.', 'detalhe' => $e->getMessage()]);
    exit;
}

// ── Helper ───────────────────────────────────────────────────
function query(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ============================================================
// 1. KPIs GERAIS
// ============================================================
$kpis = query($pdo, "
    SELECT
        COUNT(*)                                                                      AS total_chamados,
        SUM(CASE WHEN status NOT IN (5,6) THEN 1 ELSE 0 END)                        AS em_aberto,
        SUM(CASE WHEN status IN (5,6)     THEN 1 ELSE 0 END)                        AS fechados,
        SUM(CASE WHEN DATE(date) = CURDATE() THEN 1 ELSE 0 END)                     AS abertos_hoje,
        ROUND(AVG(CASE WHEN status IN (5,6) AND solvedate IS NOT NULL
                  THEN TIMESTAMPDIFF(MINUTE, date, solvedate) END) / 60.0, 1)       AS tmr_horas,
        SUM(CASE WHEN time_to_resolve IS NOT NULL
                  AND solvedate IS NOT NULL
                  AND solvedate > time_to_resolve THEN 1 ELSE 0 END)                AS sla_violado,
        SUM(CASE WHEN time_to_resolve IS NOT NULL
                  AND solvedate IS NOT NULL
                  AND solvedate <= time_to_resolve THEN 1 ELSE 0 END)               AS sla_cumprido,
        ROUND(
            100.0 * SUM(CASE WHEN time_to_resolve IS NOT NULL
                              AND solvedate IS NOT NULL
                              AND solvedate <= time_to_resolve THEN 1 ELSE 0 END)
            / NULLIF(SUM(CASE WHEN time_to_resolve IS NOT NULL
                              AND solvedate IS NOT NULL THEN 1 ELSE 0 END), 0), 1)  AS pct_sla
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND date >= :data_inicio
", [':data_inicio' => $data_inicio]);

// ============================================================
// 2. VOLUME MENSAL
// ============================================================
$volume_mensal = query($pdo, "
    SELECT
        DATE_FORMAT(date, '%b/%y')                                          AS mes,
        DATE_FORMAT(date, '%Y-%m')                                          AS mes_ordem,
        COUNT(*)                                                             AS abertos,
        SUM(CASE WHEN status IN (5,6) THEN 1 ELSE 0 END)                   AS fechados,
        SUM(CASE WHEN time_to_resolve IS NOT NULL
                  AND solvedate IS NOT NULL
                  AND solvedate <= time_to_resolve THEN 1 ELSE 0 END)       AS sla_ok,
        SUM(CASE WHEN time_to_resolve IS NOT NULL
                  AND solvedate IS NOT NULL THEN 1 ELSE 0 END)              AS sla_total
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND date >= :data_inicio
    GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%b/%y')
    ORDER BY mes_ordem
", [':data_inicio' => $data_inicio]);

// ============================================================
// 3. POR STATUS (snapshot atual)
// ============================================================
$por_status = query($pdo, "
    SELECT
        CASE status
            WHEN 1 THEN 'Novo'
            WHEN 2 THEN 'Em Andamento'
            WHEN 3 THEN 'Em Andamento'
            WHEN 4 THEN 'Em Espera'
            WHEN 5 THEN 'Resolvido'
            WHEN 6 THEN 'Fechado'
            ELSE 'Outro'
        END AS label,
        COUNT(*) AS valor
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND date >= :data_inicio
    GROUP BY status
    ORDER BY status
", [':data_inicio' => $data_inicio]);

// ============================================================
// 4. POR PRIORIDADE
// ============================================================
$por_prioridade = query($pdo, "
    SELECT
        CASE priority
            WHEN 1 THEN 'Muito Baixa'
            WHEN 2 THEN 'Baixa'
            WHEN 3 THEN 'Média'
            WHEN 4 THEN 'Alta'
            WHEN 5 THEN 'Muito Alta'
            WHEN 6 THEN 'Maior'
            ELSE 'Indefinida'
        END AS label,
        priority AS ordem,
        COUNT(*) AS valor
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND date >= :data_inicio
    GROUP BY priority
    ORDER BY priority
", [':data_inicio' => $data_inicio]);

// ============================================================
// 5. POR TIPO
// ============================================================
$por_tipo = query($pdo, "
    SELECT
        CASE type WHEN 1 THEN 'Incidente' WHEN 2 THEN 'Requisição' ELSE 'Outro' END AS label,
        COUNT(*) AS valor
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND date >= :data_inicio
    GROUP BY type
", [':data_inicio' => $data_inicio]);

// ============================================================
// 6. TOP CATEGORIAS
// ============================================================
$top_categorias = query($pdo, "
    SELECT
        COALESCE(ic.name, 'Sem categoria') AS cat,
        COUNT(t.id)                         AS total
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    WHERE t.is_deleted = 0
      AND t.date >= :data_inicio
    GROUP BY ic.id, ic.name
    ORDER BY total DESC
    LIMIT 8
", [':data_inicio' => $data_inicio]);

// ============================================================
// 7. TMR POR PRIORIDADE
// ============================================================
$tmr_prioridade = query($pdo, "
    SELECT
        CASE priority
            WHEN 1 THEN 'Muito Baixa'
            WHEN 2 THEN 'Baixa'
            WHEN 3 THEN 'Média'
            WHEN 4 THEN 'Alta'
            WHEN 5 THEN 'Muito Alta'
            WHEN 6 THEN 'Maior'
        END AS label,
        priority AS ordem,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, date, solvedate)) / 60.0, 1) AS tmr
    FROM glpi_tickets
    WHERE is_deleted = 0
      AND status IN (5,6)
      AND solvedate IS NOT NULL
      AND date >= :data_inicio
    GROUP BY priority
    ORDER BY priority
", [':data_inicio' => $data_inicio]);

// ============================================================
// 8. DESEMPENHO POR TÉCNICO
// ============================================================
$tecnicos = query($pdo, "
    SELECT
        CONCAT(u.firstname, ' ', u.realname)                                              AS nome,
        COUNT(DISTINCT t.id)                                                               AS total,
        SUM(CASE WHEN t.status IN (5,6) THEN 1 ELSE 0 END)                               AS resolvidos,
        SUM(CASE WHEN t.status NOT IN (5,6) THEN 1 ELSE 0 END)                           AS abertos,
        ROUND(AVG(CASE WHEN t.status IN (5,6) AND t.solvedate IS NOT NULL
                       THEN TIMESTAMPDIFF(MINUTE, t.date, t.solvedate) END) / 60.0, 1)   AS tmr,
        ROUND(
            100.0 * SUM(CASE WHEN t.time_to_resolve IS NOT NULL
                              AND t.solvedate IS NOT NULL
                              AND t.solvedate <= t.time_to_resolve THEN 1 ELSE 0 END)
            / NULLIF(SUM(CASE WHEN t.time_to_resolve IS NOT NULL
                              AND t.solvedate IS NOT NULL THEN 1 ELSE 0 END), 0), 1)      AS sla
    FROM glpi_tickets t
    INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
    INNER JOIN glpi_users u ON u.id = tu.users_id
    WHERE t.is_deleted = 0
      AND t.date >= :data_inicio
      AND u.is_deleted = 0
    GROUP BY u.id, u.firstname, u.realname
    HAVING total >= 3
    ORDER BY total DESC
    LIMIT 15
", [':data_inicio' => $data_inicio]);

// ============================================================
// RESPOSTA JSON
// ============================================================
echo json_encode([
    'gerado_em'      => date('d/m/Y H:i:s'),
    'periodo_meses'  => $meses,
    'data_inicio'    => $data_inicio,
    'kpis'           => $kpis[0] ?? [],
    'volume_mensal'  => $volume_mensal,
    'por_status'     => $por_status,
    'por_prioridade' => $por_prioridade,
    'por_tipo'       => $por_tipo,
    'top_categorias' => $top_categorias,
    'tmr_prioridade' => $tmr_prioridade,
    'tecnicos'       => $tecnicos,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

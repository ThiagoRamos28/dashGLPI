<?php
// ============================================================
// GLPI Dashboard API
// Coloque este arquivo em /var/www/glpi/public/dashboard/api.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Restrinja ao seu domínio em produção

// ── Configuração do banco (lida do .env) ─────────────────────
$env = parse_ini_file(__DIR__ . '/.env');
if (!$env) {
    http_response_code(500);
    echo json_encode(['erro' => 'Arquivo .env não encontrado ou inválido.']);
    exit;
}
define('DB_HOST', $env['DB_HOST']);
define('DB_PORT', $env['DB_PORT']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);

// ── Parâmetros ───────────────────────────────────────────────
$meses    = isset($_GET['meses'])  ? (int)$_GET['meses']  : 12;
$meses    = in_array($meses, [1, 3, 6, 12]) ? $meses : 12;
$grupo_id = isset($_GET['grupo'])  ? (int)$_GET['grupo']  : 0; // 0 = todos os grupos
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

// ── Helpers ──────────────────────────────────────────────────
function query(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Filtro de grupo via EXISTS (grupo_id é int, sem risco de SQL injection)
function gf(int $g): string {
    if ($g === 0) return '';
    return "AND EXISTS (
        SELECT 1 FROM glpi_groups_tickets _gt
        WHERE _gt.tickets_id = t.id AND _gt.type = 2 AND _gt.groups_id = {$g}
    )";
}

$gf = gf($grupo_id);
$p  = [':data_inicio' => $data_inicio];

// ============================================================
// 1. KPIs GERAIS
// ============================================================
$kpis = query($pdo, "
    SELECT
        COUNT(*)                                                                      AS total_chamados,
        SUM(CASE WHEN t.status NOT IN (5,6) THEN 1 ELSE 0 END)                      AS em_aberto,
        SUM(CASE WHEN t.status IN (5,6)     THEN 1 ELSE 0 END)                      AS fechados,
        SUM(CASE WHEN DATE(t.date) = CURDATE() THEN 1 ELSE 0 END)                   AS abertos_hoje,
        ROUND(AVG(CASE WHEN t.status IN (5,6) AND t.solvedate IS NOT NULL
                  THEN TIMESTAMPDIFF(MINUTE, t.date, t.solvedate) END) / 60.0, 1)   AS tmr_horas,
        SUM(CASE WHEN t.time_to_resolve IS NOT NULL AND t.solvedate IS NOT NULL
                  AND t.solvedate > t.time_to_resolve THEN 1 ELSE 0 END)            AS sla_violado,
        SUM(CASE WHEN t.time_to_resolve IS NOT NULL AND t.solvedate IS NOT NULL
                  AND t.solvedate <= t.time_to_resolve THEN 1 ELSE 0 END)           AS sla_cumprido,
        ROUND(
            100.0 * SUM(CASE WHEN t.time_to_resolve IS NOT NULL AND t.solvedate IS NOT NULL
                              AND t.solvedate <= t.time_to_resolve THEN 1 ELSE 0 END)
            / NULLIF(SUM(CASE WHEN t.time_to_resolve IS NOT NULL
                              AND t.solvedate IS NOT NULL THEN 1 ELSE 0 END), 0), 1) AS pct_sla
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
", $p);

// ============================================================
// 2. VOLUME MENSAL
// ============================================================
$volume_mensal = query($pdo, "
    SELECT
        DATE_FORMAT(t.date, '%b/%y')                                         AS mes,
        DATE_FORMAT(t.date, '%Y-%m')                                         AS mes_ordem,
        COUNT(*)                                                              AS abertos,
        SUM(CASE WHEN t.status IN (5,6) THEN 1 ELSE 0 END)                  AS fechados,
        SUM(CASE WHEN t.time_to_resolve IS NOT NULL AND t.solvedate IS NOT NULL
                  AND t.solvedate <= t.time_to_resolve THEN 1 ELSE 0 END)    AS sla_ok,
        SUM(CASE WHEN t.time_to_resolve IS NOT NULL
                  AND t.solvedate IS NOT NULL THEN 1 ELSE 0 END)             AS sla_total
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
    GROUP BY DATE_FORMAT(t.date, '%Y-%m'), DATE_FORMAT(t.date, '%b/%y')
    ORDER BY mes_ordem
", $p);

// ============================================================
// 3. POR STATUS
// ============================================================
$por_status = query($pdo, "
    SELECT
        CASE t.status
            WHEN 1 THEN 'Novo'
            WHEN 2 THEN 'Em Andamento'
            WHEN 3 THEN 'Em Andamento'
            WHEN 4 THEN 'Em Espera'
            WHEN 5 THEN 'Resolvido'
            WHEN 6 THEN 'Fechado'
            ELSE 'Outro'
        END AS label,
        COUNT(*) AS valor
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
    GROUP BY t.status
    ORDER BY t.status
", $p);

// ============================================================
// 4. POR PRIORIDADE
// ============================================================
$por_prioridade = query($pdo, "
    SELECT
        CASE t.priority
            WHEN 1 THEN 'Muito Baixa'
            WHEN 2 THEN 'Baixa'
            WHEN 3 THEN 'Média'
            WHEN 4 THEN 'Alta'
            WHEN 5 THEN 'Muito Alta'
            WHEN 6 THEN 'Maior'
            ELSE 'Indefinida'
        END AS label,
        t.priority AS ordem,
        COUNT(*) AS valor
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
    GROUP BY t.priority
    ORDER BY t.priority
", $p);

// ============================================================
// 5. POR TIPO
// ============================================================
$por_tipo = query($pdo, "
    SELECT
        CASE t.type WHEN 1 THEN 'Incidente' WHEN 2 THEN 'Requisição' ELSE 'Outro' END AS label,
        COUNT(*) AS valor
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
    GROUP BY t.type
", $p);

// ============================================================
// 6. TOP CATEGORIAS
// ============================================================
$top_categorias = query($pdo, "
    SELECT
        COALESCE(ic.name, 'Sem categoria') AS cat,
        COUNT(t.id)                         AS total
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
    GROUP BY ic.id, ic.name
    ORDER BY total DESC
    LIMIT 8
", $p);

// ============================================================
// 7. TMR POR PRIORIDADE
// ============================================================
$tmr_prioridade = query($pdo, "
    SELECT
        CASE t.priority
            WHEN 1 THEN 'Muito Baixa' WHEN 2 THEN 'Baixa'   WHEN 3 THEN 'Média'
            WHEN 4 THEN 'Alta'        WHEN 5 THEN 'Muito Alta' WHEN 6 THEN 'Maior'
        END AS label,
        t.priority AS ordem,
        ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.date, t.solvedate)) / 60.0, 1) AS tmr
    FROM glpi_tickets t
    WHERE t.is_deleted = 0 AND t.status IN (5,6) AND t.solvedate IS NOT NULL
      AND t.date >= :data_inicio $gf
    GROUP BY t.priority
    ORDER BY t.priority
", $p);

// ============================================================
// 8. DESEMPENHO POR TÉCNICO
// ============================================================
$tecnicos = query($pdo, "
    SELECT
        CONCAT(u.firstname, ' ', u.realname)                                               AS nome,
        COUNT(DISTINCT t.id)                                                                AS total,
        SUM(CASE WHEN t.status IN (5,6) THEN 1 ELSE 0 END)                                AS resolvidos,
        SUM(CASE WHEN t.status NOT IN (5,6) THEN 1 ELSE 0 END)                            AS abertos,
        ROUND(AVG(CASE WHEN t.status IN (5,6) AND t.solvedate IS NOT NULL
                       THEN TIMESTAMPDIFF(MINUTE, t.date, t.solvedate) END) / 60.0, 1)    AS tmr,
        ROUND(
            100.0 * SUM(CASE WHEN t.time_to_resolve IS NOT NULL AND t.solvedate IS NOT NULL
                              AND t.solvedate <= t.time_to_resolve THEN 1 ELSE 0 END)
            / NULLIF(SUM(CASE WHEN t.time_to_resolve IS NOT NULL
                              AND t.solvedate IS NOT NULL THEN 1 ELSE 0 END), 0), 1)       AS sla
    FROM glpi_tickets t
    INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
    INNER JOIN glpi_users u ON u.id = tu.users_id
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio AND u.is_deleted = 0 $gf
    GROUP BY u.id, u.firstname, u.realname
    HAVING total >= 3
    ORDER BY total DESC
    LIMIT 15
", $p);

// ============================================================
// 9. SATISFAÇÃO (glpi_ticketsatisfactions)
// ============================================================
$satisfacao = query($pdo, "
    SELECT
        COUNT(*)                                                                         AS total_enviadas,
        SUM(CASE WHEN ts.date_answered IS NOT NULL THEN 1 ELSE 0 END)                  AS respondidas,
        ROUND(AVG(CASE WHEN ts.satisfaction_scaled_to_5 IS NOT NULL
                       THEN ts.satisfaction_scaled_to_5 END), 1)                        AS media,
        SUM(CASE WHEN ROUND(ts.satisfaction_scaled_to_5) = 1 THEN 1 ELSE 0 END)       AS nota_1,
        SUM(CASE WHEN ROUND(ts.satisfaction_scaled_to_5) = 2 THEN 1 ELSE 0 END)       AS nota_2,
        SUM(CASE WHEN ROUND(ts.satisfaction_scaled_to_5) = 3 THEN 1 ELSE 0 END)       AS nota_3,
        SUM(CASE WHEN ROUND(ts.satisfaction_scaled_to_5) = 4 THEN 1 ELSE 0 END)       AS nota_4,
        SUM(CASE WHEN ROUND(ts.satisfaction_scaled_to_5) = 5 THEN 1 ELSE 0 END)       AS nota_5,
        ROUND(100.0 * SUM(CASE WHEN ts.date_answered IS NOT NULL THEN 1 ELSE 0 END)
            / NULLIF(COUNT(*), 0), 1)                                                   AS taxa_resposta
    FROM glpi_ticketsatisfactions ts
    INNER JOIN glpi_tickets t ON t.id = ts.tickets_id
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio $gf
", $p);

// ============================================================
// 10. COMENTÁRIOS RECENTES DE SATISFAÇÃO
// ============================================================
$comentarios = query($pdo, "
    SELECT
        ts.satisfaction_scaled_to_5             AS nota,
        ts.comment,
        DATE_FORMAT(ts.date_answered, '%d/%m/%Y') AS data
    FROM glpi_ticketsatisfactions ts
    INNER JOIN glpi_tickets t ON t.id = ts.tickets_id
    WHERE t.is_deleted = 0
      AND ts.date_answered IS NOT NULL
      AND ts.comment IS NOT NULL AND ts.comment != ''
      AND t.date >= :data_inicio $gf
    ORDER BY ts.date_answered DESC
    LIMIT 5
", $p);

// ============================================================
// 11. LISTA DE GRUPOS DISPONÍVEIS
// ============================================================
$grupos = query($pdo, "
    SELECT id, name FROM glpi_groups
    WHERE is_deleted = 0 AND is_assign = 1
    ORDER BY name
");

// ============================================================
// RESPOSTA JSON
// ============================================================
echo json_encode([
    'gerado_em'      => date('d/m/Y H:i:s'),
    'periodo_meses'  => $meses,
    'grupo_id'       => $grupo_id,
    'data_inicio'    => $data_inicio,
    'kpis'           => $kpis[0] ?? [],
    'volume_mensal'  => $volume_mensal,
    'por_status'     => $por_status,
    'por_prioridade' => $por_prioridade,
    'por_tipo'       => $por_tipo,
    'top_categorias' => $top_categorias,
    'tmr_prioridade' => $tmr_prioridade,
    'tecnicos'       => $tecnicos,
    'satisfacao'     => $satisfacao[0] ?? [],
    'comentarios'    => $comentarios,
    'grupos'         => $grupos,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

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
$categoria_id = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;

if (!empty($_GET['data_inicio']) && !empty($_GET['data_fim'])) {
    // Período customizado via data_inicio + data_fim
    $data_inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio'])
        ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-12 months'));
    $data_fim    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim'])
        ? $_GET['data_fim']    : date('Y-m-d');
    if ($data_fim < $data_inicio) { [$data_inicio, $data_fim] = [$data_fim, $data_inicio]; }
    $meses = null;
} else {
    // Fallback: ?meses=1|3|6|12
    $meses       = isset($_GET['meses']) ? (int)$_GET['meses'] : 12;
    $meses       = in_array($meses, [1, 3, 6, 12]) ? $meses : 12;
    $data_inicio = date('Y-m-d', strtotime("-{$meses} months"));
    $data_fim    = date('Y-m-d');
}

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

// Filtro de categoria — inclui a categoria selecionada e TODAS as subcategorias
// Usa completename para navegar a hierarquia em qualquer profundidade
function cf(int $c): string {
    if ($c === 0) return '';
    return "AND t.itilcategories_id IN (
        SELECT _ic.id FROM glpi_itilcategories _ic
        WHERE _ic.id = {$c}
           OR _ic.completename LIKE (
               SELECT CONCAT(_p.completename, ' > %')
               FROM glpi_itilcategories _p WHERE _p.id = {$c}
           )
    )";}


$gf = cf($categoria_id);
$p  = [':data_inicio' => $data_inicio, ':data_fim' => $data_fim];

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
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio AND t.date <= :data_fim $gf
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
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio AND t.date <= :data_fim $gf
    GROUP BY DATE_FORMAT(t.date, '%Y-%m'), DATE_FORMAT(t.date, '%b/%y')
    ORDER BY mes_ordem
", $p);

// ============================================================
// 3. TOP CATEGORIAS
// ============================================================
$top_categorias = query($pdo, "
    SELECT
        COALESCE(ic.name, 'Sem categoria') AS cat,
        COUNT(t.id)                         AS total
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio AND t.date <= :data_fim $gf
    GROUP BY ic.id, ic.name
    ORDER BY total DESC
    LIMIT 8
", $p);

// ============================================================
// 4. DESEMPENHO POR TÉCNICO
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
    WHERE t.is_deleted = 0 AND t.date >= :data_inicio AND t.date <= :data_fim AND u.is_deleted = 0 $gf
    GROUP BY u.id, u.firstname, u.realname
    HAVING total >= 3
    ORDER BY total DESC
    LIMIT 15
", $p);

// ============================================================
// CHAMADOS CRÍTICOS (abertos agora — sem filtro de data)
// ============================================================
$chamados_criticos = query($pdo, "
    SELECT
        t.id,
        t.name                                                              AS titulo,
        t.priority,
        ic.name                                                             AS categoria,
        CONCAT(u.firstname, ' ', u.realname)                               AS tecnico,
        DATE_FORMAT(t.date, '%d/%m/%Y %H:%i')                              AS abertura,
        t.time_to_resolve                                                   AS prazo_sla,
        CASE WHEN t.time_to_resolve IS NOT NULL
              AND NOW() > t.time_to_resolve THEN 1 ELSE 0 END              AS sla_violado,
        TIMESTAMPDIFF(HOUR, t.date, NOW())                                  AS horas_aberto
    FROM glpi_tickets t
    LEFT JOIN glpi_itilcategories ic ON ic.id = t.itilcategories_id
    LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
    LEFT JOIN glpi_users u ON u.id = tu.users_id AND u.is_deleted = 0
    WHERE t.is_deleted = 0
      AND t.status NOT IN (5, 6)
      $gf
    GROUP BY t.id, t.name, t.priority, ic.name, u.firstname, u.realname,
             t.date, t.time_to_resolve
    ORDER BY sla_violado DESC, t.priority DESC, t.date ASC
    LIMIT 10
", []);

// ============================================================
// 5. SATISFAÇÃO (glpi_ticketsatisfactions)
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
    INNER JOIN glpi_tickets t ON t.id = ts.tickets_id AND t.is_deleted = 0
        AND t.date >= :data_inicio AND t.date <= :data_fim
    WHERE 1=1 $gf
", $p);

// ============================================================
// 6. COMENTÁRIOS RECENTES DE SATISFAÇÃO
// ============================================================
$comentarios = query($pdo, "
    SELECT
        ts.satisfaction_scaled_to_5               AS nota,
        ts.comment,
        DATE_FORMAT(ts.date_answered, '%d/%m/%Y') AS data
    FROM glpi_ticketsatisfactions ts
    INNER JOIN glpi_tickets t ON t.id = ts.tickets_id AND t.is_deleted = 0
        AND t.date >= :data_inicio AND t.date <= :data_fim
    WHERE ts.date_answered IS NOT NULL
      AND ts.comment IS NOT NULL AND ts.comment != ''
      $gf
    ORDER BY ts.date_answered DESC
    LIMIT 5
", $p);

// ============================================================
// RESPOSTA JSON
// ============================================================
echo json_encode([
    'gerado_em'         => date('d/m/Y H:i:s'),
    'periodo_meses'     => $meses,
    'categoria_id'      => $categoria_id,
    'data_inicio'       => $data_inicio,
    'data_fim'          => $data_fim,
    'kpis'              => $kpis[0] ?? [],
    'volume_mensal'     => $volume_mensal,
    'top_categorias'    => $top_categorias,
    'tecnicos'          => $tecnicos,
    'satisfacao'        => $satisfacao[0] ?? [],
    'comentarios'       => $comentarios,
    'chamados_criticos' => $chamados_criticos,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

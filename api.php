<?php
// ============================================================
//  Aero Legacy — MySQL leaderboard API
//  Nahraj na stejný server jako quiz.html
//
//  SETUP:
//  1. Vytvoř MySQL databázi (přes phpMyAdmin nebo hosting panel)
//  2. Vyplň přihlašovací údaje níže
//  3. Nahraj tento soubor na server (stejná složka jako quiz.html)
//  4. Otevři https://tvuj-web.cz/api.php?action=install
//     → vytvoří tabulku automaticky
// ============================================================

// ── VYPLŇ TYTO ÚDAJE ────────────────────────────────────────
define('DB_HOST', 'sql101.infinityfree.com');       // většinou localhost
define('DB_NAME', 'if0_41384754_score');                // název databáze
define('DB_USER', 'if0_41384754');                // uživatel databáze
define('DB_PASS', '42Pindikov');                // heslo
// ── KONEC KONFIGURACE ────────────────────────────────────────

// Povol CORS pro quiz.html (na stejném nebo jiném doméně)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';

// ── DB PŘIPOJENÍ ─────────────────────────────────────────────
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}

// ── INSTALL — vytvoří tabulku ─────────────────────────────────
if ($action === 'install') {
    db()->exec("
        CREATE TABLE IF NOT EXISTS scores (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(50)  NOT NULL,
            score        INT          NOT NULL,
            correct      TINYINT,
            total        TINYINT,
            accuracy     TINYINT,
            best_streak  TINYINT,
            time_seconds SMALLINT,
            mode         VARCHAR(20),
            date         VARCHAR(20),
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_score (score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo json_encode(['ok' => true, 'message' => 'Tabulka scores vytvorena (nebo uz existuje).']);
    exit;
}

// ── SCORES — vrátí top 100 ────────────────────────────────────
if ($action === 'scores') {
    $rows = db()
        ->query("SELECT name, score, correct, accuracy, best_streak, time_seconds, mode, date
                 FROM scores
                 ORDER BY score DESC
                 LIMIT 100")
        ->fetchAll();
    echo json_encode($rows);
    exit;
}

// ── SAVE — uloží nové skóre ───────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['name'], $data['score'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing name or score']);
        exit;
    }

    // Základní sanitace
    $name    = mb_substr(trim((string)($data['name']   ?? 'Anonymous')), 0, 50);
    $score   = (int)($data['score']        ?? 0);
    $correct = (int)($data['correct']      ?? 0);
    $total   = (int)($data['total']        ?? 10);
    $acc     = (int)($data['accuracy']     ?? 0);
    $streak  = (int)($data['best_streak']  ?? 0);
    $time    = (int)($data['time_seconds'] ?? 0);
    $mode    = mb_substr(trim((string)($data['mode'] ?? 'mixed')), 0, 20);
    $date    = mb_substr(trim((string)($data['date'] ?? '')),      0, 20);

    $stmt = db()->prepare("
        INSERT INTO scores (name, score, correct, total, accuracy, best_streak, time_seconds, mode, date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $score, $correct, $total, $acc, $streak, $time, $mode, $date]);

    echo json_encode(['ok' => true, 'id' => db()->lastInsertId()]);
    exit;
}

// ── HEALTH check ─────────────────────────────────────────────
if ($action === 'health') {
    echo json_encode(['ok' => true, 'db' => DB_NAME !== '']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action. Use: install, scores, save, health']);

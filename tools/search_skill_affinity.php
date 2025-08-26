<?php
// エラー表示を無効にしてJSONレスポンスを保護
ini_set('display_errors', 0);
error_reporting(0);

// ========== データベース接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { 
        throw new Exception('データベース接続エラー');
    }
    $conn->set_charset("utf8mb4");

// ========== 検索条件の受け取り ==========
$character_id = isset($_GET['character_id']) ? intval($_GET['character_id']) : 0;
$distance_type = $_GET['distance_type'] ?? '';
$strategy_type = $_GET['strategy_type'] ?? '';
$surface_type = $_GET['surface_type'] ?? '';
$condition_phase = $_GET['condition_phase'] ?? '';
$condition_position = $_GET['condition_position'] ?? '';

// ========== SQLクエリの構築 ==========
$select_fields = "s.*";
$tables = " FROM skills s ";
$where_conditions = [];
$params = [];
$param_types = "";

// --- 適性ランクをスコアに変換する関数 ---
function aptitude_to_score($aptitude) {
    switch (strtoupper($aptitude)) {
        case 'S': return 20;
        case 'A': return 15;
        case 'B': return 10;
        case 'C': return 5;
        default: return 0;
    }
}

// --- スコア計算のロジック ---
$score_calculation = "";
if ($character_id) {
    // ▼ ウマ娘が選択された場合：スキルの条件とウマ娘の適性を照合 ▼
    $tables .= " LEFT JOIN characters c ON c.id = ? ";
    array_unshift($params, $character_id);
    $param_types = "i" . $param_types;

    // ▼ フィルタ条件は削除：全てのスキルを表示し、適性でスコアリング ▼

    $score_calculation = "
        (
            -- 距離適性マッチング（選択した距離条件と一致する場合はボーナス）
            CASE 
                WHEN s.distance_type = ? AND s.distance_type = '短距離' THEN 
                    CASE WHEN c.distance_aptitude_short IN ('S', 'A') THEN 50 WHEN c.distance_aptitude_short = 'B' THEN 30 WHEN c.distance_aptitude_short = 'C' THEN 15 ELSE 5 END
                WHEN s.distance_type = ? AND s.distance_type = 'マイル' THEN 
                    CASE WHEN c.distance_aptitude_mile IN ('S', 'A') THEN 50 WHEN c.distance_aptitude_mile = 'B' THEN 30 WHEN c.distance_aptitude_mile = 'C' THEN 15 ELSE 5 END
                WHEN s.distance_type = ? AND s.distance_type = '中距離' THEN 
                    CASE WHEN c.distance_aptitude_medium IN ('S', 'A') THEN 50 WHEN c.distance_aptitude_medium = 'B' THEN 30 WHEN c.distance_aptitude_medium = 'C' THEN 15 ELSE 5 END
                WHEN s.distance_type = ? AND s.distance_type = '長距離' THEN 
                    CASE WHEN c.distance_aptitude_long IN ('S', 'A') THEN 50 WHEN c.distance_aptitude_long = 'B' THEN 30 WHEN c.distance_aptitude_long = 'C' THEN 15 ELSE 5 END
                -- 選択した距離条件と異なる場合は低スコア
                WHEN s.distance_type = '短距離' THEN 
                    CASE WHEN c.distance_aptitude_short IN ('S', 'A') THEN 10 WHEN c.distance_aptitude_short = 'B' THEN 5 ELSE 1 END
                WHEN s.distance_type = 'マイル' THEN 
                    CASE WHEN c.distance_aptitude_mile IN ('S', 'A') THEN 10 WHEN c.distance_aptitude_mile = 'B' THEN 5 ELSE 1 END
                WHEN s.distance_type = '中距離' THEN 
                    CASE WHEN c.distance_aptitude_medium IN ('S', 'A') THEN 10 WHEN c.distance_aptitude_medium = 'B' THEN 5 ELSE 1 END
                WHEN s.distance_type = '長距離' THEN 
                    CASE WHEN c.distance_aptitude_long IN ('S', 'A') THEN 10 WHEN c.distance_aptitude_long = 'B' THEN 5 ELSE 1 END
                ELSE 5
            END +
            -- 脚質適性マッチング（選択した脚質条件と一致する場合はボーナス）
            CASE 
                WHEN s.strategy_type = ? AND s.strategy_type = '逃げ' THEN 
                    CASE WHEN c.strategy_aptitude_runner IN ('S', 'A') THEN 50 WHEN c.strategy_aptitude_runner = 'B' THEN 30 WHEN c.strategy_aptitude_runner = 'C' THEN 15 ELSE 5 END
                WHEN s.strategy_type = ? AND s.strategy_type = '先行' THEN 
                    CASE WHEN c.strategy_aptitude_leader IN ('S', 'A') THEN 50 WHEN c.strategy_aptitude_leader = 'B' THEN 30 WHEN c.strategy_aptitude_leader = 'C' THEN 15 ELSE 5 END
                WHEN s.strategy_type = ? AND s.strategy_type = '差し' THEN 
                    CASE WHEN c.strategy_aptitude_chaser IN ('S', 'A') THEN 50 WHEN c.strategy_aptitude_chaser = 'B' THEN 30 WHEN c.strategy_aptitude_chaser = 'C' THEN 15 ELSE 5 END
                WHEN s.strategy_type = ? AND s.strategy_type = '追込' THEN 
                    CASE WHEN c.strategy_aptitude_trailer IN ('S', 'A') THEN 50 WHEN c.strategy_aptitude_trailer = 'B' THEN 30 WHEN c.strategy_aptitude_trailer = 'C' THEN 15 ELSE 5 END
                -- 選択した脚質条件と異なる場合は低スコア
                WHEN s.strategy_type = '逃げ' THEN 
                    CASE WHEN c.strategy_aptitude_runner IN ('S', 'A') THEN 8 WHEN c.strategy_aptitude_runner = 'B' THEN 4 ELSE 1 END
                WHEN s.strategy_type = '先行' THEN 
                    CASE WHEN c.strategy_aptitude_leader IN ('S', 'A') THEN 8 WHEN c.strategy_aptitude_leader = 'B' THEN 4 ELSE 1 END
                WHEN s.strategy_type = '差し' THEN 
                    CASE WHEN c.strategy_aptitude_chaser IN ('S', 'A') THEN 8 WHEN c.strategy_aptitude_chaser = 'B' THEN 4 ELSE 1 END
                WHEN s.strategy_type = '追込' THEN 
                    CASE WHEN c.strategy_aptitude_trailer IN ('S', 'A') THEN 8 WHEN c.strategy_aptitude_trailer = 'B' THEN 4 ELSE 1 END
                ELSE 5
            END +
            -- バ場適性マッチング
            CASE s.surface_type
                WHEN '芝' THEN CASE WHEN c.surface_aptitude_turf IN ('S', 'A') THEN 15 WHEN c.surface_aptitude_turf = 'B' THEN 10 WHEN c.surface_aptitude_turf = 'C' THEN 5 ELSE 0 END
                WHEN 'ダート' THEN CASE WHEN c.surface_aptitude_dirt IN ('S', 'A') THEN 15 WHEN c.surface_aptitude_dirt = 'B' THEN 10 WHEN c.surface_aptitude_dirt = 'C' THEN 5 ELSE 0 END
                ELSE 5
            END +
            -- 条件適性ボーナス
            CASE WHEN s.condition_phase = ? THEN 10 ELSE 0 END +
            CASE WHEN s.condition_position = ? THEN 10 ELSE 0 END
        ) AS affinity_score
    ";
    // パラメータを追加（距離条件4回、脚質条件4回、フェーズ1回、位置1回）
    array_push($params, $distance_type, $distance_type, $distance_type, $distance_type);
    array_push($params, $strategy_type, $strategy_type, $strategy_type, $strategy_type);
    array_push($params, $condition_phase, $condition_position);
    $param_types .= "ssssssssss";

} else {
    // ▼ ウマ娘が選択されていない場合：従来通りのスコア計算 ▼
    $score_calculation = "
        (
            CASE WHEN s.distance_type = ? THEN 10 ELSE 0 END +
            CASE WHEN s.strategy_type = ? THEN 10 ELSE 0 END +
            CASE WHEN s.surface_type = ? THEN 5 ELSE 0 END +
            CASE WHEN s.condition_phase = ? THEN 5 ELSE 0 END +
            CASE WHEN s.condition_position = ? THEN 5 ELSE 0 END
        ) AS affinity_score
    ";
    array_push($params, $distance_type, $strategy_type, $surface_type, $condition_phase, $condition_position);
    $param_types .= "sssss";
}
$select_fields .= ", " . $score_calculation;

// 進化スキルと固有スキルを除外するフィルタ
$where_conditions[] = "s.skill_type NOT IN ('進化スキル', '固有スキル')";

// クエリの組み立て
$sql = "SELECT " . $select_fields . $tables;
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " ORDER BY affinity_score DESC, s.id DESC LIMIT 100";

// ========== DB検索実行 ==========
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $skills = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $skills = [];
    error_log("SQL prepare failed: " . $conn->error);
}
$conn->close();

// ========== 結果をHTML形式で生成 ==========
ob_start();
if (empty($skills)) {
    echo "<p>条件に一致するスキルが見つかりませんでした。</p>";
} else {
?>
    <div class="skill-results-list">
        <?php foreach($skills as $skill): ?>
            <div class="skill-item">
                <div class="skill-icon-wrapper">
                    <?php
                    $icon_path = !empty($skill['icon_path']) ? '../' . $skill['icon_path'] : '';
                    if ($icon_path && file_exists($icon_path)):
                    ?>
                        <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="<?php echo htmlspecialchars($skill['skill_name']); ?>" class="skill-icon">
                    <?php else: ?>
                        <div class="skill-icon-placeholder"></div>
                    <?php endif; ?>
                </div>
                <div class="skill-details">
                    <div class="skill-name"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                    <div class="skill-description">
                        <?php echo !empty($skill['skill_description']) ? htmlspecialchars($skill['skill_description']) : '（スキルの説明が登録されていません）'; ?>
                    </div>
                    <div class="skill-meta">
                        <span class="skill-tag"><?php echo htmlspecialchars($skill['skill_type']); ?></span>
                        <span class="score-badge">相性: <?php echo $skill['affinity_score']; ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php
}
$html_output = ob_get_clean();

header('Content-Type: application/json');
echo json_encode(['html' => $html_output]);

} catch (Exception $e) {
    // エラーログに記録（デバッグ用）
    error_log("Search skill affinity error: " . $e->getMessage());
    
    // JSONエラーレスポンスを返す
    header('Content-Type: application/json');
    echo json_encode([
        'html' => '<div class="error-message"><h3>エラーが発生しました</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>'
    ]);
}
?>
<?php
// エラー表示を無効にしてJSONレスポンスを保護
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// ========== データベース接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { 
        throw new Exception('データベース接続エラー');
    }
    $conn->set_charset("utf8mb4");

    $character_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$character_id) {
        echo json_encode(['error' => 'IDが指定されていません。']);
        exit;
    }

// charactersテーブルから適性をすべて取得
$stmt = $conn->prepare("SELECT 
    surface_aptitude_turf, surface_aptitude_dirt, 
    distance_aptitude_short, distance_aptitude_mile, distance_aptitude_medium, distance_aptitude_long,
    strategy_aptitude_runner, strategy_aptitude_leader, strategy_aptitude_chaser, strategy_aptitude_trailer 
    FROM characters WHERE id = ?");
$stmt->bind_param("i", $character_id);
$stmt->execute();
$result = $stmt->get_result();
$character = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($character) {
    // 最も適性が高い距離と脚質を自動で判定する
    $distance_aptitudes = [
        '短距離' => $character['distance_aptitude_short'],
        'マイル' => $character['distance_aptitude_mile'],
        '中距離' => $character['distance_aptitude_medium'],
        '長距離' => $character['distance_aptitude_long'],
    ];
    $strategy_aptitudes = [
        '逃げ' => $character['strategy_aptitude_runner'],
        '先行' => $character['strategy_aptitude_leader'],
        '差し' => $character['strategy_aptitude_chaser'],
        '追込' => $character['strategy_aptitude_trailer'],
    ];

    // 'S' > 'A' > 'B' ... の順でソート（降順）
    function compare_aptitude($a, $b) {
        $rank_order = ['S' => 5, 'A' => 4, 'B' => 3, 'C' => 2, 'G' => 1];
        $score_a = $rank_order[$a] ?? 0;
        $score_b = $rank_order[$b] ?? 0;
        return $score_b - $score_a; // 降順（Sが最高）
    }
    
    uasort($distance_aptitudes, 'compare_aptitude');
    uasort($strategy_aptitudes, 'compare_aptitude');

    // 適性が最も高いものを取得
    $best_distance = key($distance_aptitudes);
    $best_strategy = key($strategy_aptitudes);

    echo json_encode([
        'success' => true,
        'best_distance' => $best_distance,
        'best_strategy' => $best_strategy,
    ]);
} else {
    echo json_encode(['error' => 'キャラクターが見つかりません。']);
}

} catch (Exception $e) {
    // エラーログに記録（デバッグ用）
    error_log("Character details error: " . $e->getMessage());
    
    // JSONエラーレスポンスを返す
    echo json_encode([
        'error' => 'サーバーエラーが発生しました。',
        'details' => $e->getMessage()
    ]);
}
?>
<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 安全な画像削除関数
function safe_delete_image($image_path) {
    if (empty($image_path)) return false;
    
    // パスの正規化と検証
    $real_path = realpath($image_path);
    $uploads_dir = realpath(__DIR__ . '/../uploads');
    
    // uploadsディレクトリ内かつファイルが存在することを確認
    if ($real_path && $uploads_dir && strpos($real_path, $uploads_dir) === 0 && is_file($real_path)) {
        return unlink($real_path);
    }
    return false;
}

// トランザクションを開始して、全ての削除処理を安全に行う
$conn->begin_transaction();

try {
    // --- 1. 画像ファイルがあれば削除 ---
    $stmt_select = $conn->prepare("SELECT image_url FROM support_cards WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    if ($card = $result->fetch_assoc()) {
        safe_delete_image($card['image_url']);
    }
    $stmt_select->close();

    // --- 2. 性能データを削除 (`card_effects`) ---
    $stmt_effects = $conn->prepare("DELETE FROM card_effects WHERE support_card_id = ?");
    $stmt_effects->bind_param("i", $id);
    $stmt_effects->execute();
    $stmt_effects->close();

    // --- 3. スキル紐付けデータを削除 (`support_card_skills`) ---
    $stmt_skills = $conn->prepare("DELETE FROM support_card_skills WHERE support_card_id = ?");
    $stmt_skills->bind_param("i", $id);
    $stmt_skills->execute();
    $stmt_skills->close();

    // --- 4. サポートカード本体を削除 (`support_cards`) ---
    $stmt_card = $conn->prepare("DELETE FROM support_cards WHERE id = ?");
    $stmt_card->bind_param("i", $id);
    $stmt_card->execute();
    $stmt_card->close();

    // 全ての処理が成功したら確定
    $conn->commit();

} catch (Exception $e) {
    // エラーが発生したら全て元に戻す
    $conn->rollback();
    die("削除処理中にエラーが発生しました: " . $e->getMessage());
}

$conn->close();
header("Location: index.php");
exit;
?>
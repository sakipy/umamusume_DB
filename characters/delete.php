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
    
    // 相対パスの場合は絶対パスに変換
    if (strpos($image_path, 'uploads/') === 0) {
        $full_path = '../' . $image_path;
    } else {
        $full_path = $image_path;
    }
    
    // ファイルが存在し、uploadsディレクトリ内にあることを確認
    if (file_exists($full_path) && strpos(realpath($full_path), realpath('../uploads/')) === 0) {
        if (unlink($full_path)) {
            echo "<!-- 画像削除成功: {$full_path} -->";
            return true;
        } else {
            echo "<!-- 画像削除失敗: {$full_path} -->";
            return false;
        }
    }
    return false;
}

// トランザクションを開始して、全ての削除処理を安全に行う
$conn->begin_transaction();

try {
    // --- 1. 画像ファイルがあれば削除（両方の画像カラムをチェック） ---
    $stmt_select = $conn->prepare("SELECT image_url, image_url_suit FROM characters WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    
    if ($char = $result->fetch_assoc()) {
        // image_url（通常画像）の削除
        safe_delete_image($char['image_url']);
        
        // image_url_suit（勝負服画像）の削除
        safe_delete_image($char['image_url_suit']);
    }
    $stmt_select->close();

    // --- 2. スキル紐付けデータを削除 (`character_skills`) ---
    $stmt_skills = $conn->prepare("DELETE FROM character_skills WHERE character_id = ?");
    $stmt_skills->bind_param("i", $id);
    $stmt_skills->execute();
    $stmt_skills->close();

    // --- 3. キャラクター本体を削除 (`characters`) ---
    $stmt_char = $conn->prepare("DELETE FROM characters WHERE id = ?");
    $stmt_char->bind_param("i", $id);
    $stmt_char->execute();
    $stmt_char->close();

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
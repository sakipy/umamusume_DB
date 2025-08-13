<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$conn->begin_transaction();
try {
    // 画像パスを取得してファイルを削除
    $stmt_select = $conn->prepare("SELECT screenshot_url FROM trained_umamusume WHERE id = ?");
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['screenshot_url']) && file_exists('../' . $row['screenshot_url'])) {
            unlink('../' . $row['screenshot_url']);
        }
    }
    $stmt_select->close();

    // スキル紐付けを削除
    $stmt_skills = $conn->prepare("DELETE FROM trained_umamusume_skills WHERE trained_umamusume_id = ?");
    $stmt_skills->bind_param("i", $id);
    $stmt_skills->execute();
    $stmt_skills->close();

    // 本体を削除
    $stmt_delete = $conn->prepare("DELETE FROM trained_umamusume WHERE id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("削除処理中にエラーが発生しました: " . $e->getMessage());
}

$conn->close();
header("Location: index.php");
exit;
?>
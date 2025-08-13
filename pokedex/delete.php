<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 最初に画像パスを全て取得
$stmt_select = $conn->prepare("SELECT winning_outfit_image_url, face_image_url, uniform_image_url FROM pokedex WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($row = $result->fetch_assoc()) {
    // 画像ファイルが存在すれば削除
    if (!empty($row['winning_outfit_image_url']) && file_exists('../' . $row['winning_outfit_image_url'])) {
        unlink('../' . $row['winning_outfit_image_url']);
    }
    if (!empty($row['face_image_url']) && file_exists('../' . $row['face_image_url'])) {
        unlink('../' . $row['face_image_url']);
    }
    if (!empty($row['uniform_image_url']) && file_exists('../' . $row['uniform_image_url'])) {
        unlink('../' . $row['uniform_image_url']);
    }
}
$stmt_select->close();

// DBからレコードを削除
$stmt_delete = $conn->prepare("DELETE FROM pokedex WHERE id = ?");
$stmt_delete->bind_param("i", $id);
$stmt_delete->execute();
$stmt_delete->close();

$conn->close();
header("Location: index.php");
exit;
?>
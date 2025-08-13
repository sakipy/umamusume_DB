<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 先に画像パスを取得して、ファイルが存在すれば削除
$stmt_select = $conn->prepare("SELECT image_url FROM races WHERE id = ?");
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($row = $result->fetch_assoc()) {
    if (!empty($row['image_url']) && file_exists('../' . $row['image_url'])) {
        unlink('../' . $row['image_url']);
    }
}
$stmt_select->close();

// DBからレコードを削除
$stmt = $conn->prepare("DELETE FROM races WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: index.php");
exit;
?>
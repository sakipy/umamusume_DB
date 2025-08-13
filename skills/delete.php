<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 先に関連付けデータを削除（もしあれば）
$stmt_link = $conn->prepare("DELETE FROM support_card_skills WHERE skill_id = ?");
$stmt_link->bind_param("i", $id);
$stmt_link->execute();
$stmt_link->close();

// スキル本体を削除
$stmt = $conn->prepare("DELETE FROM skills WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$conn->close();
header("Location: index.php");
exit;
?>
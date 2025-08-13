<?php
// ========== ページ設定 ==========
$page_title = 'システム設定';
$current_page = 'settings'; // 新しい識別子
$base_path = '../';
$message = '';

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- POSTリクエスト（設定が変更された）の場合の処理 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $edit_mode_value = isset($_POST['edit_mode_enabled']) ? '1' : '0';
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'edit_mode_enabled'");
    $stmt->bind_param("s", $edit_mode_value);
    if ($stmt->execute()) {
        $message = "設定を更新しました。";
    } else {
        $message = "設定の更新に失敗しました。";
    }
    $stmt->close();
}

// --- 現在の設定値を取得 ---
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result && $result->fetch_assoc()['setting_value'] == 1);
$conn->close();

include '../templates/header.php';
?>
<div class="container">
    <h1>システム設定</h1>
    <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

    <form action="index.php" method="POST" class="settings-form">
        <div class="form-group setting-item">
            <div class="setting-label">
                <label for="edit_mode_enabled">編集・削除モード</label>
                <p>サイト全体の編集および削除ボタンの表示／非表示を切り替えます。</p>
            </div>
            <div class="toggle-switch">
                <input type="checkbox" id="edit_mode_enabled" name="edit_mode_enabled" value="1" <?php if ($edit_mode_enabled) echo 'checked'; ?>>
                <label for="edit_mode_enabled"></label>
            </div>
        </div>
        <button type="submit">設定を保存</button>
    </form>
</div>

<style>
    .settings-form { margin-top: 30px; }
    .setting-item { display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 20px; border-radius: 8px; }
    .setting-label p { margin: 5px 0 0; color: #666; font-size: 0.9em; }
    .toggle-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-switch label { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .toggle-switch label:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    .toggle-switch input:checked + label { background-color: var(--primary-color); }
    .toggle-switch input:checked + label:before { transform: translateX(26px); }
</style>

<?php include '../templates/footer.php'; ?>
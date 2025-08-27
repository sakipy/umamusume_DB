<?php
// ========== ページ設定 ==========
$page_title = 'ホームページ画像管理';
$current_page = 'homepage_settings'; // 新しい識別子
$base_path = '../';
$message = '';
$error_message = '';

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- POSTリクエスト（画像がアップロードされた）の場合の処理 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $current_image_url = $_POST['current_image_url'] ?? '';
    $is_skill_analyzer = isset($_POST['skill_analyzer_image']);

    if ($is_skill_analyzer && isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
        // スキル相性診断画像のアップロード処理
        $upload_dir = '../uploads/homepage/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $target_file = $upload_dir . 'skill_analyzer.png';
        if (move_uploaded_file($_FILES['new_image']['tmp_name'], $target_file)) {
            $message = "スキル相性診断の画像を更新しました。";
        } else {
            $error_message = "ファイルのアップロードに失敗しました。";
        }
    } else if ($item_id > 0 && isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
        $upload_dir = '../uploads/homepage/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_name = time() . '_' . basename($_FILES['new_image']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['new_image']['tmp_name'], $target_file)) {
            // DBに保存するパス
            $new_image_path = 'uploads/homepage/' . $file_name;

            // DBの画像パスを更新
            $stmt = $conn->prepare("UPDATE homepage_menu SET image_url = ? WHERE id = ?");
            $stmt->bind_param("si", $new_image_path, $item_id);
            if ($stmt->execute()) {
                $message = "画像を更新しました。";
                // 古い画像があれば削除
                if (!empty($current_image_url) && file_exists('../' . $current_image_url)) {
                     // デフォルト画像は削除しないようにする
                    if (strpos($current_image_url, 'uploads/homepage/') === 0) {
                        unlink('../' . $current_image_url);
                    }
                }
            } else {
                $error_message = "データベースの更新に失敗しました。";
            }
            $stmt->close();
        } else {
            $error_message = "ファイルのアップロードに失敗しました。";
        }
    } else {
        $error_message = "無効なリクエストです。";
    }
}

// --- 現在のメニュー項目を取得 ---
$menu_items = [];
$result = $conn->query("SELECT * FROM homepage_menu ORDER BY sort_order ASC");
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}
$conn->close();

include '../templates/header.php';
?>
<div class="container">
    <h1>ホームページ画像管理</h1>
    <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <p>トップページに表示される各メニューの画像を変更できます。</p>

    <div class="settings-grid">
        <?php foreach ($menu_items as $item): ?>
            <div class="setting-item-card">
                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                <div class="current-image-preview">
                    <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="現在の画像">
                    <?php else: ?>
                        <div class="no-image">画像なし</div>
                    <?php endif; ?>
                </div>
                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($item['image_url']); ?>">
                    <input type="file" name="new_image" required class="file-upload-input" id="file_<?php echo $item['id']; ?>" onchange="this.form.submit()">
                    <label for="file_<?php echo $item['id']; ?>" class="file-upload-label">画像を変更...</label>
                </form>
            </div>
        <?php endforeach; ?>
        <!-- スキル相性診断画像の管理カード -->
        <div class="setting-item-card">
            <h3>スキル相性診断</h3>
            <div class="current-image-preview">
                <?php $skill_analyzer_img = '../uploads/homepage/skill_analyzer.png'; ?>
                <?php if (file_exists($skill_analyzer_img)): ?>
                    <img src="<?php echo $skill_analyzer_img; ?>" alt="スキル相性診断画像">
                <?php else: ?>
                    <div class="no-image">画像なし</div>
                <?php endif; ?>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="skill_analyzer_image" value="1">
                <input type="file" name="new_image" required class="file-upload-input" id="file_skill_analyzer" onchange="this.form.submit()">
                <label for="file_skill_analyzer" class="file-upload-label">画像を変更...</label>
            </form>
        </div>
    </div>
</div>

<style>
    .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 24px; margin-top: 30px; }
    .setting-item-card { background: #f8f9fa; border: 1px solid #eee; border-radius: 8px; padding: 20px; text-align: center; }
    .setting-item-card h3 { margin-top: 0; }
    .current-image-preview { width: 100%; aspect-ratio: 1/1; margin-bottom: 15px; border-radius: 8px; overflow: hidden; background: #fff; }
    .current-image-preview img, .current-image-preview .no-image { width: 100%; height: 100%; object-fit: contain; }
</style>

<?php include '../templates/footer.php'; ?>
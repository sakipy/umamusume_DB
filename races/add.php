<?php
// ========== ページ設定 ==========
$page_title = '新しいレースを追加';
$current_page = 'races';
$base_path = '../';
$message = ''; 
$error_message = '';
$racecourses = [];

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- POSTリクエストの場合の処理 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // パラメータの受け取り
    $race_name = $_POST['race_name'] ?? '';
    $racecourse_id = (int)($_POST['racecourse_id'] ?? 0);
    $grade = $_POST['grade'] ?? '';
    $distance = (int)($_POST['distance'] ?? 0);
    $surface = $_POST['surface'] ?? '';
    $turning_direction = $_POST['turning_direction'] ?? '';
    $description = $_POST['description'] ?? '';
    $image_url = null;

    // 画像アップロード処理
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/races/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/races/' . $file_name; // 相対パスで保存
        } else {
            $error_message = "ファイルのアップロードに失敗しました。";
        }
    }

    if (empty($race_name) || $racecourse_id <= 0 || $distance <= 0) {
        $error_message = "レース名、競馬場、距離は必須項目です。";
    } elseif (empty($error_message)) {
        $stmt = $conn->prepare("INSERT INTO races (race_name, racecourse_id, grade, distance, surface, turning_direction, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissssss", $race_name, $racecourse_id, $grade, $distance, $surface, $turning_direction, $description, $image_url);
        
        if ($stmt->execute()) {
            $message = "新しいレース「" . htmlspecialchars($race_name) . "」を登録しました。";
        } else {
            $error_message = "登録に失敗しました: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- 競馬場リストを取得 ---
$result = $conn->query("SELECT id, name FROM racecourses ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $racecourses[] = $row;
}
$conn->close();

$grade_options = ['G1', 'G2', 'G3', 'OP', 'Pre-OP', 'L', 'Jpn1', 'Jpn2', 'Jpn3'];
$surface_options = ['芝', 'ダート'];
$turning_direction_options = ['右回り', '左回り', '直線'];
?>

<?php include '../templates/header.php'; ?>
<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add.php" method="POST" enctype="multipart/form-data">
        <div class="form-grid-2col">
            <div class="form-group">
                <label for="race_name">レース名:</label>
                <input type="text" id="race_name" name="race_name" required>
            </div>
            <div class="form-group">
                <label for="racecourse_id">競馬場:</label>
                <select id="racecourse_id" name="racecourse_id" required>
                    <option value="">選択してください</option>
                    <?php foreach ($racecourses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="grade">グレード:</label>
                <select id="grade" name="grade">
                    <option value="">なし</option>
                    <?php foreach ($grade_options as $grade): ?><option value="<?php echo $grade; ?>"><?php echo $grade; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="distance">距離 (m):</label>
                <input type="number" id="distance" name="distance" required>
            </div>
             <div class="form-group">
                <label for="surface">バ場:</label>
                <select id="surface" name="surface">
                    <?php foreach ($surface_options as $surface): ?><option value="<?php echo $surface; ?>"><?php echo $surface; ?></option><?php endforeach; ?>
                </select>
            </div>
             <div class="form-group">
                <label for="turning_direction">回り:</label>
                <select id="turning_direction" name="turning_direction">
                    <?php foreach ($turning_direction_options as $direction): ?><option value="<?php echo $direction; ?>"><?php echo $direction; ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>画像:</label>
            <div class="image-preview-wrapper" style="width: 300px; aspect-ratio: 16/9;">
                <img id="image_preview" style="display:none;"><span>プレビュー</span>
            </div>
            <label for="image" class="file-upload-label" style="width: 300px; margin-top: 10px;">ファイルを選択...</label>
            <input type="file" id="image" name="image" class="file-upload-input">
        </div>
        <div class="form-group">
            <label for="description">説明・備考:</label>
            <textarea id="description" name="description" rows="4"></textarea>
        </div>
        <button type="submit">この内容で登録する</button>
    </form>
    <a href="index.php" class="back-link">&laquo; レース一覧に戻る</a>
</div>

<script>
document.getElementById('image').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const preview = document.getElementById('image_preview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
        preview.nextElementSibling.style.display = 'none';
    }
});
</script>

<?php include '../templates/footer.php'; ?>
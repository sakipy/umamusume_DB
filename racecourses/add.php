<?php
$page_title = '競馬場追加';
$current_page = 'racecourses';
$base_path = '../';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$message = ''; $error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");

    // 送信された配列データをカンマ区切りの文字列に変換
    $turning_direction = isset($_POST['turning_direction']) ? implode(',', $_POST['turning_direction']) : '';
    $course_type = isset($_POST['course_type']) ? implode(',', $_POST['course_type']) : '';
    $surface = isset($_POST['surface']) ? implode(',', $_POST['surface']) : '';

    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $image_url = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/racecourses/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = $target_file;
        } else { $error_message = "ファイルのアップロードに失敗しました。"; }
    }

    if (empty($name)) {
        $error_message = "競馬場名は必須です。";
    } elseif (empty($error_message)) {
        // 新しいカラムを追加したINSERT文
        $stmt = $conn->prepare("INSERT INTO racecourses (name, location, turning_direction, course_type, surface, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $location, $turning_direction, $course_type, $surface, $description, $image_url);
        
        if ($stmt->execute()) {
            $message = "新しい競馬場「" . htmlspecialchars($name) . "」を登録しました。";
        } else { $error_message = "登録に失敗しました: " . $stmt->error; }
        $stmt->close();
    }
    $conn->close();
}
?>

<?php include '../templates/header.php'; ?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>競馬場追加</title>
    <link rel="stylesheet" href="../common_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>新しい競馬場を登録</h1>
        <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <form action="add.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">競馬場名:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="location">所在地:</label>
                <input type="text" id="location" name="location">
            </div>

            <div class="form-group">
                <label>回り方向:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="dir_right" name="turning_direction[]" value="右回り"><label for="dir_right">右回り</label>
                    <input type="checkbox" id="dir_left" name="turning_direction[]" value="左回り"><label for="dir_left">左回り</label>
                </div>
            </div>
            <div class="form-group">
                <label>コース種別:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="course_outer" name="course_type[]" value="外回り"><label for="course_outer">外回り</label>
                    <input type="checkbox" id="course_inner" name="course_type[]" value="内回り"><label for="course_inner">内回り</label>
                </div>
            </div>
            <div class="form-group">
                <label>馬場種別:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="surface_turf" name="surface[]" value="芝"><label for="surface_turf">芝</label>
                    <input type="checkbox" id="surface_dirt" name="surface[]" value="ダート"><label for="surface_dirt">ダート</label>
                </div>
            </div>
            <div class="form-group">
                <label>画像:</label>
                <div id="image-preview-wrapper" class="image-preview-wrapper" style="width: 400px; height: 185px;">
                    <img id="image_preview" src="" alt="画像プレビュー" style="display: none;">
                    <span>プレビュー (800x370)</span>
                </div>
                <div style="margin-top: 10px;">
                    <label for="image" class="file-upload-label">ファイルを選択...</label>
                    <input type="file" id="image" name="image" class="file-upload-input" accept="image/jpeg, image/png, image/webp">
                    <span id="file-name-display">選択されていません</span>
                </div>
            </div>
            <div class="form-group">
                <label for="description">説明:</label>
                <textarea id="description" name="description" rows="5"></textarea>
            </div>
            <button type="submit">登録する</button>
        </form>
        <a href="index.php" class="back-link">&laquo; 競馬場一覧に戻る</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('image');
        const fileNameDisplay = document.getElementById('file-name-display');
        const imagePreview = document.getElementById('image_preview');
        const previewPlaceholder = document.querySelector('#image-preview-wrapper span');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileNameDisplay.textContent = file.name;
                imagePreview.src = URL.createObjectURL(file);
                imagePreview.style.display = 'block';
                if(previewPlaceholder) previewPlaceholder.style.display = 'none';
            } else {
                fileNameDisplay.textContent = '選択されていません';
                imagePreview.src = '';
                imagePreview.style.display = 'none';
                if(previewPlaceholder) previewPlaceholder.style.display = 'block';
            }
        });
    });
    </script>
</body>
</html>

<?php include '../templates/footer.php'; ?>
<?php
$page_title = '競馬場編集';
$current_page = 'racecourses';
$base_path = '../';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$error_message = ''; $racecourse = null;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $image_url = $_POST['current_image_url'];

    // 送信された配列データをカンマ区切りの文字列に変換
    $turning_direction = isset($_POST['turning_direction']) ? implode(',', $_POST['turning_direction']) : '';
    $course_type = isset($_POST['course_type']) ? implode(',', $_POST['course_type']) : '';
    $surface = isset($_POST['surface']) ? implode(',', $_POST['surface']) : '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/racecourses/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            if (!empty($image_url) && file_exists($image_url)) { unlink($image_url); }
            $image_url = $target_file;
        } else { $error_message = "ファイルのアップロードに失敗しました。"; }
    }

    if (empty($name)) {
        $error_message = "競馬場名は必須です。";
    } elseif (empty($error_message)) {
        // 新しいカラムを追加したUPDATE文
        $stmt = $conn->prepare("UPDATE racecourses SET name = ?, location = ?, turning_direction = ?, course_type = ?, surface = ?, description = ?, image_url = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $name, $location, $turning_direction, $course_type, $surface, $description, $image_url, $id);
        
        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        } else { $error_message = "更新に失敗しました: " . $stmt->error; }
        $stmt->close();
    }
}

$racecourse_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($racecourse_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM racecourses WHERE id = ?");
    $stmt->bind_param("i", $racecourse_id);
    $stmt->execute();
    $racecourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$racecourse) { die("競馬場が見つかりません。"); }
$conn->close();
?>

<?php include '../templates/header.php'; ?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>競馬場編集</title>
    <link rel="stylesheet" href="../common_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>競馬場情報を編集</h1>
        <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>
        <?php if ($racecourse): ?>
        <form action="edit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($racecourse['id']); ?>">
            <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($racecourse['image_url']); ?>">
            <div class="form-group">
                <label for="name">競馬場名:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($racecourse['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="location">所在地:</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($racecourse['location']); ?>">
            </div>

            <?php
                // DBの値を配列に変換
                $saved_directions = !empty($racecourse['turning_direction']) ? explode(',', $racecourse['turning_direction']) : [];
                $saved_courses = !empty($racecourse['course_type']) ? explode(',', $racecourse['course_type']) : [];
                $saved_surfaces = !empty($racecourse['surface']) ? explode(',', $racecourse['surface']) : [];
            ?>
            <div class="form-group">
                <label>回り方向:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="dir_right" name="turning_direction[]" value="右回り" <?php if(in_array('右回り', $saved_directions)) echo 'checked'; ?>><label for="dir_right">右回り</label>
                    <input type="checkbox" id="dir_left" name="turning_direction[]" value="左回り" <?php if(in_array('左回り', $saved_directions)) echo 'checked'; ?>><label for="dir_left">左回り</label>
                </div>
            </div>
            <div class="form-group">
                <label>コース種別:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="course_outer" name="course_type[]" value="外回り" <?php if(in_array('外回り', $saved_courses)) echo 'checked'; ?>><label for="course_outer">外回り</label>
                    <input type="checkbox" id="course_inner" name="course_type[]" value="内回り" <?php if(in_array('内回り', $saved_courses)) echo 'checked'; ?>><label for="course_inner">内回り</label>
                </div>
            </div>
            <div class="form-group">
                <label>馬場種別:</label>
                <div class="toggle-checkbox-group">
                    <input type="checkbox" id="surface_turf" name="surface[]" value="芝" <?php if(in_array('芝', $saved_surfaces)) echo 'checked'; ?>><label for="surface_turf">芝</label>
                    <input type="checkbox" id="surface_dirt" name="surface[]" value="ダート" <?php if(in_array('ダート', $saved_surfaces)) echo 'checked'; ?>><label for="surface_dirt">ダート</label>
                </div>
            </div>
            <div class="form-group">
                <label>画像:</label>
                <div id="image-preview-wrapper" class="image-preview-wrapper" style="width: 400px; height: 185px;">
                    <img id="image_preview" src="<?php echo htmlspecialchars($racecourse['image_url']); ?>" alt="画像プレビュー">
                </div>
                <div style="margin-top: 10px;">
                    <label for="image" class="file-upload-label">ファイルを選択して変更...</label>
                    <input type="file" id="image" name="image" class="file-upload-input" accept="image/jpeg, image/png, image/webp">
                    <span id="file-name-display">現在の画像: <?php echo basename($racecourse['image_url']); ?></span>
                </div>
            </div>
            <div class="form-group">
                <label for="description">説明:</label>
                <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($racecourse['description']); ?></textarea>
            </div>
            <button type="submit">更新する</button>
        </form>
        <a href="index.php" class="back-link">&laquo; 競馬場一覧に戻る</a>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('image');
        const fileNameDisplay = document.getElementById('file-name-display');
        const imagePreview = document.getElementById('image_preview');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileNameDisplay.textContent = file.name;
                imagePreview.src = URL.createObjectURL(file);
            }
        });
    });
    </script>
</body>
</html>

<?php include '../templates/footer.php'; ?>
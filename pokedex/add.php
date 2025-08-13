<?php
// ========== ページ設定 ==========
$page_title = '図鑑に新しいデータを追加';
$current_page = 'pokedex';
$base_path = '../';
$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");

    try {
        // --- IDの重複チェック ---
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("図鑑No.は必須です。");
        }
        $stmt_check = $conn->prepare("SELECT id FROM pokedex WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("指定された図鑑No.「{$id}」は既に使用されています。");
        }
        $stmt_check->close();

        // --- Helper function for file upload ---
        function upload_pokedex_image($file_key) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                $upload_dir = '../uploads/pokedex/';
                if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $file_name = time() . '_' . $file_key . '_' . basename($_FILES[$file_key]['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                    return 'uploads/pokedex/' . $file_name;
                } else {
                    throw new Exception( "「{$file_key}」のアップロードに失敗しました。");
                }
            }
            return null;
        }
        
        $winning_outfit_image_url = upload_pokedex_image('winning_outfit_image');
        $face_image_url = upload_pokedex_image('face_image');
        $uniform_image_url = upload_pokedex_image('uniform_image');

        // `pokedex` テーブルへのINSERT
        $stmt = $conn->prepare(
            "INSERT INTO pokedex (pokedex_name, category, cv, birthday, height, weight, three_sizes, description, winning_outfit_image_url, face_image_url, uniform_image_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssssssss", 
            $_POST['pokedex_name'],
            $_POST['category'],
            $_POST['cv'],
            $_POST['birthday'],
            $_POST['height'],
            $_POST['weight'],
            $_POST['three_sizes'],
            $_POST['description'],
            $winning_outfit_image_url,
            $face_image_url,
            $uniform_image_url
        );
        
        if ($stmt->execute()) {
            $message = "新しいデータ「" . htmlspecialchars($_POST['pokedex_name']) . "」を登録しました。";
        } else {
            throw new Exception("登録に失敗しました: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    $conn->close();
}
include '../templates/header.php';
?>

<div class="container full-width">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add.php" method="POST" enctype="multipart/form-data">
        <div class="pokedex-form-grid">
            <div class="form-col-main">
                <div class="form-group">
                    <label for="id">図鑑No.:</label>
                    <input type="number" id="id" name="id" required>
                </div>
                <div class="form-group">
                    <label for="pokedex_name">名前:</label>
                    <input type="text" id="pokedex_name" name="pokedex_name" required>
                </div>
                <div class="form-group">
                    <label for="category">分類:</label>
                    <select id="category" name="category">
                        <option value="実装済み">実装済み</option>
                        <option value="未実装">未実装</option>
                        <option value="トレセン関係者">トレセン関係者</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cv">CV:</label>
                    <input type="text" id="cv" name="cv">
                </div>
                <div class="form-group">
                    <label for="description">説明文:</label>
                    <textarea id="description" name="description" rows="10"></textarea>
                </div>
            </div>
            <div class="form-col-details">
                 <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="birthday">誕生日:</label>
                        <input type="text" id="birthday" name="birthday">
                    </div>
                    <div class="form-group">
                        <label for="height">身長:</label>
                        <input type="text" id="height" name="height">
                    </div>
                 </div>
                 <div class="form-group">
                    <label for="weight">体重:</label>
                    <input type="text" id="weight" name="weight">
                </div>
                 <div class="form-group">
                    <label for="three_sizes">スリーサイズ:</label>
                    <input type="text" id="three_sizes" name="three_sizes">
                </div>

                <hr style="margin: 20px 0;">

                <div class="form-grid-3col">
                    <div class="form-group">
                        <label>勝負服画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 2/3;">
                            <img id="winning_outfit_preview" style="display:none;"><span>プレビュー</span>
                        </div>
                        <label for="winning_outfit_image" class="file-upload-label">選択...</label>
                        <input type="file" id="winning_outfit_image" name="winning_outfit_image" class="file-upload-input">
                    </div>
                    <div class="form-group">
                        <label>制服画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 2/3;">
                             <img id="uniform_preview" style="display:none;"><span>プレビュー</span>
                        </div>
                        <label for="uniform_image" class="file-upload-label">選択...</label>
                        <input type="file" id="uniform_image" name="uniform_image" class="file-upload-input">
                    </div>
                    <div class="form-group">
                        <label>顔画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 1/1;">
                             <img id="face_preview" style="display:none;"><span>プレビュー</span>
                        </div>
                        <label for="face_image" class="file-upload-label">選択...</label>
                        <input type="file" id="face_image" name="face_image" class="file-upload-input">
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" style="margin-top: 24px;">この内容で登録する</button>
    </form>
    <a href="index.php" class="back-link">&laquo; 図鑑一覧に戻る</a>
</div>

<script>
function setupImagePreview(inputId, imgId) {
    const fileInput = document.getElementById(inputId);
    const imagePreview = document.getElementById(imgId);
    const placeholder = imagePreview.nextElementSibling;
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            imagePreview.src = URL.createObjectURL(this.files[0]);
            imagePreview.style.display = 'block';
            if(placeholder) placeholder.style.display = 'none';
        }
    });
}
setupImagePreview('winning_outfit_image', 'winning_outfit_preview');
setupImagePreview('uniform_image', 'uniform_preview');
setupImagePreview('face_image', 'face_preview');
</script>

<?php include '../templates/footer.php'; ?>
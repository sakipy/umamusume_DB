<?php
// ========== ページ設定 ==========
$page_title = '図鑑データ編集';
$current_page = 'pokedex';
$base_path = '../';
$error_message = ''; // エラーメッセージ用変数を初期化

// ========== DB接続設定 ==========
$db_host = 'localhost'; 
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'umamusume_db';

// ▼▼▼【ここから更新処理ブロックを完全に差し替え】▼▼▼
// ========== POSTリクエスト（フォーム送信）処理 ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // フォームから送信されたデータを取得
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $pokedex_name = $_POST['pokedex_name'] ?? '';
    $category = $_POST['category'] ?? '実装済み';
    $cv = $_POST['cv'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $height = $_POST['height'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $three_sizes = $_POST['three_sizes'] ?? '';
    $description = $_POST['description'] ?? '';

    // IDが不正なら終了
    if (!$id) { die("無効なIDです。"); }

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
    $conn->set_charset("utf8mb4");

    // ファイルアップロード処理用の関数
    function handle_image_upload($file_key, $current_path, $base_path) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $base_path . 'uploads/pokedex/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = uniqid() . '_' . basename($_FILES[$file_key]['name']);
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_path)) {
                if ($current_path && file_exists($base_path . $current_path)) {
                    unlink($base_path . $current_path);
                }
                return 'uploads/pokedex/' . $filename;
            }
        }
        return $current_path;
    }

    // 各画像のアップロードを処理
    // <input type="hidden"> から現在のパスを取得
    $winning_outfit_image_url = handle_image_upload('winning_outfit_image', $_POST['current_winning_outfit_image_url'], $base_path);
    $uniform_image_url = handle_image_upload('uniform_image', $_POST['current_uniform_image_url'], $base_path);
    $face_image_url = handle_image_upload('face_image', $_POST['current_face_image_url'], $base_path);
    
    // 安全なUPDATE文（プリペアドステートメント）を準備
    $sql = "UPDATE pokedex SET 
                pokedex_name = ?, category = ?, cv = ?, birthday = ?, height = ?, 
                weight = ?, three_sizes = ?, winning_outfit_image_url = ?, 
                face_image_url = ?, uniform_image_url = ?, description = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssi", 
        $pokedex_name, $category, $cv, $birthday, $height, $weight, 
        $three_sizes, $winning_outfit_image_url, $face_image_url, 
        $uniform_image_url, $description, $id
    );

    if ($stmt->execute()) {
        header("Location: view.php?id=" . $id);
        exit;
    } else {
        $error_message = "データベースの更新に失敗しました: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}
// ▲▲▲【更新処理ブロックの差し替えここまで】▲▲▲

// ========== GETリクエスト（編集フォーム表示）処理 ==========
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// pokedexテーブルのエイリアスpをcharactersテーブルのcに変更
$stmt = $conn->prepare("SELECT * FROM pokedex WHERE id = ?"); 
$stmt->bind_param("i", $id);
$stmt->execute();
$character = $stmt->get_result()->fetch_assoc(); // 変数名を$entryから$characterに変更
$stmt->close();
$conn->close();

if (!$character) { die("指定されたデータが見つかりません。"); }
// $entryを$characterに変更
$page_title = '図鑑編集: ' . htmlspecialchars($character['pokedex_name']); 

include '../templates/header.php';
?>

<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="edit.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $character['id']; ?>">
        <input type="hidden" name="current_face_image_url" value="<?php echo htmlspecialchars($character['face_image_url']); ?>">
        <input type="hidden" name="current_winning_outfit_image_url" value="<?php echo htmlspecialchars($character['winning_outfit_image_url']); ?>">
        <input type="hidden" name="current_uniform_image_url" value="<?php echo htmlspecialchars($character['uniform_image_url']); ?>">

        <div class="form-group">
            <label for="pokedex_name">ウマ娘名:</label>
            <input type="text" id="pokedex_name" name="pokedex_name" value="<?php echo htmlspecialchars($character['pokedex_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="category">分類:</label>
            <select id="category" name="category">
                <option value="実装済み" <?php if($character['category'] == '実装済み') echo 'selected'; ?>>実装済み</option>
                <option value="未実装" <?php if($character['category'] == '未実装') echo 'selected'; ?>>未実装</option>
                <option value="トレセン関係者" <?php if($character['category'] == 'トレセン関係者') echo 'selected'; ?>>トレセン関係者</option>
            </select>
        </div>

        <div class="form-group">
            <label for="cv">CV:</label>
            <input type="text" id="cv" name="cv" value="<?php echo htmlspecialchars($character['cv']); ?>">
        </div>
        <div class="form-group">
            <label for="birthday">誕生日:</label>
            <input type="text" id="birthday" name="birthday" value="<?php echo htmlspecialchars($character['birthday']); ?>">
        </div>
        <div class="form-group">
            <label for="height">身長:</label>
            <input type="text" id="height" name="height" value="<?php echo htmlspecialchars($character['height']); ?>">
        </div>
        <div class="form-group">
            <label for="weight">体重:</label>
            <input type="text" id="weight" name="weight" value="<?php echo htmlspecialchars($character['weight']); ?>">
        </div>
        <div class="form-group">
            <label for="three_sizes">スリーサイズ:</label>
            <input type="text" id="three_sizes" name="three_sizes" value="<?php echo htmlspecialchars($character['three_sizes']); ?>">
        </div>
        <div class="form-group">
            <label for="description">紹介文:</label>
            <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($character['description']); ?></textarea>
        </div>
        
        <hr style="margin: 20px 0;">
                <div class="form-grid-3col">
                    <div class="form-group">
                        <label>勝負服画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 2/3;">
                            <img id="winning_outfit_preview" src="../<?php echo htmlspecialchars($character['winning_outfit_image_url']); ?>">
                        </div>
                        <label for="winning_outfit_image" class="file-upload-label">変更...</label>
                        <input type="file" id="winning_outfit_image" name="winning_outfit_image" class="file-upload-input">
                    </div>
                    <div class="form-group">
                        <label>制服画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 2/3;">
                             <img id="uniform_preview" src="../<?php echo htmlspecialchars($character['uniform_image_url']); ?>">
                        </div>
                        <label for="uniform_image" class="file-upload-label">変更...</label>
                        <input type="file" id="uniform_image" name="uniform_image" class="file-upload-input">
                    </div>
                    <div class="form-group">
                        <label>顔画像:</label>
                        <div class="image-preview-wrapper" style="aspect-ratio: 1/1;">
                             <img id="face_preview" src="../<?php echo htmlspecialchars($character['face_image_url']); ?>">
                        </div>
                        <label for="face_image" class="file-upload-label">変更...</label>
                        <input type="file" id="face_image" name="face_image" class="file-upload-input">
                    </div>
                </div>
        <button type="submit">この内容で更新する</button>
    </form>
    <a href="view.php?id=<?php echo $character['id']; ?>" class="back-link">&laquo; 詳細ページに戻る</a>
</div>
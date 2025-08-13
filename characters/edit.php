<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘編集';
$current_page = 'characters';
$base_path = '../';

// ========== 変数の初期化 ==========
$message = ''; $error_message = '';
$character = null; $all_skills = []; $current_skill_ids = [];

// ========== データベース接続 ==========
$conn = new mysqli('localhost', 'root', '', 'umamusume_db');
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// =================================================================
// POSTリクエスト（フォームが送信された）の場合の処理
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $original_id = (int)($_POST['original_id'] ?? 0);
    $new_id = (int)($_POST['id'] ?? 0);
    
    $conn->begin_transaction();
    try {
        if ($new_id !== $original_id) {
            if ($new_id <= 0) { throw new Exception("IDは必須です。"); }
            $stmt_check = $conn->prepare("SELECT id FROM characters WHERE id = ?");
            $stmt_check->bind_param("i", $new_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("指定されたID「{$new_id}」は既に使用されています。");
            }
            $stmt_check->close();
        }

        function update_image($file_key, $prefix, $current_url) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                $upload_dir = '../uploads/characters/';
                $file_name = time() . '_' . $prefix . '_' . basename($_FILES[$file_key]['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                    if (!empty($current_url) && file_exists('../' . $current_url)) {
                        unlink('../' . $current_url);
                    }
                    return 'uploads/characters/' . $file_name;
                } else {
                    throw new Exception(ucfirst($prefix) . "画像のアップロードに失敗しました。");
                }
            }
            return $current_url;
        }

        $image_url_suit = update_image('character_image_suit', 'suit', $_POST['current_image_url_suit']);
        
        $stmt_char = $conn->prepare(
            "UPDATE characters SET
                id=?, character_name=?, rarity=?, pokedex_id=?, image_url=?, image_url_suit=?,
                initial_speed=?, initial_stamina=?, initial_power=?, initial_guts=?, initial_wisdom=?,
                growth_rate_speed=?, growth_rate_stamina=?, growth_rate_power=?, growth_rate_guts=?, growth_rate_wisdom=?,
                surface_aptitude_turf=?, surface_aptitude_dirt=?,
                distance_aptitude_short=?, distance_aptitude_mile=?, distance_aptitude_medium=?, distance_aptitude_long=?,
                strategy_aptitude_runner=?, strategy_aptitude_leader=?, strategy_aptitude_chaser=?, strategy_aptitude_trailer=?
            WHERE id = ?"
        );

        $stmt_char->bind_param("isiissiiiiidddddssssssssssi",
            $new_id,
            $_POST['character_name'], $_POST['rarity'], $_POST['pokedex_id'], $image_url_suit, $image_url_suit,
            $_POST['initial_speed'], $_POST['initial_stamina'], $_POST['initial_power'], $_POST['initial_guts'], $_POST['initial_wisdom'],
            $_POST['growth_rate_speed'], $_POST['growth_rate_stamina'], $_POST['growth_rate_power'], $_POST['growth_rate_guts'], $_POST['growth_rate_wisdom'],
            $_POST['surface_aptitude_turf'], $_POST['surface_aptitude_dirt'],
            $_POST['distance_aptitude_short'], $_POST['distance_aptitude_mile'], $_POST['distance_aptitude_medium'], $_POST['distance_aptitude_long'],
            $_POST['strategy_aptitude_runner'], $_POST['strategy_aptitude_leader'], $_POST['strategy_aptitude_chaser'], $_POST['strategy_aptitude_trailer'],
            $original_id
        );
        $stmt_char->execute();
        $stmt_char->close();

        // スキル紐付けの更新 (DELETE -> INSERT)
        $conn->query("DELETE FROM character_skills WHERE character_id = $new_id");
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $stmt_skill = $conn->prepare("INSERT INTO character_skills (character_id, skill_id, unlock_condition) VALUES (?, ?, ?)");
            foreach ($_POST['skill_ids'] as $skill_id) {
                $unlock_condition = '初期';
                $stmt_skill->bind_param("iis", $new_id, $skill_id, $unlock_condition);
                $stmt_skill->execute();
            }
            $stmt_skill->close();
        }

        $conn->commit();
        header("Location: view.php?id=" . $new_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "エラー: " . $e->getMessage();
    }
}

// =================================================================
// GETリクエスト（ページ表示用）のデータ取得
// =================================================================
$character_id = (int)($_GET['id'] ?? $_POST['original_id'] ?? 0);
if ($character_id > 0) {
    $stmt_char = $conn->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt_char->bind_param("i", $character_id);
    $stmt_char->execute();
    $character = $stmt_char->get_result()->fetch_assoc();
    $stmt_char->close();

    $all_skills_result = $conn->query("SELECT id, skill_name, skill_type FROM skills ORDER BY skill_name ASC");
    while ($row = $all_skills_result->fetch_assoc()) { $all_skills[] = $row; }

    $current_skills_stmt = $conn->prepare("SELECT skill_id FROM character_skills WHERE character_id = ?");
    $current_skills_stmt->bind_param("i", $character_id);
    $current_skills_stmt->execute();
    $result = $current_skills_stmt->get_result();
    while ($row = $result->fetch_assoc()) { $current_skill_ids[] = $row['skill_id']; }
    $current_skills_stmt->close();
}
if (!$character) { die("ウマ娘が見つかりません。"); }
$conn->close();

$aptitude_options = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
?>
<?php include '../templates/header.php'; ?>

<div class="container full-width">
    <h1>ウマ娘情報を編集</h1>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="edit.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="original_id" value="<?php echo htmlspecialchars($character['id']); ?>">
        <input type="hidden" name="current_image_url_suit" value="<?php echo htmlspecialchars($character['image_url_suit']); ?>">
        
       <div class="character-form-grid">
            <div class="form-col-main">
                <div class="form-group">
                    <label>ID:</label>
                    <input type="number" name="id" value="<?php echo htmlspecialchars($character['id']); ?>" required>
                </div>
                <div class="form-group">
                    <label>ウマ娘名:</label>
                    <input type="text" name="character_name" value="<?php echo htmlspecialchars($character['character_name']); ?>" required>
                </div>
                 <div class="form-group">
                    <label>図鑑No. (紐付け):</label>
                    <input type="number" name="pokedex_id" value="<?php echo htmlspecialchars($character['pokedex_id']); ?>">
                </div>
                <div class="form-group">
                    <label>初期レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="1" <?php if($character['rarity'] == 1) echo 'selected'; ?>>★1</option>
                        <option value="2" <?php if($character['rarity'] == 2) echo 'selected'; ?>>★2</option>
                        <option value="3" <?php if($character['rarity'] == 3) echo 'selected'; ?>>★3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>勝負服画像:</label>
                    <div id="image-preview-suit" class="image-preview-wrapper">
                        <img id="image_preview_suit_img" src="../<?php echo htmlspecialchars($character['image_url_suit']); ?>">
                    </div>
                    <label for="character_image_suit" class="file-upload-label">ファイルを選択して変更...</label>
                    <input type="file" id="character_image_suit" name="character_image_suit" class="file-upload-input">
                </div>
            </div>

            <div class="form-col-details">
                 <h2 class="section-title-bar">初期ステータスと成長率</h2>
                <div class="status-growth-grid">
                    <div></div>
                    <div class="grid-header">スピード</div><div class="grid-header">スタミナ</div><div class="grid-header">パワー</div><div class="grid-header">根性</div><div class="grid-header">賢さ</div>
                    <div class="grid-label">初期ステータス</div>
                    <div><input type="number" name="initial_speed" value="<?php echo htmlspecialchars($character['initial_speed']); ?>"></div>
                    <div><input type="number" name="initial_stamina" value="<?php echo htmlspecialchars($character['initial_stamina']); ?>"></div>
                    <div><input type="number" name="initial_power" value="<?php echo htmlspecialchars($character['initial_power']); ?>"></div>
                    <div><input type="number" name="initial_guts" value="<?php echo htmlspecialchars($character['initial_guts']); ?>"></div>
                    <div><input type="number" name="initial_wisdom" value="<?php echo htmlspecialchars($character['initial_wisdom']); ?>"></div>
                    <div class="grid-label">成長率 (%)</div>
                    <div><input type="number" name="growth_rate_speed" value="<?php echo htmlspecialchars($character['growth_rate_speed']); ?>" step="10"></div>
                    <div><input type="number" name="growth_rate_stamina" value="<?php echo htmlspecialchars($character['growth_rate_stamina']); ?>" step="10"></div>
                    <div><input type="number" name="growth_rate_power" value="<?php echo htmlspecialchars($character['growth_rate_power']); ?>" step="10"></div>
                    <div><input type="number" name="growth_rate_guts" value="<?php echo htmlspecialchars($character['growth_rate_guts']); ?>" step="10"></div>
                    <div><input type="number" name="growth_rate_wisdom" value="<?php echo htmlspecialchars($character['growth_rate_wisdom']); ?>" step="10"></div>
                </div>

                <h2 class="section-title-bar">適性</h2>
                <div class="aptitude-grid">
                     <div class="form-group">
                        <label>バ場適性</label>
                        <div class="aptitude-row">
                            <span>芝</span><select name="surface_aptitude_turf"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['surface_aptitude_turf'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>ダート</span><select name="surface_aptitude_dirt"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['surface_aptitude_dirt'] ? 'selected' : '').">$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>距離適性</label>
                        <div class="aptitude-row">
                            <span>短距離</span><select name="distance_aptitude_short"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['distance_aptitude_short'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>マイル</span><select name="distance_aptitude_mile"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['distance_aptitude_mile'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>中距離</span><select name="distance_aptitude_medium"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['distance_aptitude_medium'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>長距離</span><select name="distance_aptitude_long"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['distance_aptitude_long'] ? 'selected' : '').">$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>脚質適性</label>
                        <div class="aptitude-row">
                            <span>逃げ</span><select name="strategy_aptitude_runner"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['strategy_aptitude_runner'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>先行</span><select name="strategy_aptitude_leader"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['strategy_aptitude_leader'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>差し</span><select name="strategy_aptitude_chaser"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['strategy_aptitude_chaser'] ? 'selected' : '').">$op</option>"; ?></select>
                            <span>追込</span><select name="strategy_aptitude_trailer"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == $character['strategy_aptitude_trailer'] ? 'selected' : '').">$op</option>"; ?></select>
                        </div>
                    </div>
                </div>

                <h2 class="section-title-bar">所持スキル</h2>
                <div class="skill-selection-area">
                    <div class="skill-list-wrapper" style="max-height: 400px;">
                        <ul class="skill-list grid-layout" id="skill-list-container">
                            <?php foreach ($all_skills as $skill): ?>
                                <li <?php if(in_array($skill['id'], $current_skill_ids)) echo 'class="selected"'; ?>>
                                    <label>
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" <?php if(in_array($skill['id'], $current_skill_ids)) echo 'checked'; ?>>
                                        <?php
                                            $text_class = '';
                                            if ($skill['skill_type'] == 'レアスキル') { $text_class = 'text-rare'; }
                                            elseif ($skill['skill_type'] == '進化スキル') { $text_class = 'text-evolution'; }
                                            elseif ($skill['skill_type'] == '固有スキル') { $text_class = 'text-rainbow'; }
                                        ?>
                                        <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="submit">この内容で更新する</button>
            </div>
        </div>
    </form>
    <a href="view.php?id=<?php echo $character['id']; ?>" class="back-link" style="margin-top: 24px;">&laquo; 詳細ページに戻る</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- スキルリストの選択機能 ---
    document.getElementById('skill-list-container').addEventListener('change', e => {
        if (e.target.type === 'checkbox') e.target.closest('li').classList.toggle('selected', e.target.checked);
    });

    // --- 画像プレビュー汎用関数 ---
    function setupImagePreview(inputId, wrapperId, imgId) {
        const fileInput = document.getElementById(inputId);
        const imagePreview = document.getElementById(imgId);
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                imagePreview.src = URL.createObjectURL(this.files[0]);
            }
        });
    }

    // --- 各画像プレビューをセットアップ ---
    setupImagePreview('character_image_suit', 'image-preview-suit', 'image_preview_suit_img');
    setupImagePreview('character_image_uniform', 'image-preview-uniform', 'image_preview_uniform_img');
    setupImagePreview('face_image', 'face-image-preview', 'face_image_preview_img');

    // --- レアリティと枠色の連動 ---
    const raritySelect = document.getElementById('rarity_select');
    function updateBorderColor() {
        const rarity = raritySelect.value;
        document.getElementById('image-preview-suit').className = 'image-preview-wrapper rarity-' + rarity;
        document.getElementById('image-preview-uniform').className = 'image-preview-wrapper rarity-' + rarity;
    }
    raritySelect.addEventListener('change', updateBorderColor);
    updateBorderColor(); // 初期化
});
</script>
<?php include '../templates/footer.php'; ?>
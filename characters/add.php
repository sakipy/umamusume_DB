<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘追加';
$current_page = 'characters';
$base_path = '../';

// ========== 変数の初期化 ==========
$message = ''; 
$error_message = '';
$all_skills = [];

// ========== データベース接続 ==========
$conn = new mysqli('localhost', 'root', '', 'umamusume_db');
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// =================================================================
// POSTリクエスト（フォームが送信された）の場合の処理
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        // --- IDの重複チェック ---
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("IDは必須です。");
        }
        $stmt_check = $conn->prepare("SELECT id FROM characters WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("指定されたID「{$id}」は既に使用されています。");
        }
        $stmt_check->close();

        // ---  Helper function for file upload ---
        function upload_image($file_key, $prefix) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                $upload_dir = '../uploads/characters/';
                $file_name = time() . '_' . $prefix . '_' . basename($_FILES[$file_key]['name']);
                $target_file = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                    return 'uploads/characters/' . $file_name; // 相対パスを返す
                } else {
                    throw new Exception( ucfirst($prefix) . "画像のアップロードに失敗しました。");
                }
            }
            return null;
        }

        $image_url_suit = upload_image('character_image_suit', 'suit');

        // --- bind_paramを回避してSQLクエリを構築 ---
        $columns = [
            'id', 'character_name', 'rarity', 'pokedex_id', 'image_url', 'image_url_suit',
            'initial_speed', 'initial_stamina', 'initial_power', 'initial_guts', 'initial_wisdom',
            'growth_rate_speed', 'growth_rate_stamina', 'growth_rate_power', 'growth_rate_guts', 'growth_rate_wisdom',
            'surface_aptitude_turf', 'surface_aptitude_dirt',
            'distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long',
            'strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer'
        ];
        
        $values = [];
        // 各POSTデータを安全な形式に変換して$values配列に追加
        $values[] = (int)($_POST['id'] ?? 0);
        $values[] = "'" . $conn->real_escape_string($_POST['character_name']) . "'";
        $values[] = (int)($_POST['rarity'] ?? 1);
        $values[] = isset($_POST['pokedex_id']) && $_POST['pokedex_id'] !== '' ? (int)$_POST['pokedex_id'] : "NULL";
        $values[] = $image_url_suit ? "'" . $conn->real_escape_string($image_url_suit) . "'" : "NULL";
        $values[] = $image_url_suit ? "'" . $conn->real_escape_string($image_url_suit) . "'" : "NULL";
        $values[] = (int)($_POST['initial_speed'] ?? 0);
        $values[] = (int)($_POST['initial_stamina'] ?? 0);
        $values[] = (int)($_POST['initial_power'] ?? 0);
        $values[] = (int)($_POST['initial_guts'] ?? 0);
        $values[] = (int)($_POST['initial_wisdom'] ?? 0);
        $values[] = (float)($_POST['growth_rate_speed'] ?? 0);
        $values[] = (float)($_POST['growth_rate_stamina'] ?? 0);
        $values[] = (float)($_POST['growth_rate_power'] ?? 0);
        $values[] = (float)($_POST['growth_rate_guts'] ?? 0);
        $values[] = (float)($_POST['growth_rate_wisdom'] ?? 0);
        $values[] = "'" . $conn->real_escape_string($_POST['surface_aptitude_turf']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['surface_aptitude_dirt']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['distance_aptitude_short']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['distance_aptitude_mile']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['distance_aptitude_medium']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['distance_aptitude_long']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['strategy_aptitude_runner']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['strategy_aptitude_leader']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['strategy_aptitude_chaser']) . "'";
        $values[] = "'" . $conn->real_escape_string($_POST['strategy_aptitude_trailer']) . "'";

        $sql = "INSERT INTO characters (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

        if (!$conn->query($sql)) {
            throw new Exception("データベースへの登録に失敗しました: " . $conn->error);
        }
        
        // --- `character_skills` テーブルへのINSERT ---
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $stmt_skill = $conn->prepare("INSERT INTO character_skills (character_id, skill_id, unlock_condition) VALUES (?, ?, ?)");
            foreach ($_POST['skill_ids'] as $skill_id) {
                $unlock_condition = '初期'; 
                $stmt_skill->bind_param("iis", $id, $skill_id, $unlock_condition);
                $stmt_skill->execute();
            }
            $stmt_skill->close();
        }

        $conn->commit();
        $message = "新しいウマ娘「" . htmlspecialchars($_POST['character_name']) . "」を登録しました。";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "エラー: " . $e->getMessage();
    }
}

$all_skills_result = $conn->query("SELECT id, skill_name, skill_type FROM skills ORDER BY skill_name ASC");
while ($row = $all_skills_result->fetch_assoc()) { $all_skills[] = $row; }
$conn->close();
$aptitude_options = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
?>
<?php include '../templates/header.php'; ?>

<div class="container full-width">
    <h1>新しいウマ娘を登録</h1>
    <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add.php" method="POST" enctype="multipart/form-data">
        <div class="character-form-grid">
            <div class="form-col-main">
                <div class="form-group">
                    <label>ID:</label>
                    <input type="number" name="id" required>
                </div>
                <div class="form-group">
                    <label>ウマ娘名:</label>
                    <input type="text" name="character_name" value="<?php echo htmlspecialchars($scraped_data['character_name'] ?? ''); ?>" required>
                </div>
                 <div class="form-group">
                    <label>図鑑No. (紐付け):</label>
                    <input type="number" name="pokedex_id">
                </div>
                <div class="form-group">
                    <label>初期レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="1">★1</option> <option value="2">★2</option> <option value="3" selected>★3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>勝負服画像:</label>
                    <div id="image-preview-suit" class="image-preview-wrapper">
                        <img id="image_preview_suit_img" src="" style="display: none;"><span>プレビュー</span>
                    </div>
                    <label for="character_image_suit" class="file-upload-label">ファイルを選択...</label>
                    <input type="file" id="character_image_suit" name="character_image_suit" class="file-upload-input">
                </div>
            </div>

            <div class="form-col-details">
                 <h2 class="section-title-bar">初期ステータスと成長率</h2>
                <div class="status-growth-grid">
                    <div></div>
                    <div class="grid-header">スピ</div><div class="grid-header">スタ</div><div class="grid-header">パワ</div><div class="grid-header">根性</div><div class="grid-header">賢さ</div>
                    <div class="grid-label">初期値</div>
                    <input type="number" name="initial_speed" value="<?php echo htmlspecialchars($scraped_data['initial_speed'] ?? '100'); ?>">
                    <input type="number" name="initial_stamina" value="<?php echo htmlspecialchars($scraped_data['initial_stamina'] ?? '100'); ?>">
                    <input type="number" name="initial_power" value="<?php echo htmlspecialchars($scraped_data['initial_power'] ?? '100'); ?>">
                    <input type="number" name="initial_guts" value="<?php echo htmlspecialchars($scraped_data['initial_guts'] ?? '100'); ?>">
                    <input type="number" name="initial_wisdom" value="<?php echo htmlspecialchars($scraped_data['initial_wisdom'] ?? '100'); ?>">
                    <div class="grid-label">成長率(%)</div>
                    <input type="number" name="growth_rate_speed" step="0.1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_speed'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_stamina" step="0.1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_stamina'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_power" step="0.1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_power'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_guts" step="0.1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_guts'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_wisdom" step="0.1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_wisdom'] ?? '0'); ?>">
                </div>

                <h2 class="section-title-bar">適性</h2>
                <div class="aptitude-grid">
                    <div class="form-group">
                        <label>バ場適性</label>
                        <div class="aptitude-row">
                            <span>芝</span><select name="surface_aptitude_turf"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == 'A' ? 'selected' : '').">$op</option>"; ?></select>
                            <span>ダート</span><select name="surface_aptitude_dirt"><?php foreach($aptitude_options as $op) echo "<option value='$op' ".($op == 'G' ? 'selected' : '').">$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>距離適性</label>
                        <div class="aptitude-row">
                            <span>短距離</span><select name="distance_aptitude_short"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>マイル</span><select name="distance_aptitude_mile"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>中距離</span><select name="distance_aptitude_medium"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>長距離</span><select name="distance_aptitude_long"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>脚質適性</label>
                        <div class="aptitude-row">
                            <span>逃げ</span><select name="strategy_aptitude_runner"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>先行</span><select name="strategy_aptitude_leader"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>差し</span><select name="strategy_aptitude_chaser"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>追込</span><select name="strategy_aptitude_trailer"><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                        </div>
                    </div>
                </div>
                
                <h2 class="section-title-bar">所持スキル</h2>
                <div class="skill-selection-area">
                    <div class="skill-list-wrapper" style="max-height: 400px;">
                        <ul class="skill-list grid-layout" id="skill-list-container">
                            <?php foreach ($all_skills as $skill): ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>">
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
                <button type="submit">この内容で登録する</button>
            </div>
        </div>
    </form>
    <a href="index.php" class="back-link" style="margin-top: 24px;">&laquo; ウマ娘一覧に戻る</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('skill-list-container').addEventListener('change', e => {
        if (e.target.type === 'checkbox') e.target.closest('li').classList.toggle('selected', e.target.checked);
    });
    function setupImagePreview(inputId, wrapperId, imgId) {
        const fileInput = document.getElementById(inputId);
        const previewWrapper = document.getElementById(wrapperId);
        const imagePreview = document.getElementById(imgId);
        const placeholder = previewWrapper.querySelector('span');
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                imagePreview.src = URL.createObjectURL(this.files[0]);
                imagePreview.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
            }
        });
    }
    setupImagePreview('character_image_suit', 'image-preview-suit', 'image_preview_suit_img');
    const raritySelect = document.getElementById('rarity_select');
    function updateBorderColor() {
        const rarity = raritySelect.value;
        document.getElementById('image-preview-suit').className = 'image-preview-wrapper rarity-' + rarity;
    }
    raritySelect.addEventListener('change', updateBorderColor);
    updateBorderColor();
});
</script>
<?php include '../templates/footer.php'; ?>
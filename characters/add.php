<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘追加';
$current_page = 'characters';
$base_path = '../';

// ========== 変数の初期化 ==========
$message = ''; 
$error_message = '';
$all_skills = [];
$scraped_data = [];
$selected_skill_ids = [];
$character_skills_from_import = []; // インポートされたスキル情報（解放条件付き）

// セッションからデータを読み込む
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['scraped_data'])) {
    $scraped_data = $_SESSION['scraped_data'];
    // ▼▼▼【修正】解放条件付きのスキル情報を取得 ▼▼▼
    $character_skills_from_import = $scraped_data['character_skills'] ?? []; 
    // ▼▼▼【修正】チェックボックス選択用にスキルIDだけの配列も作成 ▼▼▼
    $selected_skill_ids = array_column($character_skills_from_import, 'skill_id');
    unset($_SESSION['scraped_data']);
}

// ========== データベース接続 ==========
$conn = new mysqli('localhost', 'root', '', 'umamusume_db');
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// ========== 最小の空きIDを取得する関数 ==========
function getNextAvailableId($conn) {
    // 既存のIDを昇順で取得
    $result = $conn->query("SELECT id FROM characters ORDER BY id ASC");
    $used_ids = [];
    while ($row = $result->fetch_assoc()) {
        $used_ids[] = $row['id'];
    }
    
    // 1から順番に確認して、最初に見つからないIDを返す
    $expected_id = 1;
    foreach ($used_ids as $used_id) {
        if ($expected_id < $used_id) {
            // 空きが見つかった
            return $expected_id;
        }
        $expected_id = $used_id + 1;
    }
    
    // 空きがない場合は、最大値+1を返す
    return $expected_id;
}

// =================================================================
// POSTリクエスト（フォームが送信された）の場合の処理
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            throw new Exception("有効なIDを入力してください。");
        }

        $stmt_check = $conn->prepare("SELECT id FROM characters WHERE id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception("指定されたID「{$id}」は既に使用されています。");
        }
        $stmt_check->close();

        function upload_image($file_key, $prefix) {
            if (!empty($_POST['image_suit_path'])) {
                return $_POST['image_suit_path'];
            }
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/characters/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $file_name = time() . '_' . $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($_FILES[$file_key]['tmp_name']);
                if (strpos($mime_type, 'image/') !== 0) {
                    throw new Exception(ucfirst($prefix) . "画像のファイル形式が無効です。");
                }
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                    return 'uploads/characters/' . $file_name;
                }
                throw new Exception(ucfirst($prefix) . "画像のアップロードに失敗しました。");
            }
            return null;
        }

        $image_url_suit = upload_image('character_image_suit', 'suit');

        $columns = [
            'id', 'character_name', 'rarity', 'pokedex_id', 'image_url', 'image_url_suit',
            'initial_speed', 'initial_stamina', 'initial_power', 'initial_guts', 'initial_wisdom',
            'growth_rate_speed', 'growth_rate_stamina', 'growth_rate_power', 'growth_rate_guts', 'growth_rate_wisdom',
            'surface_aptitude_turf', 'surface_aptitude_dirt',
            'distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long',
            'strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer'
        ];
        $values = [];
        $values[] = (int)($_POST['id'] ?? 0);
        $values[] = "'" . $conn->real_escape_string($_POST['character_name']) . "'";
        $values[] = (int)($_POST['rarity'] ?? 1);
        $values[] = !empty($_POST['pokedex_id']) ? (int)$_POST['pokedex_id'] : "NULL";
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
        
        // ▼▼▼【修正】スキル関連処理を簡素化 - unlock_conditionを削除 ▼▼▼
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $stmt_skill = $conn->prepare("INSERT INTO character_skills (character_id, skill_id) VALUES (?, ?)");
            foreach ($_POST['skill_ids'] as $skill_id) {
                $stmt_skill->bind_param("ii", $id, $skill_id);
                $stmt_skill->execute();
            }
            $stmt_skill->close();
        }

        $conn->commit();
        $message = "新しいウマ娘「" . htmlspecialchars($_POST['character_name']) . "」を登録しました。";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "エラー: " . $e->getMessage();
        $scraped_data = array_merge($scraped_data, $_POST);
        // エラー発生時も、スキル選択状態を復元するためにセッションデータを再利用
        $character_skills_from_import = json_decode($_POST['imported_skills_json'] ?? '[]', true);
        $selected_skill_ids = array_column($character_skills_from_import, 'skill_id');
    }
}

// スキル一覧を取得
$all_skills = [];
$all_skills_result = $conn->query("SELECT id, skill_name, skill_type FROM skills ORDER BY skill_name ASC");
while ($row = $all_skills_result->fetch_assoc()) { 
    $all_skills[] = $row; 
}

// 自動IDを取得（スクレイピングデータがない場合に使用）
$next_available_id = getNextAvailableId($conn);

$conn->close();

$aptitude_options = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
?>
<?php include '../templates/header.php'; ?>

<div class="container full-width">
    <h1>新しいウマ娘を登録</h1>
    <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="image_suit_path" value="<?php echo htmlspecialchars($scraped_data['image_suit_path'] ?? ''); ?>">
        
        <input type="hidden" name="imported_skills_json" value='<?php echo htmlspecialchars(json_encode($character_skills_from_import), ENT_QUOTES, 'UTF-8'); ?>'>

        <div class="character-form-grid">
            <div class="form-col-main">
                <div class="form-group">
                    <label for="id">ID:</label>
                    <input type="number" id="id" name="id" value="<?php echo htmlspecialchars($scraped_data['id'] ?? $next_available_id); ?>" required>
                    <small style="color: #666;">自動で空いている最小のIDが入力されています</small>
                </div>
                <div class="form-group">
                    <label for="character_name">ウマ娘名:</label>
                    <input type="text" id="character_name" name="character_name" value="<?php echo htmlspecialchars($scraped_data['character_name'] ?? ''); ?>" required>
                </div>
                 <div class="form-group">
                    <label for="pokedex_id">図鑑No. (紐付け):</label>
                    <input type="number" id="pokedex_id" name="pokedex_id" value="<?php echo htmlspecialchars($scraped_data['pokedex_id'] ?? ''); ?>">
                 </div>
                <div class="form-group">
                    <label for="rarity_select">初期レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="1" <?php echo (($scraped_data['rarity'] ?? 3) == 1) ? 'selected' : ''; ?>>★1</option> 
                        <option value="2" <?php echo (($scraped_data['rarity'] ?? 3) == 2) ? 'selected' : ''; ?>>★2</option> 
                        <option value="3" <?php echo (($scraped_data['rarity'] ?? 3) == 3) ? 'selected' : ''; ?>>★3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>勝負服画像:</label>
                    <div id="image-preview-suit" class="image-preview-wrapper">
                        <?php if (!empty($scraped_data['image_suit_path']) && file_exists($base_path . $scraped_data['image_suit_path'])): ?>
                            <img id="image_preview_suit_img" src="<?php echo htmlspecialchars($base_path . $scraped_data['image_suit_path']); ?>" style="display: block;">
                        <?php else: ?>
                            <img id="image_preview_suit_img" src="" style="display: none;"><span>プレビュー</span>
                        <?php endif; ?>
                    </div>
                    <label for="character_image_suit" class="file-upload-label">ファイルを選択...</label>
                    <input type="file" id="character_image_suit" name="character_image_suit" class="file-upload-input" accept="image/*">
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
                    <input type="number" name="growth_rate_speed" step="1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_speed'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_stamina" step="1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_stamina'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_power" step="1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_power'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_guts" step="1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_guts'] ?? '0'); ?>">
                    <input type="number" name="growth_rate_wisdom" step="1" value="<?php echo htmlspecialchars($scraped_data['growth_rate_wisdom'] ?? '0'); ?>">
                </div>

                <h2 class="section-title-bar">適性</h2>
                <div class="aptitude-grid">
                    <?php
                    function render_aptitude_options($name, $scraped_value) {
                        $options = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];
                        foreach($options as $op) {
                            $selected = ($op == $scraped_value) ? 'selected' : '';
                            echo "<option value='$op' $selected>$op</option>";
                        }
                    }
                    ?>
                    <div class="form-group">
                        <label>バ場適性</label>
                        <div class="aptitude-row">
                            <span>芝</span><select name="surface_aptitude_turf"><?php render_aptitude_options('surface_aptitude_turf', $scraped_data['surface_aptitude_turf'] ?? 'C'); ?></select>
                            <span>ダート</span><select name="surface_aptitude_dirt"><?php render_aptitude_options('surface_aptitude_dirt', $scraped_data['surface_aptitude_dirt'] ?? 'C'); ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>距離適性</label>
                        <div class="aptitude-row">
                            <span>短</span><select name="distance_aptitude_short"><?php render_aptitude_options('distance_aptitude_short', $scraped_data['distance_aptitude_short'] ?? 'C'); ?></select>
                            <span>マ</span><select name="distance_aptitude_mile"><?php render_aptitude_options('distance_aptitude_mile', $scraped_data['distance_aptitude_mile'] ?? 'C'); ?></select>
                            <span>中</span><select name="distance_aptitude_medium"><?php render_aptitude_options('distance_aptitude_medium', $scraped_data['distance_aptitude_medium'] ?? 'C'); ?></select>
                            <span>長</span><select name="distance_aptitude_long"><?php render_aptitude_options('distance_aptitude_long', $scraped_data['distance_aptitude_long'] ?? 'C'); ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>脚質適性</label>
                        <div class="aptitude-row">
                            <span>逃</span><select name="strategy_aptitude_runner"><?php render_aptitude_options('strategy_aptitude_runner', $scraped_data['strategy_aptitude_runner'] ?? 'C'); ?></select>
                            <span>先</span><select name="strategy_aptitude_leader"><?php render_aptitude_options('strategy_aptitude_leader', $scraped_data['strategy_aptitude_leader'] ?? 'C'); ?></select>
                            <span>差</span><select name="strategy_aptitude_chaser"><?php render_aptitude_options('strategy_aptitude_chaser', $scraped_data['strategy_aptitude_chaser'] ?? 'C'); ?></select>
                            <span>追</span><select name="strategy_aptitude_trailer"><?php render_aptitude_options('strategy_aptitude_trailer', $scraped_data['strategy_aptitude_trailer'] ?? 'C'); ?></select>
                        </div>
                    </div>
                </div>
                
                <h2 class="section-title-bar">所持スキル</h2>
                <div class="skill-selection-area">
                    <div class="skill-list-wrapper" style="max-height: 400px;">
                        <ul class="skill-list grid-layout" id="skill-list-container">
                            <?php foreach ($all_skills as $skill): ?>
                                <?php
                                $is_checked = in_array($skill['id'], $selected_skill_ids);
                                $is_selected_class = $is_checked ? 'selected' : '';
                                ?>
                                <li class="<?php echo $is_selected_class; ?>">
                                    <label>
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" <?php if ($is_checked) echo 'checked'; ?>>
                                        <?php
                                            $text_class = '';
                                            if ($skill['skill_type'] == 'レアスキル') $text_class = 'text-rare'; 
                                            elseif ($skill['skill_type'] == '進化スキル') $text_class = 'text-evolution';
                                            elseif ($skill['skill_type'] == '固有スキル') $text_class = 'text-rainbow';
                                        ?>
                                        <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">この内容で登録する</button>
                    <a href="index.php" class="back-link">キャンセル</a>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('skill-list-container').addEventListener('change', e => {
        if (e.target.type === 'checkbox') {
            e.target.closest('li').classList.toggle('selected', e.target.checked);
        }
    });

    function setupImagePreview(inputId, wrapperId, imgId) {
        const fileInput = document.getElementById(inputId);
        if (!fileInput) return;
        const previewWrapper = document.getElementById(wrapperId);
        const imagePreview = document.getElementById(imgId);
        const placeholder = previewWrapper.querySelector('span');
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    setupImagePreview('character_image_suit', 'image-preview-suit', 'image_preview_suit_img');

    const raritySelect = document.getElementById('rarity_select');
    function updateBorderColor() {
        const rarity = raritySelect.value;
        const previewWrapper = document.getElementById('image-preview-suit');
        previewWrapper.className = 'image-preview-wrapper'; 
        previewWrapper.classList.add('rarity-' + rarity);
    }
    raritySelect.addEventListener('change', updateBorderColor);
    updateBorderColor();
});
</script>
<?php include '../templates/footer.php'; ?>
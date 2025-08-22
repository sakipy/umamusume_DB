<?php
// ========== ページ設定 ==========
$page_title = 'サポートカード編集';
$current_page = 'support_card';
$base_path = '../';
$error_message = '';

// ========== DB接続設定 ==========
$db_host = 'localhost'; 
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'umamusume_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// ▼▼▼【追記】フォームで使う選択肢用のデータを準備 ▼▼▼
$effect_labels = [
    'friendship_bonus' => '友情ボーナス', 'race_bonus' => 'レースボーナス', 'initial_bond' => '初期絆ゲージ',
    'training_effect_up' => 'トレーニング効果UP', 'motivation_bonus' => 'やる気効果UP', 'specialty_rate_up' => '得意率UP',
    'hint_lv_up' => 'ヒントLvUP', 'hint_rate_up' => 'ヒント発生率UP', 'fan_bonus' => 'ファン数ボーナス',
    'skill_point_bonus' => 'スキルPtボーナス', 'failure_rate_down' => '失敗率DOWN', 'stamina_consumption_down' => '体力消費DOWN',
    'speed_bonus' => 'スピードボーナス', 'stamina_bonus' => 'スタミナボーナス', 'power_bonus' => 'パワーボーナス',
    'guts_bonus' => '根性ボーナス', 'wisdom_bonus' => '賢さボーナス', 'initial_skill_point_bonus' => '初期スキルPt'
];
// ▲▲▲【追記】▲▲▲

// ========== POSTリクエスト（フォーム送信）処理 ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { die("無効なIDです。"); }

    // 安全な画像削除関数
    function safe_delete_image($image_path) {
        if (empty($image_path)) return false;
        
        // パスの正規化と検証
        $real_path = realpath($image_path);
        $uploads_dir = realpath(__DIR__ . '/../uploads');
        
        // uploadsディレクトリ内かつファイルが存在することを確認
        if ($real_path && $uploads_dir && strpos($real_path, $uploads_dir) === 0 && is_file($real_path)) {
            return unlink($real_path);
        }
        return false;
    }

    // 画像アップロード処理関数
    function update_image($file_key, $current_image) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
            // MIME type 検証
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES[$file_key]['tmp_name']);
            if (strpos($mime_type, 'image/') !== 0) {
                throw new Exception("画像ファイル形式が無効です。");
            }

            $upload_dir = '../uploads/support_cards/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                // 古い画像を安全に削除
                safe_delete_image($current_image);
                return 'uploads/support_cards/' . $file_name;
            }
            throw new Exception("画像アップロードに失敗しました。");
        }
        return $current_image; // 新しいファイルがない場合は既存のパスを返す
    }

    // トランザクション開始
    $conn->begin_transaction();
    try {
        // 現在の画像URLを取得
        $stmt_current = $conn->prepare("SELECT image_url FROM support_cards WHERE id = ?");
        $stmt_current->bind_param("i", $id);
        $stmt_current->execute();
        $current_data = $stmt_current->get_result()->fetch_assoc();
        $stmt_current->close();

        // 画像アップロード処理
        $image_url = update_image('card_image', $current_data['image_url']);

        // 1. 基本情報の更新
        $card_name = $_POST['card_name'] ?? '';
        $rarity = $_POST['rarity'] ?? '';
        $card_type = $_POST['card_type'] ?? '';
        
        // ▼▼▼【追記】pokedex_idを取得 ▼▼▼
        $pokedex_id = !empty($_POST['pokedex_id']) ? (int)$_POST['pokedex_id'] : NULL;
        
        $stmt_card = $conn->prepare("UPDATE support_cards SET card_name = ?, rarity = ?, card_type = ?, image_url = ?, pokedex_id = ? WHERE id = ?");
        $stmt_card->bind_param("ssssii", $card_name, $rarity, $card_type, $image_url, $pokedex_id, $id);
        $stmt_card->execute();
        $stmt_card->close();

        // 2. 性能（Effects）の更新
        $stmt_effect = $conn->prepare("INSERT INTO card_effects (support_card_id, unlock_level, effect_type, effect_value) VALUES (?, 4, ?, ?) ON DUPLICATE KEY UPDATE effect_value = VALUES(effect_value)");
        foreach ($effect_labels as $name => $label) {
            $value = $_POST[$name] ?? 0;
            $stmt_effect->bind_param("isi", $id, $name, $value);
            $stmt_effect->execute();
        }
        $stmt_effect->close();

        // 3. 所持スキルの更新
        $skill_ids = $_POST['skill_ids'] ?? [];
        $stmt_delete_skills = $conn->prepare("DELETE FROM support_card_skills WHERE support_card_id = ?");
        $stmt_delete_skills->bind_param("i", $id);
        $stmt_delete_skills->execute();
        $stmt_delete_skills->close();
        
        if (!empty($skill_ids)) {
            $stmt_insert_skill = $conn->prepare("INSERT INTO support_card_skills (support_card_id, skill_id) VALUES (?, ?)");
            foreach ($skill_ids as $skill_id) {
                $stmt_insert_skill->bind_param("ii", $id, $skill_id);
                $stmt_insert_skill->execute();
            }
            $stmt_insert_skill->close();
        }

        // コミット
        $conn->commit();
        header("Location: index.php"); // 完了後は一覧ページへ
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "更新に失敗しました: " . $e->getMessage();
    }
}


// ========== GETリクエスト（編集フォーム表示）処理 ==========
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) { header("Location: index.php"); exit; }

$stmt_card = $conn->prepare("SELECT * FROM support_cards WHERE id = ?");
$stmt_card->bind_param("i", $id);
$stmt_card->execute();
$card = $stmt_card->get_result()->fetch_assoc();
$stmt_card->close();
if (!$card) { die("サポートカードが見つかりません。"); }

// ▼▼▼【追記】紐付け先候補となる全図鑑キャラクターを取得 ▼▼▼
$pokedex_list = [];
$result_pokedex = $conn->query("SELECT id, pokedex_name FROM pokedex ORDER BY pokedex_name ASC");
while($row = $result_pokedex->fetch_assoc()) {
    $pokedex_list[] = $row;
}

// (既存のコード... 性能、スキル情報を取得)
$effects = [];
$stmt_effects = $conn->prepare("SELECT effect_type, effect_value FROM card_effects WHERE support_card_id = ? AND unlock_level = 4");
$stmt_effects->bind_param("i", $id);
$stmt_effects->execute();
$result_effects = $stmt_effects->get_result();
while ($row = $result_effects->fetch_assoc()) { $effects[$row['effect_type']] = $row['effect_value']; }
$stmt_effects->close();

$all_skills = [];
$result_skills = $conn->query("SELECT * FROM skills ORDER BY skill_name ASC");
while ($row = $result_skills->fetch_assoc()) { $all_skills[] = $row; }

$current_skill_ids = [];
$stmt_current_skills = $conn->prepare("SELECT skill_id FROM support_card_skills WHERE support_card_id = ?");
$stmt_current_skills->bind_param("i", $id);
$stmt_current_skills->execute();
$result_current_skills = $stmt_current_skills->get_result();
while ($row = $result_current_skills->fetch_assoc()) { $current_skill_ids[] = $row['skill_id']; }
$stmt_current_skills->close();

$conn->close();
include '../templates/header.php';
?>

<div class="container">
    <h1>サポートカード編集</h1>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>
    <?php if ($card): ?>
    <form action="edit.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($card['id']); ?>">
        <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($card['image_url']); ?>">
        
        <h2>基本情報</h2>
        <div class="basic-info-grid">
            <div class="basic-info-col-image">
                <div class="form-group">
                    <label>カード画像:</label>
                    <div id="image-preview-wrapper" class="image-preview-wrapper">
                        <img id="image_preview" src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="画像プレビュー">
                    </div>
                    <div class="file-upload-wrapper">
                        <label for="card_image" class="file-upload-label">ファイルを選択して変更...</label>
                        <input type="file" id="card_image" name="card_image" class="file-upload-input" accept="image/jpeg, image/png, image/webp">
                    </div>
                </div>
            </div>
            <div class="basic-info-col-details">
                <div class="form-group">
                    <label for="card_name">カード名:</label>
                    <input type="text" id="card_name" name="card_name" value="<?php echo htmlspecialchars($card['card_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="rarity_select">レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="SSR" <?php if($card['rarity'] == 'SSR') echo 'selected'; ?>>SSR</option>
                        <option value="SR"  <?php if($card['rarity'] == 'SR') echo 'selected'; ?>>SR</option>
                        <option value="R"   <?php if($card['rarity'] == 'R') echo 'selected'; ?>>R</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="card_type">タイプ:</label>
                    <select id="card_type" name="card_type" required>
                        <option value="スピード" <?php if($card['card_type'] == 'スピード') echo 'selected'; ?>>スピード</option>
                        <option value="スタミナ" <?php if($card['card_type'] == 'スタミナ') echo 'selected'; ?>>スタミナ</option>
                        <option value="パワー"   <?php if($card['card_type'] == 'パワー') echo 'selected'; ?>>パワー</option>
                        <option value="根性"     <?php if($card['card_type'] == '根性') echo 'selected'; ?>>根性</option>
                        <option value="賢さ"     <?php if($card['card_type'] == '賢さ') echo 'selected'; ?>>賢さ</option>
                        <option value="友人"     <?php if($card['card_type'] == '友人') echo 'selected'; ?>>友人</option>
                        <option value="グループ" <?php if($card['card_type'] == 'グループ') echo 'selected'; ?>>グループ</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="pokedex_id">関連ウマ娘 (図鑑):</label>
                    <select id="pokedex_id" name="pokedex_id">
                        <option value="">-- 関連付けなし --</option>
                        <?php foreach($pokedex_list as $p_char): ?>
                            <option 
                                value="<?php echo $p_char['id']; ?>"
                                <?php if ($card['pokedex_id'] == $p_char['id']) echo 'selected'; ?>
                            >
                                <?php echo htmlspecialchars($p_char['pokedex_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <hr>
        <h2>完凸（4凸）時の性能</h2>
        <div class="form-grid-2col">
            <?php
            foreach ($effect_labels as $name => $label) {
                $value = $effects[$name] ?? 0;
                echo '<div class="form-group">';
                echo '<label for="' . $name . '">' . $label . ':</label>';
                echo '<input type="number" id="' . $name . '" name="' . $name . '" value="' . htmlspecialchars($value) . '">';
                echo '</div>';
            }
            ?>
        </div>

        <hr>
        <div class="skill-selection-area">
            <h2>所持スキル</h2>
            <div class="skill-filter-form">
                <div class="form-group">
                    <label for="skill_filter_type">タイプ:</label>
                    <select id="skill_filter_type">
                        <option value="">すべて</option>
                        <?php foreach($skill_type_options as $option): ?><option value="<?php echo $option; ?>"><?php echo $option; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="skill_filter_distance">距離:</label>
                    <select id="skill_filter_distance">
                        <option value="">すべて</option>
                        <?php foreach($distance_options as $option): ?><option value="<?php echo $option; ?>"><?php echo $option; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="skill_filter_strategy">脚質:</label>
                    <select id="skill_filter_strategy">
                        <option value="">すべて</option>
                        <?php foreach($strategy_options as $option): ?><option value="<?php echo $option; ?>"><?php echo $option; ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="skill-list-wrapper">
                <ul class="skill-list grid-layout" id="skill-list-container">
                    <?php foreach ($all_skills as $skill): ?>
                        <li data-type="<?php echo htmlspecialchars($skill['skill_type']); ?>"
                            data-distance="<?php echo htmlspecialchars($skill['distance_type']); ?>"
                            data-strategy="<?php echo htmlspecialchars($skill['strategy_type']); ?>"
                            <?php if(in_array($skill['id'], $current_skill_ids)) echo 'class="selected"'; ?>>
                            <label>
                                <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" 
                                    <?php if (in_array($skill['id'], $current_skill_ids)) echo 'checked'; ?>>
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

        <button type="submit">更新する</button>
    </form>
    <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- スキル絞り込み機能 ---
        const filterType = document.getElementById('skill_filter_type');
        const filterDistance = document.getElementById('skill_filter_distance');
        const filterStrategy = document.getElementById('skill_filter_strategy');
        const skillItems = document.querySelectorAll('#skill-list-container li');

        function filterSkills() {
            const type = filterType.value;
            const distance = filterDistance.value;
            const strategy = filterStrategy.value;

            skillItems.forEach(function(item) {
                const itemType = item.dataset.type;
                const itemDistance = item.dataset.distance;
                const itemStrategy = item.dataset.strategy;
                const typeMatch = (type === '') || (itemType === type);
                const distanceMatch = (distance === '') || (itemDistance.includes(distance)) || (itemDistance === '');
                const strategyMatch = (strategy === '') || (itemStrategy.includes(strategy)) || (itemStrategy === '');
                if (typeMatch && distanceMatch && strategyMatch) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        filterType.addEventListener('change', filterSkills);
        filterDistance.addEventListener('change', filterSkills);
        filterStrategy.addEventListener('change', filterSkills);

        // --- 画像プレビューと枠色変更の機能 ---
        const raritySelect = document.getElementById('rarity_select');
        const previewWrapper = document.getElementById('image-preview-wrapper');
        const fileInput = document.getElementById('card_image');
        const imagePreview = document.getElementById('image_preview');

        function updateBorderColor() {
            const selectedRarity = raritySelect.value.toLowerCase();
            previewWrapper.classList.remove('rarity-ssr', 'rarity-sr', 'rarity-r');
            if (selectedRarity) {
                previewWrapper.classList.add('rarity-' + selectedRarity);
            }
        }
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                imagePreview.src = URL.createObjectURL(file);
            }
        });
        raritySelect.addEventListener('change', updateBorderColor);
        updateBorderColor();

        // --- スキルリストのクリック・選択機能 ---
        const skillListContainer = document.getElementById('skill-list-container');
        const allSkillCheckboxes = skillListContainer.querySelectorAll('input[type="checkbox"]');

        // チェックボックスの状態が変わった時に背景色を切り替える
        allSkillCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const li = this.closest('li');
                if (li) {
                    li.classList.toggle('selected', this.checked);
                }
            });
        });

        // ★★★ ページ読み込み時に、既にチェックされている項目に背景色を適用 ★★★
        allSkillCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                checkbox.closest('li').classList.add('selected');
            }
        });
    });
</script>

<?php include '../templates/footer.php'; ?>
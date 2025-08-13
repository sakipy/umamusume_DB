<?php
// ========== ページ設定 ==========
$page_title = 'サポートカード追加';
$current_page = 'support_card';
$base_path = '../';
// ========== データベース接続設定 ==========
$db_host = 'localhost'; 
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'umamusume_db';
$message = ''; 
$error_message = '';
$all_skills = [];
// ========== データベース接続 ==========
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { 
    die("DB接続失敗: " . $conn->connect_error); 
}
$conn->set_charset("utf8mb4");
// =================================================================
// POSTリクエスト（フォームが送信された）の場合の処理
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        $image_url_to_save = null;
        if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] == 0) {
            $upload_dir = '../uploads/support_cards/';
            if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $file_name = time() . '_' . basename($_FILES['card_image']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['card_image']['tmp_name'], $target_file)) {
                $image_url_to_save = $target_file;
            } else {
                throw new Exception("ファイルのアップロードに失敗しました。");
            }
        }
        $stmt_card = $conn->prepare("INSERT INTO support_cards (card_name, rarity, card_type, image_url) VALUES (?, ?, ?, ?)");
        $stmt_card->bind_param("ssss", $_POST['card_name'], $_POST['rarity'], $_POST['card_type'], $image_url_to_save);
        $stmt_card->execute();
        $new_card_id = $conn->insert_id;
        if ($new_card_id == 0) {
            throw new Exception("カードの基本情報の登録に失敗しました。");
        }
        $stmt_card->close();
        $stmt_effect = $conn->prepare("INSERT INTO card_effects (support_card_id, unlock_level, effect_type, effect_value) VALUES (?, ?, ?, ?)");
        $effect_types = [
            'friendship_bonus', 'race_bonus', 'initial_bond', 'training_effect_up', 
            'motivation_bonus', 'specialty_rate_up', 'hint_lv_up', 'hint_rate_up', 'fan_bonus', 
            'skill_point_bonus', 'failure_rate_down', 'stamina_consumption_down',
            'speed_bonus', 'stamina_bonus', 'power_bonus', 'guts_bonus', 'wisdom_bonus',
            'initial_skill_point_bonus'
        ];
        $unlock_level = 4;
        foreach ($effect_types as $type) {
            if (isset($_POST[$type]) && is_numeric($_POST[$type])) {
                $value = (int)$_POST[$type];
                $stmt_effect->bind_param("iisi", $new_card_id, $unlock_level, $type, $value);
                $stmt_effect->execute();
            }
        }
        $stmt_effect->close();
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $stmt_add_skill = $conn->prepare("INSERT INTO support_card_skills (support_card_id, skill_id) VALUES (?, ?)");
            foreach ($_POST['skill_ids'] as $skill_id) {
                $sanitized_skill_id = (int)$skill_id;
                $stmt_add_skill->bind_param("ii", $new_card_id, $sanitized_skill_id);
                $stmt_add_skill->execute();
            }
            $stmt_add_skill->close();
        }
        $conn->commit();
        $message = "新しいサポートカード「" . htmlspecialchars($_POST['card_name']) . "」を登録しました。";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
$all_skills_result = $conn->query("SELECT id, skill_name, skill_type, skill_description, distance_type, strategy_type FROM skills ORDER BY skill_name ASC");
while ($row = $all_skills_result->fetch_assoc()) { 
    $all_skills[] = $row; 
}
$conn->close();
$effect_labels = [
    'friendship_bonus' => '友情ボーナス (%)', 'race_bonus' => 'レースボーナス (%)', 'initial_bond' => '初期絆ゲージ',
    'training_effect_up' => 'トレーニング効果UP (%)', 'motivation_bonus' => 'やる気効果UP (%)', 'specialty_rate_up' => '得意率UP',
    'speed_bonus' => 'スピードボーナス', 'stamina_bonus' => 'スタミナボーナス', 'power_bonus' => 'パワーボーナス',
    'guts_bonus' => '根性ボーナス', 'wisdom_bonus' => '賢さボーナス',
    'hint_lv_up' => 'ヒントLvUP', 'hint_rate_up' => 'ヒント発生率UP (%)', 'fan_bonus' => 'ファン数ボーナス (%)',
    'skill_point_bonus' => 'スキルPtボーナス', 'initial_skill_point_bonus' => '初期スキルPt',
    'failure_rate_down' => '失敗率ダウン (%)', 'stamina_consumption_down' => '体力消費ダウン (%)',
];
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$skill_type_options = ['ノーマルスキル', 'レアスキル', '進化スキル', '固有スキル', 'その他'];
?>
<?php include '../templates/header.php'; ?>
<div class="container">
    <h1>新しいサポートカードを登録</h1>
    <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add_card.php" method="POST" enctype="multipart/form-data">
        <h2>基本情報</h2>
        <div class="basic-info-grid">
            <div class="basic-info-col-image">
                <div class="form-group">
                    <label>カード画像:</label>
                    <div id="image-preview-wrapper" class="image-preview-wrapper rarity-ssr">
                        <img id="image_preview" src="" alt="画像プレビュー" style="display: none;">
                        <span>プレビュー</span>
                    </div>
                    <div class="file-upload-wrapper">
                        <label for="card_image" class="file-upload-label">ファイルを選択...</label>
                        <input type="file" id="card_image" name="card_image" class="file-upload-input" accept="image/jpeg, image/png, image/webp">
                    </div>
                </div>
            </div>
            <div class="basic-info-col-details">
                <div class="form-group">
                    <label for="card_name">カード名:</label>
                    <input type="text" id="card_name" name="card_name" required>
                </div>
                <div class="form-group">
                    <label for="rarity_select">レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="SSR">SSR</option><option value="SR">SR</option><option value="R">R</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="card_type">タイプ:</label>
                    <select id="card_type" name="card_type" required>
                        <option value="スピード">スピード</option><option value="スタミナ">スタミナ</option><option value="パワー">パワー</option><option value="根性">根性</option><option value="賢さ">賢さ</option><option value="友人">友人</option><option value="グループ">グループ</option>
                    </select>
                </div>
            </div>
        </div>
        
        <hr>
        <h2>完凸（4凸）時の性能</h2>
        <div class="form-grid-2col">
            <?php
            foreach ($effect_labels as $name => $label) {
                echo '<div class="form-group">';
                echo '<label for="' . $name . '">' . $label . ':</label>';
                echo '<input type="number" id="' . $name . '" name="' . $name . '" value="0">';
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
                            data-strategy="<?php echo htmlspecialchars($skill['strategy_type']); ?>">
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

        <button type="submit">登録する</button>
    </form>
    <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
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
        const previewPlaceholder = previewWrapper.querySelector('span');

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
                imagePreview.style.display = 'block';
                if(previewPlaceholder) previewPlaceholder.style.display = 'none';
            } else {
                imagePreview.src = '';
                imagePreview.style.display = 'none';
                if(previewPlaceholder) previewPlaceholder.style.display = 'block';
            }
        });
        raritySelect.addEventListener('change', updateBorderColor);
        updateBorderColor();

        // --- スキルリストのクリック・選択機能 ---
        const skillListContainer = document.getElementById('skill-list-container');
        const allSkillCheckboxes = skillListContainer.querySelectorAll('input[type="checkbox"]');

        allSkillCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const li = this.closest('li');
                if (li) {
                    li.classList.toggle('selected', this.checked);
                }
            });
        });
    });
    
</script>

<?php include '../templates/footer.php'; ?>
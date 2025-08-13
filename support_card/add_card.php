<?php
// ========== ページ設定 ==========
$page_title = 'サポートカード新規追加';
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

// フォームで使う選択肢用のデータを準備
$effect_labels = [
    'friendship_bonus' => '友情ボーナス', 'race_bonus' => 'レースボーナス', 'initial_bond' => '初期絆ゲージ',
    'training_effect_up' => 'トレーニング効果UP', 'motivation_bonus' => 'やる気効果UP', 'specialty_rate_up' => '得意率UP',
    'hint_lv_up' => 'ヒントLvUP', 'hint_rate_up' => 'ヒント発生率UP', 'fan_bonus' => 'ファン数ボーナス',
    'skill_point_bonus' => 'スキルPtボーナス', 'failure_rate_down' => '失敗率DOWN', 'stamina_consumption_down' => '体力消費DOWN',
    'speed_bonus' => 'スピードボーナス', 'stamina_bonus' => 'スタミナボーナス', 'power_bonus' => 'パワーボーナス',
    'guts_bonus' => '根性ボーナス', 'wisdom_bonus' => '賢さボーナス', 'initial_skill_point_bonus' => '初期スキルPt'
];

// ========== POSTリクエスト（フォーム送信）処理 ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // トランザクション開始
    $conn->begin_transaction();
    try {
        // 1. 画像アップロード処理
        $image_url = '';
        if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $base_path . 'uploads/support_cards/';
            if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $filename = uniqid() . '_' . basename($_FILES['card_image']['name']);
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['card_image']['tmp_name'], $target_path)) {
                $image_url = '../' . 'uploads/support_cards/' . $filename; // 相対パスの先頭に..を追加
            }
        }

        // 2. 基本情報のINSERT
        $card_name = $_POST['card_name'] ?? '';
        $rarity = $_POST['rarity'] ?? '';
        $card_type = $_POST['card_type'] ?? '';
        $pokedex_id = !empty($_POST['pokedex_id']) ? (int)$_POST['pokedex_id'] : NULL;
        
        $stmt_card = $conn->prepare("INSERT INTO support_cards (card_name, rarity, card_type, image_url, pokedex_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_card->bind_param("ssssi", $card_name, $rarity, $card_type, $image_url, $pokedex_id);
        $stmt_card->execute();
        $new_card_id = $conn->insert_id; // 新しく作成されたカードのIDを取得
        $stmt_card->close();

        // 3. 性能（Effects）のINSERT
        $stmt_effect = $conn->prepare("INSERT INTO card_effects (support_card_id, unlock_level, effect_type, effect_value) VALUES (?, 4, ?, ?)");
        foreach ($effect_labels as $name => $label) {
            $value = $_POST[$name] ?? 0;
            if ($value > 0) { // 値が0より大きいものだけを登録
                $stmt_effect->bind_param("isi", $new_card_id, $name, $value);
                $stmt_effect->execute();
            }
        }
        $stmt_effect->close();

        // 4. 所持スキルのINSERT
        $skill_ids = $_POST['skill_ids'] ?? [];
        if (!empty($skill_ids)) {
            $stmt_insert_skill = $conn->prepare("INSERT INTO support_card_skills (support_card_id, skill_id) VALUES (?, ?)");
            foreach ($skill_ids as $skill_id) {
                $stmt_insert_skill->bind_param("ii", $new_card_id, $skill_id);
                $stmt_insert_skill->execute();
            }
            $stmt_insert_skill->close();
        }

        // 全て成功したらコミット
        $conn->commit();
        header("Location: index.php?message=success_add"); // 完了後は一覧ページへ
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "新規追加に失敗しました: " . $e->getMessage();
    }
}

// ========== GETリクエスト（フォーム表示）処理 ==========
// 紐付け先候補となる全図鑑キャラクターを取得
$pokedex_list = [];
$result_pokedex = $conn->query("SELECT id, pokedex_name FROM pokedex ORDER BY pokedex_name ASC");
while($row = $result_pokedex->fetch_assoc()) { $pokedex_list[] = $row; }

// スキル選択用に全スキルを取得
$all_skills = [];
$result_skills = $conn->query("SELECT * FROM skills ORDER BY skill_name ASC");
while ($row = $result_skills->fetch_assoc()) { $all_skills[] = $row; }

// スキルフィルター用の選択肢を取得
$skill_type_options = [];
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$result_types = $conn->query("SELECT DISTINCT skill_type FROM skills WHERE skill_type IS NOT NULL AND skill_type != '' ORDER BY skill_type");
while($row = $result_types->fetch_assoc()){ $skill_type_options[] = $row['skill_type']; }

$conn->close();
include '../templates/header.php';
?>
<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>
    
    <form action="add_card.php" method="POST" enctype="multipart/form-data">
        
        <h2>基本情報</h2>
        <div class="basic-info-grid">
            <div class="basic-info-col-image">
                <div class="form-group">
                    <label>カード画像:</label>
                    <div id="image-preview-wrapper" class="image-preview-wrapper">
                        <img id="image_preview" src="../assets/img/no_image_support.png" alt="画像プレビュー">
                    </div>
                    <div class="file-upload-wrapper">
                        <label for="card_image" class="file-upload-label">ファイルを選択...</label>
                        <input type="file" id="card_image" name="card_image" class="file-upload-input" accept="image/jpeg, image/png, image/webp" required>
                    </div>
                </div>
            </div>
            <div class="basic-info-col-details">
                <div class="form-group">
                    <label for="card_name">カード名:</label>
                    <input type="text" id="card_name" name="card_name" value="" required>
                </div>
                <div class="form-group">
                    <label for="rarity_select">レアリティ:</label>
                    <select id="rarity_select" name="rarity" required>
                        <option value="SSR">SSR</option>
                        <option value="SR">SR</option>
                        <option value="R">R</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="card_type">タイプ:</label>
                    <select id="card_type" name="card_type" required>
                        <option value="スピード">スピード</option>
                        <option value="スタミナ">スタミナ</option>
                        <option value="パワー">パワー</option>
                        <option value="根性">根性</option>
                        <option value="賢さ">賢さ</option>
                        <option value="友人">友人</option>
                        <option value="グループ">グループ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pokedex_id">関連ウマ娘 (図鑑):</label>
                    <select id="pokedex_id" name="pokedex_id">
                        <option value="">-- 関連付けなし --</option>
                        <?php foreach($pokedex_list as $p_char): ?>
                            <option value="<?php echo $p_char['id']; ?>">
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

        <div class="form-actions">
            <button type="submit" class="button-primary">この内容で登録する</button>
            <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
        </div>
    </form>
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
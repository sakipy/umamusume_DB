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

        // 3. 所持スキルの更新（関係性と順序も含む）
        $skill_ids = $_POST['skill_ids'] ?? [];
        $stmt_delete_skills = $conn->prepare("DELETE FROM support_card_skills WHERE support_card_id = ?");
        $stmt_delete_skills->bind_param("i", $id);
        $stmt_delete_skills->execute();
        $stmt_delete_skills->close();
        
        if (!empty($skill_ids)) {
            $stmt_insert_skill = $conn->prepare("INSERT INTO support_card_skills (support_card_id, skill_id, skill_relation, skill_order) VALUES (?, ?, ?, ?)");
            foreach ($skill_ids as $order => $skill_id) {
                $skill_id = (int)$skill_id;
                if ($skill_id > 0) {
                    // スキル関係の取得（最初のスキル以外）
                    $skill_relation = 'and'; // デフォルト
                    if ($order > 0 && isset($_POST['skill_relations'][$order])) {
                        $skill_relation = $_POST['skill_relations'][$order];
                    }
                    
                    $stmt_insert_skill->bind_param("iisi", $id, $skill_id, $skill_relation, $order);
                    $stmt_insert_skill->execute();
                }
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
$current_skill_relations = [];
$stmt_current_skills = $conn->prepare("SELECT skill_id, skill_relation, skill_order FROM support_card_skills WHERE support_card_id = ? ORDER BY skill_order ASC");
$stmt_current_skills->bind_param("i", $id);
$stmt_current_skills->execute();
$result_current_skills = $stmt_current_skills->get_result();
while ($row = $result_current_skills->fetch_assoc()) { 
    $current_skill_ids[] = $row['skill_id']; 
    $current_skill_relations[$row['skill_id']] = [
        'relation' => $row['skill_relation'] ?? 'and',
        'order' => $row['skill_order'] ?? 0
    ];
}
$stmt_current_skills->close();

// スキル絞り込み用の選択肢を生成
$skill_type_options = [];
$distance_options = [];
$strategy_options = [];

foreach ($all_skills as $skill) {
    if (!empty($skill['skill_type']) && !in_array($skill['skill_type'], $skill_type_options)) {
        $skill_type_options[] = $skill['skill_type'];
    }
    if (!empty($skill['distance_type']) && !in_array($skill['distance_type'], $distance_options)) {
        $distance_options[] = $skill['distance_type'];
    }
    if (!empty($skill['strategy_type']) && !in_array($skill['strategy_type'], $strategy_options)) {
        $strategy_options[] = $skill['strategy_type'];
    }
}

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
            <!-- 選択済みスキル表示 -->
            <div class="selected-skills-section">
                <h2>選択済みスキル（関係性設定可能）</h2>
                <div id="selected-skills" class="selected-skills-list">
                    <?php 
                    // スキルを順序でソート
                    $ordered_skills = [];
                    foreach ($current_skill_ids as $skill_id) {
                        $order = $current_skill_relations[$skill_id]['order'] ?? count($ordered_skills);
                        $ordered_skills[$order] = $skill_id;
                    }
                    ksort($ordered_skills);
                    
                    if (empty($ordered_skills)): ?>
                        <div class="no-skills-message">
                            <p class="text-muted">スキルが登録されていません</p>
                            <small>下記のスキル一覧から追加してください</small>
                        </div>
                    <?php else:
                        foreach ($ordered_skills as $order => $skill_id): 
                            $skill_info = array_filter($all_skills, function($s) use ($skill_id) { return $s['id'] == $skill_id; });
                            $skill_info = reset($skill_info);
                            if ($skill_info):
                                $text_class = '';
                                if ($skill_info['skill_type'] == 'レアスキル') { $text_class = 'text-rare'; } 
                                elseif ($skill_info['skill_type'] == '進化スキル') { $text_class = 'text-evolution'; }
                                elseif ($skill_info['skill_type'] == '固有スキル') { $text_class = 'text-rainbow'; }
                                
                                $current_relation = $current_skill_relations[$skill_id]['relation'] ?? 'and';
                        ?>
                            <div class="selected-skill-item" data-skill-id="<?php echo $skill_id; ?>">
                                <input type="hidden" name="skill_ids[]" value="<?php echo $skill_id; ?>">
                                <div class="skill-info">
                                    <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill_info['skill_name']); ?></span>
                                    <small><?php echo htmlspecialchars($skill_info['skill_type']); ?></small>
                                </div>
                                <?php if ($order > 0): ?>
                                    <div class="skill-relation-selector">
                                        <label>関係性:</label>
                                        <select name="skill_relations[<?php echo $order; ?>]">
                                            <option value="and" <?php echo $current_relation == 'and' ? 'selected' : ''; ?>>＋（AND）</option>
                                            <option value="or" <?php echo $current_relation == 'or' ? 'selected' : ''; ?>>or（OR）</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="skill-relation-info">
                                        <small class="text-muted">最初のスキル</small>
                                    </div>
                                <?php endif; ?>
                                <button type="button" class="remove-skill-btn" onclick="removeSkill(<?php echo $skill_id; ?>)">×</button>
                            </div>
                        <?php 
                            endif;
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>

            <!-- スキル選択 -->
            <div class="skill-selection-section">
                <h3>スキル追加</h3>
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
                                data-skill-id="<?php echo $skill['id']; ?>"
                                <?php if(in_array($skill['id'], $current_skill_ids)) echo 'style="display:none;" data-skill-selected="true"'; ?>>
                                <button type="button" class="add-skill-btn" onclick="addSkill(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($skill['skill_type'], ENT_QUOTES); ?>')">
                                    <?php
                                        $text_class = '';
                                        if ($skill['skill_type'] == 'レアスキル') { $text_class = 'text-rare'; } 
                                        elseif ($skill['skill_type'] == '進化スキル') { $text_class = 'text-evolution'; }
                                        elseif ($skill['skill_type'] == '固有スキル') { $text_class = 'text-rainbow'; }
                                    ?>
                                    <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                    <small><?php echo htmlspecialchars($skill['skill_type']); ?></small>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <button type="submit">更新する</button>
    </form>
    <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初期のスキル数を設定
    let skillOrder = document.querySelectorAll('.selected-skill-item').length;

    // 既存の選択済みスキルにドラッグ機能を追加
    document.querySelectorAll('.selected-skill-item').forEach(makeDraggable);

    // スキル追加
    window.addSkill = function(skillId, skillName, skillType) {
        const selectedSkills = document.getElementById('selected-skills');
        const skillItem = document.querySelector(`li[data-skill-id="${skillId}"]`);
        
        // 関係性セレクタ（最初のスキル以外）
        const relationHtml = skillOrder > 0 ? `
            <div class="skill-relation-selector">
                <label>関係性:</label>
                <select name="skill_relations[${skillOrder}]">
                    <option value="and">＋（AND）</option>
                    <option value="or">or（OR）</option>
                </select>
            </div>
        ` : `
            <div class="skill-relation-info">
                <small class="text-muted">最初のスキル</small>
            </div>
        `;
        
        // テキストクラスの判定
        let textClass = '';
        if (skillType === 'レアスキル') textClass = 'text-rare';
        else if (skillType === '進化スキル') textClass = 'text-evolution';
        else if (skillType === '固有スキル') textClass = 'text-rainbow';
        
        // 選択済みスキルに追加
        const newSkillDiv = document.createElement('div');
        newSkillDiv.className = 'selected-skill-item';
        newSkillDiv.dataset.skillId = skillId;
        newSkillDiv.innerHTML = `
            <input type="hidden" name="skill_ids[]" value="${skillId}">
            <div class="skill-info">
                <span class="${textClass}">${skillName}</span>
                <small>${skillType}</small>
            </div>
            ${relationHtml}
            <button type="button" class="remove-skill-btn" onclick="removeSkill(${skillId})">×</button>
        `;
        
        selectedSkills.appendChild(newSkillDiv);
        
        // 「スキルが登録されていません」メッセージを非表示
        const noSkillsMessage = document.querySelector('.no-skills-message');
        if (noSkillsMessage) {
            noSkillsMessage.style.display = 'none';
        }
        
        // ドラッグ&ドロップ機能を追加
        makeDraggable(newSkillDiv);
        
        // リストから非表示にして、選択済みマークを付ける
        if (skillItem) {
            skillItem.style.display = 'none';
            skillItem.setAttribute('data-skill-selected', 'true');
        }
        
        skillOrder++;
    };

    // スキル削除
    window.removeSkill = function(skillId) {
        const selectedSkill = document.querySelector(`.selected-skill-item[data-skill-id="${skillId}"]`);
        const skillItem = document.querySelector(`li[data-skill-id="${skillId}"]`);
        
        if (selectedSkill) {
            selectedSkill.remove();
        }
        
        if (skillItem) {
            skillItem.style.display = 'block';
            skillItem.removeAttribute('data-skill-selected');
        }
        
        // 順序を再計算
        reorderSkills();
        
        // スキルがなくなった場合にメッセージを再表示
        const remainingSkills = document.querySelectorAll('.selected-skill-item');
        const noSkillsMessage = document.querySelector('.no-skills-message');
        if (remainingSkills.length === 0 && noSkillsMessage) {
            noSkillsMessage.style.display = 'block';
        }
    };

    // スキル順序の再計算
    function reorderSkills() {
        const selectedSkills = document.querySelectorAll('.selected-skill-item');
        skillOrder = selectedSkills.length;
        
        selectedSkills.forEach((skill, index) => {
            const hiddenInput = skill.querySelector('input[name="skill_ids[]"]');
            const relationContainer = skill.querySelector('.skill-relation-selector, .skill-relation-info');
            
            if (index === 0) {
                // 最初のスキルは関係性セレクタを情報に変更
                if (relationContainer && relationContainer.classList.contains('skill-relation-selector')) {
                    relationContainer.outerHTML = `
                        <div class="skill-relation-info">
                            <small class="text-muted">最初のスキル</small>
                        </div>
                    `;
                }
            } else {
                // 2番目以降は関係性セレクタを表示
                if (relationContainer && relationContainer.classList.contains('skill-relation-info')) {
                    relationContainer.outerHTML = `
                        <div class="skill-relation-selector">
                            <label>関係性:</label>
                            <select name="skill_relations[${index}]">
                                <option value="and">＋（AND）</option>
                                <option value="or">or（OR）</option>
                            </select>
                        </div>
                    `;
                } else if (relationContainer) {
                    const select = relationContainer.querySelector('select');
                    if (select) {
                        select.name = `skill_relations[${index}]`;
                    }
                }
            }
        });
    }

    // ドラッグ&ドロップ機能
    function makeDraggable(element) {
        element.setAttribute('draggable', 'true');
        
        element.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', '');
            element.classList.add('dragging');
        });
        
        element.addEventListener('dragend', function(e) {
            element.classList.remove('dragging');
        });
        
        element.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        element.addEventListener('drop', function(e) {
            e.preventDefault();
            const dragging = document.querySelector('.dragging');
            const container = document.getElementById('selected-skills');
            
            if (dragging && dragging !== element) {
                const allItems = [...container.children];
                const draggingIndex = allItems.indexOf(dragging);
                const targetIndex = allItems.indexOf(element);
                
                if (draggingIndex < targetIndex) {
                    container.insertBefore(dragging, element.nextSibling);
                } else {
                    container.insertBefore(dragging, element);
                }
                
                reorderSkills();
            }
        });
    }

    // --- スキル絞り込み機能 ---
    const filterType = document.getElementById('skill_filter_type');
    const filterDistance = document.getElementById('skill_filter_distance');
    const filterStrategy = document.getElementById('skill_filter_strategy');
    const skillItems = document.querySelectorAll('#skill-list-container li');

    function filterSkills() {
        const type = filterType.value;
        const distance = filterDistance.value;
        const strategy = filterStrategy.value;

        console.log('フィルタ条件:', { type, distance, strategy });
        let visibleCount = 0;

        skillItems.forEach(function(item) {
            const itemType = item.getAttribute('data-type');
            const itemDistance = item.getAttribute('data-distance');
            const itemStrategy = item.getAttribute('data-strategy');
            
            // 既に選択済みのスキルかどうかをチェック
            const isAlreadySelected = item.style.display === 'none' && item.hasAttribute('data-skill-selected');

            // 選択済みスキルはそのまま非表示を維持
            if (isAlreadySelected) {
                return;
            }

            // フィルタ条件のマッチング
            const typeMatch = !type || itemType === type;
            const distanceMatch = !distance || itemDistance === distance || itemDistance === '' || itemDistance === null;
            const strategyMatch = !strategy || itemStrategy === strategy || itemStrategy === '' || itemStrategy === null;

            console.log('スキル:', item.querySelector('.add-skill-btn span')?.textContent, {
                itemType, itemDistance, itemStrategy,
                typeMatch, distanceMatch, strategyMatch
            });

            if (typeMatch && distanceMatch && strategyMatch) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        console.log('表示されているスキル数:', visibleCount);
    }

    filterType.addEventListener('change', filterSkills);
    filterDistance.addEventListener('change', filterSkills);
    filterStrategy.addEventListener('change', filterSkills);

    // 初期表示時にもフィルタを実行（デバッグのため）
    console.log('初期化時のスキル要素数:', skillItems.length);
    skillItems.forEach((item, index) => {
        console.log(`スキル${index}:`, {
            name: item.querySelector('.add-skill-btn span')?.textContent,
            type: item.getAttribute('data-type'),
            distance: item.getAttribute('data-distance'),
            strategy: item.getAttribute('data-strategy'),
            display: item.style.display,
            selected: item.hasAttribute('data-skill-selected')
        });
    });

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
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                imagePreview.src = URL.createObjectURL(file);
            }
        });
    }
    
    if (raritySelect) {
        raritySelect.addEventListener('change', updateBorderColor);
        updateBorderColor();
    }
});
</script>


</script>

<style>
.selected-skills-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.selected-skills-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
    min-height: 60px;
}

.no-skills-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    text-align: center;
}

.no-skills-message p {
    margin: 0 0 5px 0;
    font-size: 1.1em;
    color: #6c757d;
}

.no-skills-message small {
    color: #6c757d;
    font-style: italic;
}

.selected-skill-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 16px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: move;
    transition: background-color 0.2s, box-shadow 0.2s;
    position: relative;
}

.selected-skill-item:hover {
    background: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.selected-skill-item.dragging {
    opacity: 0.5;
    transform: rotate(5deg);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.selected-skill-item::before {
    content: '⋮⋮';
    position: absolute;
    left: 5px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 12px;
    line-height: 1;
    letter-spacing: -2px;
}

.skill-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.skill-info small {
    color: #666;
    font-size: 0.8em;
}

.skill-relation-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}

.skill-relation-selector label {
    font-size: 0.9em;
    color: #666;
    margin: 0;
}

.skill-relation-selector select {
    padding: 4px 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 0.9em;
}

.skill-relation-info {
    display: flex;
    align-items: center;
}

.text-muted {
    color: #888;
    font-style: italic;
}

.remove-skill-btn {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.remove-skill-btn:hover {
    background: #c82333;
}

.add-skill-btn {
    width: 100%;
    padding: 10px 12px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    text-align: left;
    transition: background-color 0.2s;
}

.add-skill-btn:hover {
    background: #e9ecef;
}

.add-skill-btn small {
    display: block;
    color: #666;
    font-size: 0.8em;
    margin-top: 2px;
}

.skill-selection-section {
    border-top: 1px solid #ddd;
    padding-top: 20px;
    margin-top: 20px;
}

.skill-selection-section h3 {
    margin-bottom: 15px;
    color: #444;
}

.skill-filter-form {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.skill-filter-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 150px;
}

.skill-filter-form label {
    font-weight: bold;
    color: #495057;
    font-size: 0.9em;
}

.skill-filter-form select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: white;
    font-size: 0.9em;
    cursor: pointer;
    transition: border-color 0.2s;
}

.skill-filter-form select:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.skill-list-wrapper {
    margin-top: 15px;
}

.skill-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.skill-list.grid-layout {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
}

.skill-list li {
    margin: 0;
    padding: 0;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .skill-filter-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .skill-filter-form .form-group {
        min-width: auto;
    }
    
    .skill-list.grid-layout {
        grid-template-columns: 1fr;
    }
    
    .selected-skills-list {
        gap: 8px;
    }
    
    .selected-skill-item {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .selected-skill-item::before {
        display: none;
    }
}

/* 選択済みスキル用のスペース調整 */
.selected-skill-item .skill-info {
    margin-left: 15px;
}
</style>

<?php include '../templates/footer.php'; ?>
<?php
// ========== ページ設定 ==========
$page_title = 'サポートカード詳細';
$current_page = 'support_card';
$base_path = '../';

// ========== データベース接続設定 ==========
$db_host = 'localhost'; 
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'umamusume_db';

// ========== IDの取得とバリデーション ==========
if (empty($_GET['id'])) { 
    die("IDが指定されていません。"); 
}
$card_id = (int)$_GET['id'];

// ========== データベース接続 ==========
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { 
    die("DB接続失敗: " . $conn->connect_error); 
}
$conn->set_charset("utf8mb4");

// --- 1. カードの基本情報を取得 ---
$stmt_card = $conn->prepare("SELECT * FROM support_cards WHERE id = ?");
$stmt_card->bind_param("i", $card_id);
$stmt_card->execute();
$card = $stmt_card->get_result()->fetch_assoc();
$stmt_card->close();

if (!$card) { 
    die("指定されたカードが見つかりません。"); 
}
// ページタイトルをカード名で更新
$page_title = '詳細: ' . htmlspecialchars($card['card_name']);

// --- 2. カードの性能情報を取得 ---
$stmt_effects = $conn->prepare("SELECT unlock_level, effect_type, effect_value FROM card_effects WHERE support_card_id = ? ORDER BY unlock_level, id");
$stmt_effects->bind_param("i", $card_id);
$stmt_effects->execute();
$result_effects = $stmt_effects->get_result();
$effects_by_level = [];
while ($row = $result_effects->fetch_assoc()) {
    $effects_by_level[$row['unlock_level']][$row['effect_type']] = $row['effect_value'];
}
$stmt_effects->close();

// --- 3. 所持スキル情報を取得 ---
$owned_skills = [];
$stmt_skills = $conn->prepare("
    SELECT s.skill_name, s.skill_description, s.skill_type, scs.skill_relation, scs.skill_order
    FROM skills s
    INNER JOIN support_card_skills scs ON s.id = scs.skill_id
    WHERE scs.support_card_id = ?
    ORDER BY scs.skill_order ASC, s.id ASC
");
$stmt_skills->bind_param("i", $card_id);
$stmt_skills->execute();
$result_skills = $stmt_skills->get_result();
while ($row = $result_skills->fetch_assoc()) {
    $owned_skills[] = $row;
}
$stmt_skills->close();
$conn->close();

// --- 画面表示用のラベル定義 ---
$effect_labels = [
    'friendship_bonus' => '友情ボーナス', 'race_bonus' => 'レースボーナス', 'initial_bond' => '初期絆ゲージ',
    'training_effect_up' => 'トレーニング効果UP', 'motivation_bonus' => 'やる気効果UP', 'specialty_rate_up' => '得意率UP',
    'speed_bonus' => 'スピードボーナス', 'stamina_bonus' => 'スタミナボーナス', 'power_bonus' => 'パワーボーナス',
    'guts_bonus' => '根性ボーナス', 'wisdom_bonus' => '賢さボーナス',
    'hint_lv_up' => 'ヒントLvUP', 'hint_rate_up' => 'ヒント発生率UP', 'fan_bonus' => 'ファン数ボーナス',
    'skill_point_bonus' => 'スキルPtボーナス', 'initial_skill_point_bonus' => '初期スキルPt',
    'failure_rate_down' => '失敗率ダウン', 'stamina_consumption_down' => '体力消費ダウン',
];
?>
<?php include '../templates/header.php'; ?>

<style>
    .details-grid { display: grid; grid-template-columns: 300px 1fr; gap: 32px; align-items: flex-start; }
    .details-image { text-align: center; }
    .details-image img { max-width: 100%; border-radius: 12px; border: 2px solid var(--gold-color); }
    .details-info p { font-size: 1.1em; font-weight: bold; margin: 0 0 10px; }
    .details-info strong { margin-right: 10px; color: #888; font-weight: normal;}
    .section-title { border-left: 8px solid var(--gold-color); padding-left: 12px; text-align: left; margin-top: 30px; }
    .effects-list { list-style: none; padding: 0; column-count: 2; column-gap: 20px; }
    .effects-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 1em; }
    .effects-list li span { font-weight: bold; font-size: 1.1em; color: var(--primary-color); }
    .skill-table { width: 100%; margin-top: 10px; border-collapse: collapse; }
    .skill-table th, .skill-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    .skill-table th { background-color: #f2f2f2; }
    .skill-description {
        font-size: 0.9em;
        color: #666;
        padding-top: 4px;
    }
    
    .skill-relation {
        text-align: center;
        width: 80px;
    }
    
    .relation-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 14px;
        color: white;
        min-width: 30px;
        text-align: center;
    }
    
    .relation-badge.and {
        background-color: #28a745;
    }
    
    .relation-badge.or {
        background-color: #dc3545;
    }

    .skills-display {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin-top: 15px;
    }

    .skill-connector {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 8px 0;
        position: relative;
    }

    .skill-connector::before,
    .skill-connector::after {
        content: '';
        position: absolute;
        width: 2px;
        height: 20px;
        background-color: #ddd;
        left: 50%;
        transform: translateX(-50%);
    }

    .skill-connector::before {
        top: -20px;
    }

    .skill-connector::after {
        bottom: -20px;
    }

    .skill-item {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 16px;
        margin: 0 20px;
        position: relative;
    }

    .skill-name-container {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .skill-name {
        font-size: 1.1em;
        font-weight: bold;
    }

    .skill-type-badge {
        background: #6c757d;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        white-space: nowrap;
    }

    .skill-description {
        font-size: 0.9em;
        color: #666;
        line-height: 1.4;
    }
</style>

<div class="page-wrapper-with-sidebar">

    <div class="container main-content-area">
        <h1><?php echo htmlspecialchars($card['card_name']); ?></h1>
    
        <div class="details-grid">
            <div class="details-image">
                <?php if (!empty($card['image_url']) && file_exists($card['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['card_name']); ?>">
                <?php else: ?>
                    <div class="no-image" style="height: 400px;">画像なし</div>
                <?php endif; ?>
            </div>
    
            <div class="details-info">
                <h2 class="section-title">基本情報</h2>
                <p><strong>レアリティ:</strong> <?php echo htmlspecialchars($card['rarity']); ?></p>
                <p><strong>タイプ:</strong> <?php echo htmlspecialchars($card['card_type']); ?></p>
                
                <?php if (!empty($effects_by_level)): ?>
                    <?php foreach ($effects_by_level as $level => $effects): ?>
                        <div class="effects-section">
                            <h2 class="section-title"><?php echo $level; ?>凸時の性能</h2>
                            <ul class="effects-list">
                                <?php foreach ($effect_labels as $type => $label): ?>
                                    <?php if(isset($effects[$type]) && $effects[$type] != 0): ?>
                                    <li>
                                        <?php echo $label; ?>
                                        <span><?php echo htmlspecialchars($effects[$type]); ?></span>
                                    </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    
        <div class="skill-section">
            <h2 class="section-title">所持スキル</h2>
            <?php if (!empty($owned_skills)): ?>
                <div class="skills-display">
                    <?php 
                    foreach ($owned_skills as $index => $skill): 
                        $name_class = '';
                        if ($skill['skill_type'] == 'レアスキル') { $name_class = 'text-rare'; } 
                        elseif ($skill['skill_type'] == '進化スキル') { $name_class = 'text-evolution'; }
                        elseif ($skill['skill_type'] == '固有スキル') { $name_class = 'text-rainbow'; }
                        
                        // スキル関係の表示判定
                        if ($index > 0) {
                            $relation = $skill['skill_relation'] ?? 'and';
                            $relation_class = $relation;
                            $relation_text = ($relation === 'and') ? '＋' : 'or';
                            echo '<div class="skill-connector">';
                            echo '<span class="relation-badge ' . $relation_class . '">' . htmlspecialchars($relation_text) . '</span>';
                            echo '</div>';
                        }
                    ?>
                        <div class="skill-item">
                            <div class="skill-name-container">
                                <div class="skill-name <?php echo $name_class; ?>">
                                    <?php echo htmlspecialchars($skill['skill_name']); ?>
                                </div>
                                <div class="skill-type-badge">
                                    <?php echo htmlspecialchars($skill['skill_type']); ?>
                                </div>
                            </div>
                            <div class="skill-description">
                                <?php echo nl2br(htmlspecialchars($skill['skill_description'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>このカードに登録されているスキルはありません。</p>
            <?php endif; ?>
        </div>
    
        <div class="controls-container" style="justify-content: center; margin-top: 24px;">
            <div class="page-actions">
                <?php if ($GLOBALS['edit_mode_enabled']): ?>
                    <a href="edit.php?id=<?php echo $card['id']; ?>" class="action-button button-edit">このカードを編集する</a>
                <?php endif; ?>
                <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
            </div>
        </div>
    </div>

    <div class="related-info-sidebar">
        <div class="related-info-container">
            <h2 class="section-title">関連情報</h2>
            <div class="related-item-list">
                <div class="related-item-placeholder">
                    <span>ウマ娘ページへ</span>
                </div>
                <div class="related-item-placeholder">
                    <span>別バージョン(SSR)</span>
                </div>
                <div class="related-item-placeholder">
                    <span>別バージョン(SR)</span>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../templates/footer.php'; ?>
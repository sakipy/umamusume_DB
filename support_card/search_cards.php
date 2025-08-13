<?php
// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// ▼▼▼ このブロックを新しく追加 ▼▼▼
// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);
// ▲▲▲ ここまで ▲▲▲

// --- 絞り込み条件の受け取り ---
$sort_key = $_GET['sort'] ?? 'id_desc';
$filter_rarity = $_GET['rarity'] ?? '';
// (...これ以降のPHPコードは変更ありません...)
$filter_type = $_GET['type'] ?? '';
$search_keyword = $_GET['search'] ?? '';
$filter_skill_ids = $_GET['skill_ids'] ?? [];
$filter_friendship_min = (int)($_GET['friendship_min'] ?? 0);
$filter_race_bonus_min = (int)($_GET['race_bonus_min'] ?? 0);
$filter_training_effect_min = (int)($_GET['training_effect_min'] ?? 0);
$filter_specialty_rate_min = (int)($_GET['specialty_rate_min'] ?? 0);
$filter_speed_bonus_min = (int)($_GET['speed_bonus_min'] ?? 0);
$filter_stamina_bonus_min = (int)($_GET['stamina_bonus_min'] ?? 0);
$filter_power_bonus_min = (int)($_GET['power_bonus_min'] ?? 0);
$filter_guts_bonus_min = (int)($_GET['guts_bonus_min'] ?? 0);
$filter_wisdom_bonus_min = (int)($_GET['wisdom_bonus_min'] ?? 0);
$filter_initial_skill_point_min = (int)($_GET['initial_skill_point_bonus_min'] ?? 0);

$sql = "
    SELECT
        sc.id, sc.card_name, sc.rarity, sc.card_type, sc.image_url,
        MAX(CASE WHEN ce.effect_type = 'friendship_bonus' THEN ce.effect_value ELSE 0 END) as friendship_bonus,
        MAX(CASE WHEN ce.effect_type = 'race_bonus' THEN ce.effect_value ELSE 0 END) as race_bonus,
        MAX(CASE WHEN ce.effect_type = 'training_effect_up' THEN ce.effect_value ELSE 0 END) as training_effect_up,
        MAX(CASE WHEN ce.effect_type = 'specialty_rate_up' THEN ce.effect_value ELSE 0 END) as specialty_rate_up,
        MAX(CASE WHEN ce.effect_type = 'initial_bond' THEN ce.effect_value ELSE 0 END) as initial_bond,
        MAX(CASE WHEN ce.effect_type = 'skill_point_bonus' THEN ce.effect_value ELSE 0 END) as skill_point_bonus,
        MAX(CASE WHEN ce.effect_type = 'speed_bonus' THEN ce.effect_value ELSE 0 END) as speed_bonus,
        MAX(CASE WHEN ce.effect_type = 'stamina_bonus' THEN ce.effect_value ELSE 0 END) as stamina_bonus,
        MAX(CASE WHEN ce.effect_type = 'power_bonus' THEN ce.effect_value ELSE 0 END) as power_bonus,
        MAX(CASE WHEN ce.effect_type = 'guts_bonus' THEN ce.effect_value ELSE 0 END) as guts_bonus,
        MAX(CASE WHEN ce.effect_type = 'wisdom_bonus' THEN ce.effect_value ELSE 0 END) as wisdom_bonus,
        MAX(CASE WHEN ce.effect_type = 'initial_skill_point_bonus' THEN ce.effect_value ELSE 0 END) as initial_skill_point_bonus,
        GROUP_CONCAT(DISTINCT scs.skill_id) as owned_skill_ids
    FROM support_cards sc
    LEFT JOIN card_effects ce ON sc.id = ce.support_card_id AND ce.unlock_level = 4
    LEFT JOIN support_card_skills scs ON sc.id = scs.support_card_id
";
$where_clauses = []; $bind_params = []; $bind_types = '';
if (!empty($filter_rarity)) { $where_clauses[] = "sc.rarity = ?"; $bind_params[] = $filter_rarity; $bind_types .= 's'; }
if (!empty($filter_type)) { $where_clauses[] = "sc.card_type = ?"; $bind_params[] = $filter_type; $bind_types .= 's'; }
if (!empty($search_keyword)) { $katakana_keyword = mb_convert_kana($search_keyword, "C", "UTF-8"); $where_clauses[] = "sc.card_name LIKE ?"; $bind_params[] = "%" . $katakana_keyword . "%"; $bind_types .= 's'; }
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }

$sql .= " GROUP BY sc.id, sc.card_name, sc.image_url, sc.rarity, sc.card_type";

$having_clauses = [];
if (!empty($filter_skill_ids) && is_array($filter_skill_ids)) {
    foreach($filter_skill_ids as $skill_id) {
        if ((int)$skill_id > 0) {
            $having_clauses[] = "FIND_IN_SET(?, owned_skill_ids)";
            $bind_params[] = (int)$skill_id;
            $bind_types .= 'i';
        }
    }
}
if ($filter_friendship_min > 0) { $having_clauses[] = "friendship_bonus >= ?"; $bind_params[] = $filter_friendship_min; $bind_types .= 'i'; }
if ($filter_race_bonus_min > 0) { $having_clauses[] = "race_bonus >= ?"; $bind_params[] = $filter_race_bonus_min; $bind_types .= 'i'; }
if ($filter_training_effect_min > 0) { $having_clauses[] = "training_effect_up >= ?"; $bind_params[] = $filter_training_effect_min; $bind_types .= 'i'; }
if ($filter_specialty_rate_min > 0) { $having_clauses[] = "specialty_rate_up >= ?"; $bind_params[] = $filter_specialty_rate_min; $bind_types .= 'i'; }
if ($filter_speed_bonus_min > 0) { $having_clauses[] = "speed_bonus >= ?"; $bind_params[] = $filter_speed_bonus_min; $bind_types .= 'i'; }
if ($filter_stamina_bonus_min > 0) { $having_clauses[] = "stamina_bonus >= ?"; $bind_params[] = $filter_stamina_bonus_min; $bind_types .= 'i'; }
if ($filter_power_bonus_min > 0) { $having_clauses[] = "power_bonus >= ?"; $bind_params[] = $filter_power_bonus_min; $bind_types .= 'i'; }
if ($filter_guts_bonus_min > 0) { $having_clauses[] = "guts_bonus >= ?"; $bind_params[] = $filter_guts_bonus_min; $bind_types .= 'i'; }
if ($filter_wisdom_bonus_min > 0) { $having_clauses[] = "wisdom_bonus >= ?"; $bind_params[] = $filter_wisdom_bonus_min; $bind_types .= 'i'; }
if ($filter_initial_skill_point_min > 0) { $having_clauses[] = "initial_skill_point_bonus >= ?"; $bind_params[] = $filter_initial_skill_point_min; $bind_types .= 'i'; }

if (!empty($having_clauses)) { $sql .= " HAVING " . implode(' AND ', $having_clauses); }
$order_by_column = str_replace(['_desc', '_asc'], '', $sort_key);
$order_direction = str_ends_with($sort_key, '_asc') ? 'ASC' : 'DESC';
if ($order_by_column === 'name') { $sql .= " ORDER BY sc.card_name {$order_direction}"; } 
elseif ($order_by_column === 'id') { $sql .= " ORDER BY sc.id {$order_direction}"; } 
else { $sql .= " ORDER BY {$order_by_column} {$order_direction}, sc.id DESC"; }

$stmt = $conn->prepare($sql);
if (!empty($bind_params)) { $stmt->bind_param($bind_types, ...$bind_params); }
$stmt->execute();
$result = $stmt->get_result();

ob_start(); 
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
        $rarity_class = '';
        if ($row['rarity'] === 'SSR') { $rarity_class = 'rarity-ssr'; } 
        elseif ($row['rarity'] === 'SR') { $rarity_class = 'rarity-sr'; } 
        elseif ($row['rarity'] === 'R') { $rarity_class = 'rarity-r'; }
?>
        <div class="card-item <?php echo $rarity_class; ?>">
            <div class="card-image-wrapper">
                <?php if (!empty($row['image_url']) && file_exists($row['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['card_name']); ?>" class="card-image">
                <?php else: ?>
                    <div class="no-image">画像なし</div>
                <?php endif; ?>
            </div>
            <h3 class="card-name">
                <a href="view.php?id=<?php echo $row['id']; ?>" class="stretched-link"><?php echo htmlspecialchars($row['card_name']); ?></a>
            </h3>
            <div class="card-actions">
                <?php if ($edit_mode_enabled): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['card_name']); ?>">削除</a>
                <?php endif; ?>
            </div>
        </div>
<?php 
    endwhile;
else:
?>
    <p>条件に合うカードはありません。</p>
<?php 
endif;
$card_html = ob_get_clean();

// --- 2. 適用中フィルターのHTMLを生成 ---
$active_filters = [];
if (!empty($filter_skill_ids) && is_array($filter_skill_ids)) {
    $all_skills_map = [];
    $skills_result_for_badge = $conn->query("SELECT id, skill_name FROM skills");
    while($row = $skills_result_for_badge->fetch_assoc()) {
        $all_skills_map[$row['id']] = $row['skill_name'];
    }
    $skill_names = [];
    foreach($filter_skill_ids as $skill_id){
        if(isset($all_skills_map[$skill_id])) {
            $skill_names[] = $all_skills_map[$skill_id];
        }
    }
    if(!empty($skill_names)){
        $active_filters[] = "スキル: " . implode(', ', $skill_names);
    }
}
if ($filter_friendship_min > 0) { $active_filters[] = "友情ボーナス: {$filter_friendship_min}以上"; }
if ($filter_race_bonus_min > 0) { $active_filters[] = "レースボーナス: {$filter_race_bonus_min}以上"; }
if ($filter_training_effect_min > 0) { $active_filters[] = "トレーニング効果: {$filter_training_effect_min}以上"; }
if ($filter_specialty_rate_min > 0) { $active_filters[] = "得意率: {$filter_specialty_rate_min}以上"; }
if ($filter_speed_bonus_min > 0) { $active_filters[] = "スピードボーナス: {$filter_speed_bonus_min}以上"; }
if ($filter_stamina_bonus_min > 0) { $active_filters[] = "スタミナボーナス: {$filter_stamina_bonus_min}以上"; }
if ($filter_power_bonus_min > 0) { $active_filters[] = "パワーボーナス: {$filter_power_bonus_min}以上"; }
if ($filter_guts_bonus_min > 0) { $active_filters[] = "根性ボーナス: {$filter_guts_bonus_min}以上"; }
if ($filter_wisdom_bonus_min > 0) { $active_filters[] = "賢さボーナス: {$filter_wisdom_bonus_min}以上"; }
if ($filter_initial_skill_point_min > 0) { $active_filters[] = "初期スキルPt: {$filter_initial_skill_point_min}以上"; }
ob_start();
if (!empty($active_filters)):
?>
    <span>適用中の条件:</span>
    <?php foreach ($active_filters as $filter_text): ?>
        <span class="filter-badge"><?php echo htmlspecialchars($filter_text); ?></span>
    <?php endforeach; ?>
<?php
endif;
$badge_html = ob_get_clean();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['card_html' => $card_html, 'badge_html' => $badge_html]);
?>
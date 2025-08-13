<?php
// ========== データベース接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);

// --- 絞り込み条件の受け取り ---
$search_keyword = $_GET['search'] ?? '';
$filter_distance = $_GET['distance'] ?? '';
$filter_strategy = $_GET['strategy'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_surface = $_GET['surface'] ?? '';
$sort_key = $_GET['sort'] ?? 'id_desc';

// --- SQLのWHERE句を動的に組み立て ---
$where_clauses = []; $bind_params = []; $bind_types = '';
if (!empty($search_keyword)) {
    $hiragana_keyword = mb_convert_kana($search_keyword, "c", "UTF-8");
    $katakana_keyword = mb_convert_kana($search_keyword, "C", "UTF-8");
    if ($hiragana_keyword === $katakana_keyword) {
        $where_clauses[] = "skill_name LIKE ?";
        $bind_params[] = "%" . $search_keyword . "%";
        $bind_types .= 's';
    } else {
        $where_clauses[] = "(skill_name LIKE ? OR skill_name LIKE ?)";
        $bind_params[] = "%" . $hiragana_keyword . "%";
        $bind_params[] = "%" . $katakana_keyword . "%";
        $bind_types .= 'ss';
    }
}
if (!empty($filter_distance)) {
    if ($filter_distance === 'none') { $where_clauses[] = "(distance_type IS NULL OR distance_type = '')"; } 
    else { $where_clauses[] = "(FIND_IN_SET(?, distance_type) OR distance_type IS NULL OR distance_type = '')"; $bind_params[] = $filter_distance; $bind_types .= 's'; }
}
if (!empty($filter_strategy)) {
    if ($filter_strategy === 'none') { $where_clauses[] = "(strategy_type IS NULL OR strategy_type = '')"; } 
    else { $where_clauses[] = "(FIND_IN_SET(?, strategy_type) OR strategy_type IS NULL OR strategy_type = '')"; $bind_params[] = $filter_strategy; $bind_types .= 's'; }
}
if (!empty($filter_type)) {
    $where_clauses[] = "skill_type = ?";
    $bind_params[] = $filter_type;
    $bind_types .= 's';
}
if (!empty($filter_surface)) {
    if ($filter_surface === 'none') { $where_clauses[] = "(surface_type IS NULL OR surface_type = '')"; } 
    else { $where_clauses[] = "(FIND_IN_SET(?, surface_type) OR surface_type IS NULL OR surface_type = '')"; $bind_params[] = $filter_surface; $bind_types .= 's'; }
}

$sql = "SELECT * FROM skills";
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
if ($sort_key === 'name_asc') { $sql .= " ORDER BY skill_name ASC"; } 
else { $sql .= " ORDER BY id DESC"; }
$stmt = $conn->prepare($sql);
if (!empty($bind_params)) { $stmt->bind_param($bind_types, ...$bind_params); }
$stmt->execute();
$result = $stmt->get_result();

// --- 1. スキルカード一覧のHTMLを生成 ---
ob_start();
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
        $list_item_class = 'skill-list-item';
        $name_class = '';
        if ($row['skill_type'] == 'レアスキル') { $list_item_class .= ' type-rare'; $name_class = 'text-rare'; } 
        elseif ($row['skill_type'] == '進化スキル') { $list_item_class .= ' type-evolution'; $name_class = 'text-evolution'; }
        elseif ($row['skill_type'] == '固有スキル') { $list_item_class .= ' type-unique'; $name_class = 'text-rainbow'; }
        
        $tags = [];
        if (!empty($row['distance_type'])) { $tags[] = str_replace(',', '・', $row['distance_type']); }
        if (!empty($row['strategy_type'])) { $tags[] = str_replace(',', '・', $row['strategy_type']); }
        if (!empty($row['surface_type'])) { $tags[] = str_replace(',', '・', $row['surface_type']); }
        $tags_html = !empty($tags) ? implode(' / ', $tags) : '';
?>
        <div class="<?php echo $list_item_class; ?>">
            <a href="view.php?id=<?php echo $row['id']; ?>" class="skill-card-link"></a>
            <div class="skill-content">
                <div class="skill-header">
                    <span class="skill-name <?php echo $name_class; ?>">
                        <?php echo htmlspecialchars($row['skill_name']); ?>
                    </span>
                    <span class="skill-tags"><?php echo $tags_html; ?></span>
                </div>
                <div class="skill-description">
                    <?php echo nl2br(htmlspecialchars($row['skill_description'])); ?>
                </div>
                <div class="skill-source-placeholder">
                    <p style="margin:0;">（ここに所持ウマ娘の情報が将来的に表示されます）</p>
                </div>
            </div>
            <div class="skill-actions">
                <?php if ($edit_mode_enabled): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['skill_name']); ?>">削除</a>
                <?php endif; ?>
            </div>
        </div>
<?php 
    endwhile;
else:
?>
    <p>条件に合うスキルはありません。</p>
<?php 
endif;
$skill_html = ob_get_clean();

// --- 2. 適用中フィルターのHTMLを生成 ---
$active_filters = [];
if (!empty($filter_distance)) { $active_filters[] = "距離: " . ($filter_distance == 'none' ? '指定なし' : $filter_distance); }
if (!empty($filter_strategy)) { $active_filters[] = "脚質: " . ($filter_strategy == 'none' ? '指定なし' : $filter_strategy); }
if (!empty($filter_type)) { $active_filters[] = "タイプ: " . $filter_type; }
if (!empty($filter_surface)) { $active_filters[] = "馬場: " . ($filter_surface == 'none' ? '指定なし' : $filter_surface); }

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

// --- 3. 2種類のHTMLをJSON形式で出力 ---
header('Content-Type: application/json');
echo json_encode([
    'skill_html' => $skill_html,
    'badge_html' => $badge_html
]);
?>
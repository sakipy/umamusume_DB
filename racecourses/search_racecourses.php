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
$search_name = $_GET['search_name'] ?? '';
$search_location = $_GET['search_location'] ?? '';
$filter_directions = $_GET['direction'] ?? [];
$filter_courses = $_GET['course_type'] ?? [];
$filter_surfaces = $_GET['surface'] ?? [];
$sort_key = $_GET['sort'] ?? 'id_desc';

// --- SQLのWHERE句を動的に組み立て ---
$where_clauses = [];
$bind_params = [];
$bind_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "name LIKE ?";
    $bind_params[] = "%" . $search_name . "%";
    $bind_types .= 's';
}
if (!empty($search_location)) {
    $where_clauses[] = "location LIKE ?";
    $bind_params[] = "%" . $search_location . "%";
    $bind_types .= 's';
}

if (!empty($filter_directions) && is_array($filter_directions)) {
    foreach ($filter_directions as $direction) {
        $where_clauses[] = "FIND_IN_SET(?, turning_direction)";
        $bind_params[] = $direction;
        $bind_types .= 's';
    }
}
if (!empty($filter_courses) && is_array($filter_courses)) {
    foreach ($filter_courses as $course) {
        $where_clauses[] = "FIND_IN_SET(?, course_type)";
        $bind_params[] = $course;
        $bind_types .= 's';
    }
}
if (!empty($filter_surfaces) && is_array($filter_surfaces)) {
    foreach ($filter_surfaces as $surface) {
        $where_clauses[] = "FIND_IN_SET(?, surface)";
        $bind_params[] = $surface;
        $bind_types .= 's';
    }
}

// --- SQLクエリの組み立て ---
$sql = "SELECT * FROM racecourses";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// 並べ替え
if ($sort_key === 'name_asc') { $sql .= " ORDER BY name ASC"; } 
else { $sql .= " ORDER BY id DESC"; }

$stmt = $conn->prepare($sql);
if (!empty($bind_params)) { $stmt->bind_param($bind_types, ...$bind_params); }
$stmt->execute();
$result = $stmt->get_result();
$conn->close();

// --- 1. カード一覧のHTMLを生成 ---
ob_start();
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
?>
        <div class="racecourse-card-item">
            <a href="view.php?id=<?php echo $row['id']; ?>">
                <?php if (!empty($row['image_url']) && file_exists($row['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="racecourse-card-image">
                <?php else: ?>
                    <div class="racecourse-card-image" style="display: flex; align-items: center; justify-content: center; color: #aaa;">画像なし</div>
                <?php endif; ?>
            </a>
            <div class="racecourse-card-content">
                <div>
                    <h3 class="racecourse-card-name">
                        <a href="view.php?id=<?php echo $row['id']; ?>" class="stretched-link"><?php echo htmlspecialchars($row['name']); ?></a>
                    </h3>
                    <p class="racecourse-card-location"><strong>所在地:</strong> <?php echo htmlspecialchars($row['location'] ?: '未登録'); ?></p>
                    <p class="racecourse-card-location">
                        <strong>情報:</strong> 
                        <?php 
                            $info = [];
                            if (!empty($row['turning_direction'])) $info[] = $row['turning_direction'];
                            if (!empty($row['course_type'])) $info[] = $row['course_type'];
                            if (!empty($row['surface'])) $info[] = $row['surface'];
                            echo htmlspecialchars(implode(' / ', $info) ?: '未登録');
                        ?>
                    </p>
                </div>
                <p class="racecourse-card-description"><?php echo nl2br(htmlspecialchars(mb_strimwidth($row['description'], 0, 80, '...'))); ?></p>
                <div class="racecourse-card-actions">
                    <?php if ($edit_mode_enabled): ?>
                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                        <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['name']); ?>">削除</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php 
    endwhile;
else:
?>
    <p>条件に合う競馬場はありません。</p>
<?php 
endif;
$card_html = ob_get_clean();

// --- 2. 適用中フィルターのHTMLを生成 ---
$active_filters = [];
if (!empty($filter_directions)) { $active_filters[] = "回り方向: " . implode(', ', $filter_directions); }
if (!empty($filter_courses)) { $active_filters[] = "コース: " . implode(', ', $filter_courses); }
if (!empty($filter_surfaces)) { $active_filters[] = "馬場: " . implode(', ', $filter_surfaces); }

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

// --- 3. 結果をJSONで出力 ---
header('Content-Type: application/json');
echo json_encode([
    'card_html' => $card_html,
    'badge_html' => $badge_html
]);
?>
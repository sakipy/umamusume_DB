<?php
// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);

// --- 絞り込み条件の受け取り ---
$search_name = $_GET['search_name'] ?? '';
$racecourse_id = (int)($_GET['racecourse_id'] ?? 0);
$grade = $_GET['grade'] ?? '';
$surface = $_GET['surface'] ?? '';

// --- SQLのWHERE句を動的に組み立て ---
$where_clauses = []; $bind_params = []; $bind_types = '';

// 必ず画像が存在するものを対象にする
$where_clauses[] = "r.image_url IS NOT NULL AND r.image_url != ''";

if (!empty($search_name)) {
    $where_clauses[] = "r.race_name LIKE ?";
    $bind_params[] = "%" . $search_name . "%";
    $bind_types .= 's';
}
if ($racecourse_id > 0) {
    $where_clauses[] = "r.racecourse_id = ?";
    $bind_params[] = $racecourse_id;
    $bind_types .= 'i';
}
if (!empty($grade)) {
    $where_clauses[] = "r.grade = ?";
    $bind_params[] = $grade;
    $bind_types .= 's';
}
if (!empty($surface)) {
    $where_clauses[] = "r.surface = ?";
    $bind_params[] = $surface;
    $bind_types .= 's';
}

// --- SQLクエリの組み立て (gradeカラムを追加) ---
$sql = "SELECT r.id, r.race_name, r.image_url, r.grade FROM races r";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY r.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($bind_params)) { $stmt->bind_param($bind_types, ...$bind_params); }
$stmt->execute();
$result = $stmt->get_result();

ob_start();
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
        $grade_class = '';
        if (!empty($row['grade'])) {
            $grade_class = 'grade-' . strtolower(str_replace('pn', '', $row['grade']));
        }
?>
        <div class="race-card-item <?php echo $grade_class; ?>">
            <a href="view.php?id=<?php echo $row['id']; ?>" class="stretched-link"></a>
            <div class="race-card-image-wrapper">
                <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['race_name']); ?>">
            </div>
            <div class="race-card-info">
                <h3 class="race-card-name"><?php echo htmlspecialchars($row['race_name']); ?></h3>
            </div>
            
            <div class="race-card-actions">
                <?php if ($edit_mode_enabled): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['race_name']); ?>">削除</a>
                <?php endif; ?>
            </div>
            </div>
<?php 
    endwhile;
else:
    echo '<p style="text-align: center; grid-column: 1 / -1; padding: 30px;">条件に合うレースは見つかりませんでした。</p>';
endif;

$list_html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['list_html' => $list_html]);
?>
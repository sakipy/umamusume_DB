<?php
// ========== データベース接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);

// ★★★ 変更点：名前を分割する関数をこちらにも追加 ★★★
function splitCharacterName($fullName) {
    $prefixes = [];
    $main = $fullName;
    if (preg_match('/(.*)(【(.+?)】|\((.+?)\))$/u', $main, $matches)) {
        $main = trim($matches[1]);
        $suffixContent = !empty($matches[3]) ? $matches[3] : $matches[4];
        array_unshift($prefixes, $suffixContent);
    }
    if (preg_match('/^([\[【](.+?)[\]】])(.*)/u', $main, $matches)) {
        $main = trim($matches[3]);
        $prefixes[] = $matches[2];
    }
    $prefix_words = ['水着'];
    foreach ($prefix_words as $p) {
        if (strpos($main, $p) === 0) {
            if (!in_array($p, $prefixes)) {
                 $prefixes[] = $p;
            }
            $main = trim(substr($main, strlen($p)));
            break;
        }
    }
    return ['prefix' => implode(' ', $prefixes), 'main' => trim($main)];
}

// --- (既存の絞り込み処理は変更なし) ---
$search_name = $_GET['search_name'] ?? '';
$rarity = $_GET['rarity'] ?? '';
$sort_key = $_GET['sort'] ?? 'id_desc';
// (以下、適性や成長率の絞り込み処理...)
$aptitudes = [
    'apt_turf' => 'surface_aptitude_turf', 'apt_dirt' => 'surface_aptitude_dirt',
    'apt_short' => 'distance_aptitude_short', 'apt_mile' => 'distance_aptitude_mile',
    'apt_medium' => 'distance_aptitude_medium', 'apt_long' => 'distance_aptitude_long',
    'apt_runner' => 'strategy_aptitude_runner', 'apt_leader' => 'strategy_aptitude_leader',
    'apt_chaser' => 'strategy_aptitude_chaser', 'apt_trailer' => 'strategy_aptitude_trailer'
];
$growth_filters = $_GET['growth'] ?? [];
$where_clauses = [];
$bind_params = [];
$bind_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "character_name LIKE ?";
    $bind_params[] = "%" . $search_name . "%";
    $bind_types .= 's';
}
if (!empty($rarity)) {
    $where_clauses[] = "rarity = ?";
    $bind_params[] = $rarity;
    $bind_types .= 'i';
}
foreach ($aptitudes as $key => $column) {
    if (!empty($_GET[$key])) {
        $where_clauses[] = "$column <= ?";
        $bind_params[] = $_GET[$key];
        $bind_types .= 's';
    }
}
if (!empty($growth_filters)) {
    foreach ($growth_filters as $status) {
        $column = 'growth_rate_' . $status;
        if (in_array($column, ['growth_rate_speed', 'growth_rate_stamina', 'growth_rate_power', 'growth_rate_guts', 'growth_rate_wisdom'])) {
             $where_clauses[] = "$column > 0";
        }
    }
}
$sql = "SELECT id, character_name, rarity, image_url, image_url_suit FROM characters";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
if ($sort_key === 'name_asc') { $sql .= " ORDER BY character_name ASC"; } 
else { $sql .= " ORDER BY id DESC"; }

$stmt = $conn->prepare($sql);
if (!empty($bind_params)) { $stmt->bind_param($bind_types, ...$bind_params); }
$stmt->execute();
$result = $stmt->get_result();

// --- カード一覧のHTMLを生成 ---
ob_start();
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
        $rarity_class = 'rarity-' . $row['rarity'];
?>
        <div class="character-card-item <?php echo $rarity_class; ?>">
            <a href="view.php?id=<?php echo $row['id']; ?>" class="stretched-link"></a>
            
            <div class="character-card-image-wrapper">
                <?php if (!empty($row['image_url_suit']) && file_exists('../' . $row['image_url_suit'])): ?>
                    <img src="../<?php echo htmlspecialchars($row['image_url_suit']); ?>" alt="<?php echo htmlspecialchars($row['character_name']); ?>">
                <?php else: ?>
                    <div class="no-image">画像なし</div>
                <?php endif; ?>
            </div>
            <div class="character-card-info">
                <span class="character-card-rarity"><?php echo str_repeat('★', $row['rarity']); ?></span>
                <h3 class="character-card-name">
                    <?php
                        $name_parts = splitCharacterName($row['character_name']);
                    ?>
                    <span class="char-name-prefix">
                        <?php echo htmlspecialchars($name_parts['prefix']) ?: '&nbsp;'; ?>
                    </span>
                    <span class="char-name-main">
                        <?php echo htmlspecialchars($name_parts['main']); ?>
                    </span>
                </h3>
            </div>
            <div class="character-card-actions">
                <?php if ($edit_mode_enabled): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['character_name']); ?>">削除</a>
                <?php endif; ?>
            </div>
        </div>
<?php 
    endwhile;
else:
?>
    <p>条件に合うウマ娘は見つかりませんでした。</p>
<?php 
endif;
$card_html = ob_get_clean();

$badge_html = ''; 

// --- 結果をJSONで出力 ---
$conn->close();
header('Content-Type: application/json');
echo json_encode([
    'card_html' => $card_html,
    'badge_html' => $badge_html
]);
?>
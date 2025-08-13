<?php
// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);

// ▼▼▼ ランクからCSSクラスを生成する関数を追加 ▼▼▼
function get_rank_class($rank) {
    $base_rank = preg_replace('/[0-9+]/', '', $rank);
    return 'rank-' . strtolower($base_rank);
}

// --- SQLクエリの組み立て ---
$sql = "
    SELECT 
        tu.*, 
        c.character_name, 
        p.face_image_url
    FROM trained_umamusume tu
    JOIN characters c ON tu.character_id = c.id
    LEFT JOIN pokedex p ON c.pokedex_id = p.id
    ORDER BY tu.id DESC
";
$result = $conn->query($sql);

ob_start();
if ($result && $result->num_rows > 0):
    while($row = $result->fetch_assoc()):
?>
        <div class="trained-card-item">
            <a href="view.php?id=<?php echo $row['id']; ?>" class="stretched-link"></a>
            <div class="trained-card-header">
                <div class="trained-card-face-icon">
                     <?php if (!empty($row['face_image_url']) && file_exists('../' . $row['face_image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($row['face_image_url']); ?>" alt="">
                    <?php else: ?>
                        <div class="no-image" style="width:100%; height:100%; background:#eee;"></div>
                    <?php endif; ?>
                </div>
                <div class="trained-card-name-block">
                    <div class="trained-card-character-name"><?php echo htmlspecialchars($row['character_name']); ?></div>
                    <div class="trained-card-nickname"><?php echo htmlspecialchars($row['nickname'] ?: ' '); ?></div>
                </div>
            </div>
            <div class="trained-card-body">
                <div class="trained-card-evaluation">
                    <?php // ▼▼▼ eval-rankにクラスを追加 ▼▼▼ ?>
                    <span class="eval-rank <?php echo get_rank_class($row['evaluation_rank']); ?>"><?php echo htmlspecialchars($row['evaluation_rank'] ?: '-'); ?></span>
                    <span class="eval-score"><?php echo number_format($row['evaluation_score']); ?></span>
                </div>
                <div class="trained-card-stats">
                    <span class="stat-label">スピ</span><span class="stat-label">スタ</span><span class="stat-label">パワ</span><span class="stat-label">根性</span><span class="stat-label">賢さ</span>
                    <span class="stat-value"><?php echo $row['speed']; ?></span>
                    <span class="stat-value"><?php echo $row['stamina']; ?></span>
                    <span class="stat-value"><?php echo $row['power']; ?></span>
                    <span class="stat-value"><?php echo $row['guts']; ?></span>
                    <span class="stat-value"><?php echo $row['wisdom']; ?></span>
                </div>
            </div>
            <div class="trained-card-footer">
                <?php if ($edit_mode_enabled): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="action-button button-edit">編集</a>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($row['character_name']); ?>">削除</a>
                <?php endif; ?>
            </div>
        </div>
<?php 
    endwhile;
else:
    echo '<p style="text-align: center; grid-column: 1 / -1; padding: 30px;">まだ育成ウマ娘が登録されていません。</p>';
endif;

$list_html = ob_get_clean();
$conn->close();
header('Content-Type: application/json');
echo json_encode(['list_html' => $list_html]);
?>
<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘図鑑';
$current_page = 'pokedex';
$base_path = '../';

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- 編集モード設定の読み込み ---
$result_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
$edit_mode_enabled = ($result_settings && $result_settings->fetch_assoc()['setting_value'] == 1);

// --- 全キャラクターをカテゴリ別に取得 ---
$sql = "SELECT * FROM pokedex ORDER BY category, id ASC";
$result = $conn->query($sql);
$characters_by_category = [
    '実装済み' => [],
    '未実装' => [],
    'トレセン関係者' => []
];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $characters_by_category[$row['category']][] = $row;
    }
}
$conn->close();

include '../templates/header.php';
?>

<div class="container full-width">
    <div class="controls-container">
        <div class="page-actions">
            <a href="add.php" class="add-link">新しい図鑑データを追加する</a>
        </div>
    </div>
    
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="tab-container">
        <div class="tab-item active" data-tab="jissou">実装済み (<?php echo count($characters_by_category['実装済み']); ?>)</div>
        <div class="tab-item" data-tab="mijissou">未実装 (<?php echo count($characters_by_category['未実装']); ?>)</div>
        <div class="tab-item" data-tab="kankeisha">トレセン関係者 (<?php echo count($characters_by_category['トレセン関係者']); ?>)</div>
    </div>

    <?php foreach ($characters_by_category as $category => $characters): ?>
        <?php
            $tab_id = '';
            if ($category === '実装済み') $tab_id = 'jissou';
            elseif ($category === '未実装') $tab_id = 'mijissou';
            elseif ($category === 'トレセン関係者') $tab_id = 'kankeisha';
        ?>
        <div id="<?php echo $tab_id; ?>" class="tab-content <?php if ($category === '実装済み') echo 'active'; ?>">
            <div class="pokedex-grid">
                <?php if (!empty($characters)): ?>
                    <?php foreach ($characters as $character): ?>
                        <div class="pokedex-card">
                            <div class="pokedex-card-main">
                                <div class="pokedex-card-image-wrapper">
                                    <?php if (!empty($character['face_image_url']) && file_exists('../' . $character['face_image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($character['face_image_url']); ?>" alt="<?php echo htmlspecialchars($character['pokedex_name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">画像なし</div>
                                    <?php endif; ?>
                                </div>
                                <div class="pokedex-card-name">
                                    <?php echo htmlspecialchars($character['pokedex_name']); ?>
                                </div>
                                <a href="view.php?id=<?php echo $character['id']; ?>" class="pokedex-card-link"></a>
                            </div>
                            
                            <?php if ($edit_mode_enabled): ?>
                                <div class="pokedex-card-actions">
                                    <a href="edit.php?id=<?php echo $character['id']; ?>" class="action-button button-edit">編集</a>
                                    <a href="delete.php?id=<?php echo $character['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($character['pokedex_name']); ?>">削除</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>このカテゴリに登録されているキャラクターはいません。</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-item');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(item => item.classList.remove('active'));
            contents.forEach(content => content.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tab.dataset.tab).classList.add('active');
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
<?php
// ========== ページ設定 ==========
$page_title = '図鑑詳細';
$current_page = 'pokedex';
$base_path = '../';

// ========== IDの検証 ==========
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) {
    header("Location: index.php");
    exit;
}

// ========== DB接続とデータ取得 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// === 1. メインの図鑑情報を取得 ===
$stmt = $conn->prepare("SELECT * FROM pokedex WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entry) { 
    $conn->close();
    die("指定されたデータが見つかりません。"); 
}
$page_title = '図鑑: ' . htmlspecialchars($entry['pokedex_name']);

// === 2. 関連する「育成ウマ娘」を取得 ===
$related_characters = [];
$stmt_chars = $conn->prepare("SELECT id, character_name, rarity, image_url_suit FROM characters WHERE pokedex_id = ?");
$stmt_chars->bind_param("i", $id);
$stmt_chars->execute();
$result_chars = $stmt_chars->get_result();
while($row = $result_chars->fetch_assoc()) {
    $related_characters[] = $row;
}
$stmt_chars->close();

// ▼▼▼【ここを修正】関連サポートカードの取得方法を直接参照に変更 ▼▼▼
$related_support_cards = [];
$stmt_cards = $conn->prepare("SELECT id, card_name, image_url FROM support_cards WHERE pokedex_id = ?");
$stmt_cards->bind_param("i", $id);
$stmt_cards->execute();
$result_cards = $stmt_cards->get_result();
while($row = $result_cards->fetch_assoc()) {
    $related_support_cards[] = $row;
}
$stmt_cards->close();
// ▲▲▲【修正ここまで】▲▲▲

$conn->close();

include '../templates/header.php';
?>
<style>
    .related-info-sidebar { width: 280px; flex-shrink: 0; }
    .related-info-sidebar .related-item-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 12px; }
    .related-info-sidebar .related-item { text-decoration: none; color: #333; text-align: center; display: block; }
    .related-info-sidebar .related-item img { width: 100%; height: auto; aspect-ratio: 5 / 7; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; background-color: #fff; }
    .related-info-sidebar .related-item span { font-size: 12px; display: block; margin-top: 5px; line-height: 1.2; }
    .related-info-sidebar .related-item-no-image { width: 100%; aspect-ratio: 5 / 7; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #888; }
</style>

<div class="page-wrapper-with-sidebar">
    <div class="container main-content-area">
        <div class="pokedex-view-header">
            <?php if (!empty($entry['face_image_url']) && file_exists('../' . $entry['face_image_url'])): ?>
                <div class="pokedex-face-icon">
                    <img src="../<?php echo htmlspecialchars($entry['face_image_url']); ?>" alt="顔写真">
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($entry['pokedex_name']); ?></h1>
            <span class="pokedex-cv">CV. <?php echo htmlspecialchars($entry['cv'] ?: '未登録'); ?></span>
        </div>

        <div class="pokedex-details-grid">
            <div class="pokedex-left-column">
                <div class="image-toggle">
                    <button type="button" class="button-secondary active" data-image="winning_outfit">勝負服</button>
                    <button type="button" class="button-secondary" data-image="uniform">制服</button>
                </div>
                <div class="image-wrapper" style="aspect-ratio: 2/3;">
                    <?php
                        $has_winning_outfit = !empty($entry['winning_outfit_image_url']) && file_exists('../' . $entry['winning_outfit_image_url']);
                        $has_uniform = !empty($entry['uniform_image_url']) && file_exists('../' . $entry['uniform_image_url']);
                    ?>
                    <?php if ($has_winning_outfit): ?>
                        <img id="image-winning_outfit" class="character-view-image active" src="../<?php echo htmlspecialchars($entry['winning_outfit_image_url']); ?>">
                    <?php else: ?>
                        <div id="image-winning_outfit" class="no-image active" style="height: 100%;">勝負服画像なし</div>
                    <?php endif; ?>
                    <?php if ($has_uniform): ?>
                        <img id="image-uniform" class="character-view-image" src="../<?php echo htmlspecialchars($entry['uniform_image_url']); ?>">
                    <?php else: ?>
                         <img id="image-uniform" class="character-view-image" style="display:none;">
                    <?php endif; ?>
                </div>
            </div>
            <div class="pokedex-right-column">
                 <div class="details-info-box" style="padding: 20px 25px;">
                    <h2 class="section-title">プロフィール</h2>
                    <div class="info-grid">
                        <p><strong>誕生日</strong></p><p><?php echo htmlspecialchars($entry['birthday'] ?: '未登録'); ?></p>
                        <p><strong>身長</strong></p><p><?php echo htmlspecialchars($entry['height'] ?: '未登録'); ?></p>
                        <p><strong>体重</strong></p><p><?php echo htmlspecialchars($entry['weight'] ?: '未登録'); ?></p>
                        <p><strong>スリーサイズ</strong></p><p><?php echo htmlspecialchars($entry['three_sizes'] ?: '未登録'); ?></p>
                    </div>
                    <h2 class="section-title" style="margin-top: 24px;">説明</h2>
                    <p class="description-text"><?php echo nl2br(htmlspecialchars($entry['description'] ?: '未登録')); ?></p>
                </div>
            </div>
        </div>
        
        <div class="controls-container" style="justify-content: center; flex-direction: column; gap: 10px; margin-top: 30px;">
            <div class="page-actions" style="margin-right: 0;">
                <?php if (!empty($GLOBALS['edit_mode_enabled']) && $GLOBALS['edit_mode_enabled']): ?>
                    <a href="edit.php?id=<?php echo $entry['id']; ?>" class="action-button button-edit">このデータを編集する</a>
                    <a href="delete.php?id=<?php echo $entry['id']; ?>" class="action-button button-delete delete-link" data-item-name="<?php echo htmlspecialchars($entry['pokedex_name']); ?>">削除する</a>
                <?php endif; ?>
            </div>
            <a href="index.php" class="back-link" style="margin: 0;">&laquo; 図鑑一覧に戻る</a>
        </div>
    </div>

    <div class="related-info-sidebar">
        <div class="related-info-container">
            <h2 class="section-title">関連育成ウマ娘</h2>
            <div class="related-item-list">
                <?php if (!empty($related_characters)): ?>
                    <?php foreach ($related_characters as $char): ?>
                        <a href="../characters/view.php?id=<?php echo $char['id']; ?>" class="related-item">
                            <?php if (!empty($char['image_url_suit']) && file_exists('../' . $char['image_url_suit'])): ?>
                                <img src="../<?php echo htmlspecialchars($char['image_url_suit']); ?>" alt="<?php echo htmlspecialchars($char['character_name']); ?>">
                            <?php else: ?>
                                <div class="related-item-no-image">画像なし</div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($char['character_name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="related-item-placeholder"><span>関連ウマ娘なし</span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="related-info-container">
            <h2 class="section-title">関連サポートカード</h2>
            <div class="related-item-list">
                <?php if (!empty($related_support_cards)): ?>
                    <?php foreach ($related_support_cards as $card): ?>
                        <a href="../support_card/view.php?id=<?php echo $card['id']; ?>" class="related-item">
                            <?php if (!empty($card['image_url']) && file_exists($card['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['card_name']); ?>">
                            <?php else: ?>
                                <div class="related-item-no-image">画像なし</div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($card['card_name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="related-item-placeholder"><span>関連カードなし</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageToggleButtons = document.querySelectorAll('.image-toggle button');
    const uniformButton = document.querySelector('button[data-image="uniform"]');
    
    // 制服画像がなければボタンを非表示にする
    if (!document.getElementById('image-uniform').src) {
        if(uniformButton) uniformButton.style.display = 'none';
    }

    imageToggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const imageType = this.dataset.image;
            imageToggleButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.character-view-image, .no-image').forEach(img => {
                img.classList.remove('active');
            });
            document.getElementById('image-' + imageType).classList.add('active');
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
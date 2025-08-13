<?php
// ========== ページ設定 ==========
$page_title = '育成ウマ娘 詳細';
$current_page = 'trained_umamusume';
$base_path = '../';

// ========== DB接続とデータ取得 ==========
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 育成ウマ娘の基本情報と、元ウマ娘の情報をJOINして取得
$stmt = $conn->prepare("
    SELECT tu.*, c.character_name, p.face_image_url 
    FROM trained_umamusume tu 
    JOIN characters c ON tu.character_id = c.id 
    LEFT JOIN pokedex p ON c.pokedex_id = p.id
    WHERE tu.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$trained = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$trained) { die("指定されたデータが見つかりません。"); }
$page_title = '詳細: ' . htmlspecialchars($trained['character_name']);

// 取得スキル情報を取得
$skills = [];
$stmt_skills = $conn->prepare("
    SELECT s.* FROM skills s 
    JOIN trained_umamusume_skills tus ON s.id = tus.skill_id 
    WHERE tus.trained_umamusume_id = ?
");
$stmt_skills->bind_param("i", $id);
$stmt_skills->execute();
$result_skills = $stmt_skills->get_result();
while ($row = $result_skills->fetch_assoc()) {
    $skills[] = $row;
}
$stmt_skills->close();
$conn->close();

// ▼▼▼ ランクからCSSクラスを生成する関数を追加 ▼▼▼
function get_rank_class($rank) {
    $base_rank = preg_replace('/[0-9+]/', '', $rank);
    return 'rank-' . strtolower($base_rank);
}

include '../templates/header.php';
?>

<div class="page-wrapper-with-sidebar">
    <div class="container main-content-area">
        <div class="pokedex-view-header">
            <?php if (!empty($trained['face_image_url']) && file_exists('../' . $trained['face_image_url'])): ?>
                <div class="pokedex-face-icon">
                    <img src="../<?php echo htmlspecialchars($trained['face_image_url']); ?>" alt="顔写真">
                </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($trained['character_name']); ?></h1>
            <?php if ($trained['nickname']): ?>
                <span class="pokedex-cv" style="font-size: 1em; margin-left: 15px;">『<?php echo htmlspecialchars($trained['nickname']); ?>』</span>
            <?php endif; ?>
        </div>

        <div class="trained-view-grid">
            <div class="trained-view-left">
                <div class="trained-evaluation">
                    <?php // ▼▼▼ eval-rankにクラスを追加 ▼▼▼ ?>
                    <span class="eval-rank <?php echo get_rank_class($trained['evaluation_rank']); ?>"><?php echo htmlspecialchars($trained['evaluation_rank'] ?: '-'); ?></span>
                    <span class="eval-score"><?php echo number_format($trained['evaluation_score']); ?> pt</span>
                </div>
                <?php if (!empty($trained['screenshot_url']) && file_exists('../' . $trained['screenshot_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($trained['screenshot_url']); ?>" alt="スクリーンショット" class="trained-screenshot">
                <?php else: ?>
                    <div class="no-image" style="width: 100%; aspect-ratio: 9/16;">スクリーンショットなし</div>
                <?php endif; ?>
            </div>
            <div class="trained-view-right">
                <h2 class="section-title-bar">ステータス</h2>
                <div class="status-growth-grid view-mode">
                    <div class="grid-header">スピ</div><div class="grid-header">スタ</div><div class="grid-header">パワ</div><div class="grid-header">根性</div><div class="grid-header">賢さ</div>
                    <div class="grid-data"><?php echo $trained['speed']; ?></div>
                    <div class="grid-data"><?php echo $trained['stamina']; ?></div>
                    <div class="grid-data"><?php echo $trained['power']; ?></div>
                    <div class="grid-data"><?php echo $trained['guts']; ?></div>
                    <div class="grid-data"><?php echo $trained['wisdom']; ?></div>
                </div>

                <h2 class="section-title-bar">育成情報</h2>
                <div class="info-grid" style="grid-template-columns: 100px 1fr;">
                    <p><strong>育成完了日</strong></p><p><?php echo htmlspecialchars($trained['trained_date']); ?></p>
                </div>
                <p class="description-text" style="margin-top: 15px;"><strong>メモ:</strong><br><?php echo nl2br(htmlspecialchars($trained['memo'] ?: '未登録')); ?></p>
            </div>
        </div>

        <h2 class="section-title-bar">取得スキル一覧</h2>
        <?php if (!empty($skills)): ?>
            <table class="skill-table">
                <thead><tr><th>スキル情報</th><th>タイプ</th></tr></thead>
                <tbody>
                    <?php foreach ($skills as $skill): ?>
                        <?php
                            $row_class = ''; $name_class = '';
                            if ($skill['skill_type'] == 'レアスキル') { $row_class = 'type-rare'; $name_class = 'text-rare'; } 
                            elseif ($skill['skill_type'] == '進化スキル') { $row_class = 'type-evolution'; $name_class = 'text-evolution'; }
                            elseif ($skill['skill_type'] == '固有スキル') { $row_class = 'type-unique'; $name_class = 'text-rainbow'; }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <div class="<?php echo $name_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                                <div class="skill-description"><?php echo nl2br(htmlspecialchars($skill['skill_description'])); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($skill['skill_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>取得スキルは登録されていません。</p>
        <?php endif; ?>

        <div class="controls-container" style="justify-content: center; margin-top: 30px;">
            <div class="page-actions">
                <?php if ($GLOBALS['edit_mode_enabled']): ?>
                    <a href="edit.php?id=<?php echo $trained['id']; ?>" class="action-button button-edit">この育成記録を編集する</a>
                <?php endif; ?>
                <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
            </div>
        </div>
    </div>
    <div class="related-info-sidebar">
        <div class="related-info-container">
            <h2 class="section-title">関連ウマ娘</h2>
            <div class="related-item-list">
                <div class="related-item-placeholder"><span>（ウマ娘図鑑へ）</span></div>
            </div>
        </div>
    </div>
</div>
<?php include '../templates/footer.php'; ?>
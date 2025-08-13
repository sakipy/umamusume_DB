<?php
// ========== ページ設定 ==========
$page_title = 'レース詳細'; // 初期タイトル
$current_page = 'races';
$base_path = '../';

// ========== DB接続とデータ取得 ==========
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// 競馬場名もJOINして取得
$stmt = $conn->prepare("SELECT r.*, rc.name as racecourse_name FROM races r JOIN racecourses rc ON r.racecourse_id = rc.id WHERE r.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$race = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$race) { die("指定されたレースが見つかりません。"); }

// ★ ブラウザのタブに表示するタイトルをここで設定
$page_title = '詳細: ' . htmlspecialchars($race['race_name']);

include '../templates/header.php';
?>

<div class="container">
    <h1><?php echo htmlspecialchars($race['race_name']); ?></h1>

    <?php if (!empty($race['image_url']) && file_exists('../' . $race['image_url'])): ?>
        <div class="details-image" style="text-align: center; margin-bottom: 24px;">
            <img src="../<?php echo htmlspecialchars($race['image_url']); ?>" alt="<?php echo htmlspecialchars($race['race_name']); ?>" style="max-width: 100%; height: auto; border-radius: 8px;">
        </div>
    <?php endif; ?>

    <div class="details-info-box">
        <h2 class="section-title">レース情報</h2>
        <div class="info-grid">
            <p><strong>競馬場</strong></p>
            <p><a href="../racecourses/view.php?id=<?php echo $race['racecourse_id']; ?>"><?php echo htmlspecialchars($race['racecourse_name']); ?></a></p>
            
            <p><strong>グレード</strong></p>
            <p><?php echo htmlspecialchars($race['grade'] ?: 'なし'); ?></p>

            <p><strong>距離</strong></p>
            <p><?php echo htmlspecialchars($race['distance']); ?>m</p>

            <p><strong>バ場</strong></p>
            <p><?php echo htmlspecialchars($race['surface']); ?></p>

            <p><strong>回り</strong></p>
            <p><?php echo htmlspecialchars($race['turning_direction']); ?></p>
        </div>
        
        <h2 class="section-title">説明・備考</h2>
        <p class="description-text"><?php echo nl2br(htmlspecialchars($race['description'] ?: '未登録')); ?></p>
    </div>

    <div class="controls-container" style="justify-content: center; margin-top: 30px;">
        <div class="page-actions">
            <?php if ($GLOBALS['edit_mode_enabled']): ?>
                <a href="edit.php?id=<?php echo $race['id']; ?>" class="action-button button-edit">このレースを編集する</a>
            <?php endif; ?>
            <a href="index.php" class="back-link">&laquo; レース一覧に戻る</a>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
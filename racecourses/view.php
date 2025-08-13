<?php
$page_title = '競馬場詳細: ' ;
$current_page = 'racecourses';
$base_path = '../';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$racecourse_id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$stmt = $conn->prepare("SELECT * FROM racecourses WHERE id = ?");
$stmt->bind_param("i", $racecourse_id);
$stmt->execute();
$racecourse = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$racecourse) { die("指定された競馬場が見つかりません。"); }

$page_title = '競馬場詳細';
$current_page = 'racecourses';
$base_path = '../';
?>

<?php include '../templates/header.php'; ?>

<div class="container">
    <h1><?php echo htmlspecialchars($racecourse['name']); ?></h1>
    
    <div class="racecourse-details-container">
        <div class="details-image">
            <?php if (!empty($racecourse['image_url']) && file_exists($racecourse['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($racecourse['image_url']); ?>" alt="<?php echo htmlspecialchars($racecourse['name']); ?>">
            <?php else: ?>
                <div class="no-image" style="height: 300px; width: 100%; max-width: 100%; font-size: 1.5em;">画像なし</div>
            <?php endif; ?>
        </div>

        <div class="details-info-box">
            <h2 class="section-title">基本情報</h2>
            <div class="info-grid">
                <p><strong>所在地</strong></p>
                <p><?php echo htmlspecialchars($racecourse['location'] ?: '未登録'); ?></p>
                <p><strong>回り方向</strong></p>
                <p><?php echo htmlspecialchars($racecourse['turning_direction'] ?: '未登録'); ?></p>
                <p><strong>コース</strong></p>
                <p><?php echo htmlspecialchars($racecourse['course_type'] ?: '未登録'); ?></p>
                <p><strong>馬場</strong></p>
                <p><?php echo htmlspecialchars($racecourse['surface'] ?: '未登録'); ?></p>
            </div>
            
            <h2 class="section-title">特徴・説明</h2>
            <p class="description-text"><?php echo nl2br(htmlspecialchars($racecourse['description'] ?: '未登録')); ?></p>
        </div>
    </div>

    <a href="index.php" class="back-link" style="margin-top: 30px;">&laquo; 一覧に戻る</a>
</div>
<?php include '../templates/footer.php'; ?>
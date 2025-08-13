<?php
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$skill_id = (int)$_GET['id'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$stmt = $conn->prepare("SELECT * FROM skills WHERE id = ?");
$stmt->bind_param("i", $skill_id);
$stmt->execute();
$skill = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$skill) { die("指定されたスキルが見つかりません。"); }

$page_title = 'スキル詳細: ' . htmlspecialchars($skill['skill_name']);
$current_page = 'skills';
$base_path = '../';
?>

<?php include '../templates/header.php'; ?>

<style>
    .skill-details-container {
        background-color: #fff;
        padding: 24px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .skill-details-header {
        border-bottom: 2px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .skill-details-header h1 {
        margin: 0;
        border: none;
        padding: 0;
    }
    .skill-details-header h1::after {
        display: none;
    }
    .skill-details-body p {
        font-size: 1.1em;
        line-height: 1.8;
    }
    .skill-details-body strong {
        display: inline-block;
        min-width: 80px;
        color: #888;
    }
</style>

<div class="container">
    <div class="skill-details-container">
        <div class="skill-details-header">
            <h1><?php echo htmlspecialchars($skill['skill_name']); ?></h1>
            <?php
                $type_class = '';
                if ($skill['skill_type'] == 'レアスキル') { $type_class = ' type-rare'; } 
                elseif ($skill['skill_type'] == '進化スキル') { $type_class = ' type-evolution'; }
                elseif ($skill['skill_type'] == '固有スキル') { $type_class = ' type-unique'; }
            ?>
            <span class="skill-card-type<?php echo $type_class; ?>"><?php echo htmlspecialchars($skill['skill_type']); ?></span>
        </div>
        <div class="skill-details-body">
            <p><strong>距離:</strong> <?php echo htmlspecialchars($skill['distance_type'] ?: '指定なし'); ?></p>
            <p><strong>脚質:</strong> <?php echo htmlspecialchars($skill['strategy_type'] ?: '指定なし'); ?></p>
            <p><strong>馬場:</strong> <?php echo htmlspecialchars($skill['surface_type'] ?: '指定なし'); ?></p>
            <p><strong>説明:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($skill['skill_description'])); ?></p>
        </div>
    </div>

    <div class="controls-container" style="margin-top: 24px; justify-content: center;">
        <div class="page-actions">
            <?php if ($GLOBALS['edit_mode_enabled']): ?>
                <a href="edit.php?id=<?php echo $skill['id']; ?>" class="action-button button-edit">このスキルを編集する</a>
            <?php endif; ?>
            <a href="index.php" class="back-link">&laquo; スキル一覧に戻る</a>
        </div>
    </div>

    <div class="container" style="margin-top: 30px;">
        <h2 class="section-title">このスキルを持つウマ娘</h2>
        <p>（今後、ここにウマ娘の一覧が表示される予定です）</p>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
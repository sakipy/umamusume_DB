<?php
$page_title = 'スキル追加';
$current_page = 'skills';
$base_path = '../';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$message = ''; $error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 送信されたデータをカンマ区切りの文字列に変換
    $distance_type = isset($_POST['distance_type']) ? implode(',', $_POST['distance_type']) : '';
    $strategy_type = isset($_POST['strategy_type']) ? implode(',', $_POST['strategy_type']) : '';
    $surface_type = isset($_POST['surface_type']) ? implode(',', $_POST['surface_type']) : ''; // ★ この行を追加
    
    $skill_name = $_POST['skill_name'] ?? '';
    $skill_description = $_POST['skill_description'] ?? '';
    $skill_type = $_POST['skill_type'] ?? '';

    if (empty($skill_name)) {
        $error_message = "スキル名は必須です。";
    } else {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
        $conn->set_charset("utf8mb4");

        $stmt = $conn->prepare("INSERT INTO skills (skill_name, skill_description, skill_type, distance_type, strategy_type, surface_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type);

        if ($stmt->execute()) {
            $message = "新しいスキル「" . htmlspecialchars($skill_name) . "」を登録しました。";
        } else {
            $error_message = "登録に失敗しました: " . $stmt->error;
        }
        $stmt->close();
        $conn->close();
    }
}

// フォーム表示用の選択肢
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$surface_options = ['芝', 'ダート']; // ★ この行を追加
?>

<?php include '../templates/header.php'; ?>

<style> 
    .checkbox-group { 
        display: flex; 
        gap: 20px; 
        flex-wrap: wrap; 
    } 
    .checkbox-group label { 
        font-weight: normal; 
    } 
</style>

<div class="container">
        <h1>新しいスキルを登録</h1>
        <?php if (!empty($message)): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <form action="add.php" method="POST">
            <div class="form-group">
                <label for="skill_name">スキル名:</label>
                <input type="text" id="skill_name" name="skill_name" required>
            </div>
            <div class="form-group">
                <label for="skill_type">スキルタイプ:</label>
                <select id="skill_type" name="skill_type">
                    <option value="ノーマルスキル">ノーマルスキル</option>
                    <option value="レアスキル">レアスキル</option>
                    <option value="進化スキル">進化スキル</option>
                    <option value="固有スキル">固有スキル</option>
                    <option value="その他">その他</option>
                </select>
            </div>
            <div class="form-group">
                <label>距離:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($distance_options as $index => $option): ?>
                        <input type="checkbox" name="distance_type[]" value="<?php echo $option; ?>" id="dist_<?php echo $index; ?>">
                        <label for="dist_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
             <div class="form-group">
                <label>脚質:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($strategy_options as $index => $option): ?>
                        <input type="checkbox" name="strategy_type[]" value="<?php echo $option; ?>" id="strat_<?php echo $index; ?>">
                        <label for="strat_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>馬場適性:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($surface_options as $index => $option): ?>
                        <input type="checkbox" name="surface_type[]" value="<?php echo $option; ?>" id="surf_<?php echo $index; ?>">
                        <label for="surf_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="skill_description">スキル説明:</label>
            
                <textarea id="skill_description" name="skill_description" rows="4"></textarea>
            </div>
            <button type="submit">登録する</button>
        </form>
        <a href="index.php" class="back-link">&laquo; スキル一覧に戻る</a>
    </div>

<?php include '../templates/footer.php'; ?>
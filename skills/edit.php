<?php
$page_title = 'スキル編集';
$current_page = 'skills';
$base_path = '../';

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$error_message = ''; $skill = null;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $distance_type = isset($_POST['distance_type']) ? implode(',', $_POST['distance_type']) : '';
    $strategy_type = isset($_POST['strategy_type']) ? implode(',', $_POST['strategy_type']) : '';
    $surface_type = isset($_POST['surface_type']) ? implode(',', $_POST['surface_type']) : ''; // ★ この行を追加
    
    $skill_name = $_POST['skill_name'] ?? '';
    $skill_description = $_POST['skill_description'] ?? '';
    $skill_type = $_POST['skill_type'] ?? '';

    if (empty($skill_name)) {
        $error_message = "スキル名は必須です。";
    } else {
        $stmt = $conn->prepare("UPDATE skills SET skill_name = ?, skill_description = ?, skill_type = ?, distance_type = ?, strategy_type = ?, surface_type = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type, $id);
        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        } else {
            $error_message = "更新に失敗しました: " . $stmt->error;
        }
        $stmt->close();
    }
}

$skill_id = (int)($_GET['id'] ?? 0);
if ($skill_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM skills WHERE id = ?");
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $skill = $stmt->get_result()->fetch_assoc();
}
if (!$skill && $_SERVER['REQUEST_METHOD'] !== 'POST') { die("スキルが見つかりません。"); }
$conn->close();

// フォーム表示用の選択肢
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$surface_options = ['芝', 'ダート']; // ★ この行を追加

// DBに保存されているカンマ区切りの文字列を配列に変換
$saved_distances = !empty($skill['distance_type']) ? explode(',', $skill['distance_type']) : [];
$saved_strategies = !empty($skill['strategy_type']) ? explode(',', $skill['strategy_type']) : [];
$saved_surfaces = !empty($skill['surface_type']) ? explode(',', $skill['surface_type']) : []; // ★ この行を追加
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
        <h1>スキル情報を編集</h1>
        <?php if (!empty($error_message)): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>
        <?php if ($skill): ?>
        <form action="edit.php" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($skill['id']); ?>">
            <div class="form-group">
                <label for="skill_name">スキル名:</label>
                <input type="text" id="skill_name" name="skill_name" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="skill_type">スキルタイプ:</label>
                <select id="skill_type" name="skill_type">
                    <?php
                        $types = ['ノーマルスキル', 'レアスキル', '進化スキル', '固有スキル', 'その他'];
                        foreach ($types as $type) {
                            $selected = ($skill['skill_type'] == $type) ? 'selected' : '';
                            echo "<option value='{$type}' {$selected}>{$type}</option>";
                        }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>距離:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($distance_options as $index => $option): ?>
                        <input type="checkbox" name="distance_type[]" value="<?php echo $option; ?>" id="dist_<?php echo $index; ?>" <?php if(in_array($option, $saved_distances)) echo 'checked'; ?>>
                        <label for="dist_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            <div class="form-group">
                <label>脚質:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($strategy_options as $index => $option): ?>
                        <input type="checkbox" name="strategy_type[]" value="<?php echo $option; ?>" id="strat_<?php echo $index; ?>" <?php if(in_array($option, $saved_strategies)) echo 'checked'; ?>>
                        <label for="strat_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>馬場適性:</label>
                <div class="toggle-checkbox-group">
                    <?php foreach($surface_options as $index => $option): ?>
                        <input type="checkbox" name="surface_type[]" value="<?php echo $option; ?>" id="surf_<?php echo $index; ?>" <?php if(in_array($option, $saved_surfaces)) echo 'checked'; ?>>
                        <label for="surf_<?php echo $index; ?>"><?php echo $option; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="skill_description">スキル説明:</label>
                <textarea id="skill_description" name="skill_description" rows="4"><?php echo htmlspecialchars($skill['skill_description']); ?></textarea>
            </div>
            <button type="submit">更新する</button>
        </form>
        <a href="index.php" class="back-link">&laquo; スキル一覧に戻る</a>
        <?php endif; ?>
    </div>

<?php include '../templates/footer.php'; ?>
<?php
// ========== ページ設定 ==========
$page_title = '育成ウマ娘を登録';
$current_page = 'trained_umamusume';
$base_path = '../';
$message = ''; 
$error_message = '';
$base_characters = [];
$all_skills = [];

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- POSTリクエスト（フォーム送信時）の処理 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        // 画像アップロード処理
        $screenshot_url = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
            $upload_dir = '../uploads/trained/';
            if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $file_name = time() . '_' . basename($_FILES['screenshot']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $target_file)) {
                $screenshot_url = 'uploads/trained/' . $file_name;
            } else {
                throw new Exception("スクリーンショットのアップロードに失敗しました。");
            }
        }

        // `trained_umamusume` テーブルへのINSERT
        $stmt = $conn->prepare(
            "INSERT INTO trained_umamusume (
                character_id, nickname, evaluation_rank, evaluation_score, 
                speed, stamina, power, guts, wisdom, 
                screenshot_url, memo, trained_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issiiiiissss",
            $_POST['character_id'], $_POST['nickname'], $_POST['evaluation_rank'], $_POST['evaluation_score'],
            $_POST['speed'], $_POST['stamina'], $_POST['power'], $_POST['guts'], $_POST['wisdom'],
            $screenshot_url, $_POST['memo'], $_POST['trained_date']
        );
        $stmt->execute();
        $new_trained_id = $conn->insert_id;
        if ($new_trained_id == 0) {
            throw new Exception("育成ウマ娘の基本情報の登録に失敗しました。");
        }
        $stmt->close();

        // `trained_umamusume_skills` テーブルへのINSERT
        if (!empty($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
            $stmt_skill = $conn->prepare("INSERT INTO trained_umamusume_skills (trained_umamusume_id, skill_id) VALUES (?, ?)");
            foreach ($_POST['skill_ids'] as $skill_id) {
                $stmt_skill->bind_param("ii", $new_trained_id, $skill_id);
                $stmt_skill->execute();
            }
            $stmt_skill->close();
        }
        $conn->commit();
        $message = "新しく育成したウマ娘を登録しました。";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "エラー: " . $e->getMessage();
    }
}

// --- 育成対象ウマ娘のリストを取得 ---
$result_chars = $conn->query("SELECT id, character_name FROM characters ORDER BY character_name ASC");
while ($row = $result_chars->fetch_assoc()) {
    $base_characters[] = $row;
}

// --- 全スキルリストの取得 ---
$result_skills = $conn->query("SELECT id, skill_name, skill_type, distance_type, strategy_type FROM skills ORDER BY skill_name ASC");
while ($row = $result_skills->fetch_assoc()) {
    $all_skills[] = $row;
}
$conn->close();
?>
<?php include '../templates/header.php'; ?>
<div class="container full-width">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <form action="add.php" method="POST" enctype="multipart/form-data">
        <div class="form-grid-2col">
            <div>
                <h2 class="section-title-bar">基本情報</h2>
                <div class="form-group">
                    <label for="character_id">育成ウマ娘:</label>
                    <select id="character_id" name="character_id" required>
                        <option value="">選択してください</option>
                        <?php foreach ($base_characters as $char): ?>
                            <option value="<?php echo $char['id']; ?>"><?php echo htmlspecialchars($char['character_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nickname">ニックネーム (任意):</label>
                    <input type="text" id="nickname" name="nickname" placeholder="例: 初UG達成！">
                </div>
                 <div class="form-group">
                    <label for="trained_date">育成完了日:</label>
                    <input type="date" id="trained_date" name="trained_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="evaluation_score">評価点:</label>
                        <input type="number" id="evaluation_score" name="evaluation_score">
                    </div>
                    <div class="form-group">
                        <label for="evaluation_rank">評価ランク:</label>
                        <input type="text" id="evaluation_rank" name="evaluation_rank" readonly style="cursor: default;">
                    </div>
                </div>

                <h2 class="section-title-bar">ステータス</h2>
                <div class="status-growth-grid">
                    <div></div>
                    <div class="grid-header">スピ</div><div class="grid-header">スタ</div><div class="grid-header">パワ</div><div class="grid-header">根性</div><div class="grid-header">賢さ</div>
                    <div class="grid-label">育成後</div>
                    <div><input type="number" name="speed" value="1200"></div>
                    <div><input type="number" name="stamina" value="1000"></div>
                    <div><input type="number" name="power" value="1200"></div>
                    <div><input type="number" name="guts" value="600"></div>
                    <div><input type="number" name="wisdom" value="600"></div>
                </div>

                 <h2 class="section-title-bar">育成メモ</h2>
                <div class="form-group">
                    <textarea name="memo" rows="4" placeholder="使用したサポートカードや育成方針など"></textarea>
                </div>
            </div>
            <div>
                <h2 class="section-title-bar">スクリーンショット</h2>
                <div class="form-group">
                     <div class="image-preview-wrapper" style="width: 100%; aspect-ratio: 9/16;">
                        <img id="image_preview" style="display:none;"><span>プレビュー</span>
                    </div>
                    <label for="screenshot" class="file-upload-label" style="margin-top: 10px;">ファイルを選択...</label>
                    <input type="file" id="screenshot" name="screenshot" class="file-upload-input" accept="image/*">
                </div>
            </div>
        </div>
        
        <h2 class="section-title-bar">取得スキル</h2>
        <div class="skill-selection-area">
             <div class="skill-list-wrapper" style="max-height: 400px;">
                <ul class="skill-list grid-layout" id="skill-list-container">
                    <?php foreach ($all_skills as $skill): ?>
                        <li data-type="<?php echo htmlspecialchars($skill['skill_type']); ?>" data-distance="<?php echo htmlspecialchars($skill['distance_type']); ?>" data-strategy="<?php echo htmlspecialchars($skill['strategy_type']); ?>">
                            <label>
                                <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>">
                                <?php
                                    $text_class = '';
                                    if ($skill['skill_type'] == 'レアスキル') { $text_class = 'text-rare'; } 
                                    elseif ($skill['skill_type'] == '進化スキル') { $text_class = 'text-evolution'; }
                                    elseif ($skill['skill_type'] == '固有スキル') { $text_class = 'text-rainbow'; }
                                ?>
                                <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <button type="submit" style="margin-top: 24px;">この内容で登録する</button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('screenshot').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const preview = document.getElementById('image_preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
            const placeholder = preview.nextElementSibling;
            if(placeholder) placeholder.style.display = 'none';
        }
    });

    document.getElementById('skill-list-container').addEventListener('change', e => {
        if (e.target.type === 'checkbox') e.target.closest('li').classList.toggle('selected', e.target.checked);
    });

    const scoreInput = document.getElementById('evaluation_score');
    const rankInput = document.getElementById('evaluation_rank');

    const rankThresholds = [
        { score: 64200, rank: 'US1' }, { score: 63400, rank: 'US' },
        { score: 62500, rank: 'UA9' }, { score: 61700, rank: 'UA8' }, { score: 60800, rank: 'UA7' }, 
        { score: 60000, rank: 'UA6' }, { score: 59200, rank: 'UA5' }, { score: 58400, rank: 'UA4' }, 
        { score: 57500, rank: 'UA3' }, { score: 56700, rank: 'UA2' }, { score: 55900, rank: 'UA1' }, { score: 55200, rank: 'UA' },
        { score: 54400, rank: 'UB9' }, { score: 53600, rank: 'UB8' }, { score: 52800, rank: 'UB7' }, 
        { score: 52000, rank: 'UB6' }, { score: 51300, rank: 'UB5' }, { score: 50500, rank: 'UB4' }, 
        { score: 49800, rank: 'UB3' }, { score: 49000, rank: 'UB2' }, { score: 48300, rank: 'UB1' }, { score: 47600, rank: 'UB' },
        { score: 46900, rank: 'UC9' }, { score: 46200, rank: 'UC8' }, { score: 45400, rank: 'UC7' }, 
        { score: 44700, rank: 'UC6' }, { score: 44000, rank: 'UC5' }, { score: 43400, rank: 'UC4' }, 
        { score: 42700, rank: 'UC3' }, { score: 42000, rank: 'UC2' }, { score: 41300, rank: 'UC1' }, { score: 40700, rank: 'UC' },
        { score: 40000, rank: 'UD9' }, { score: 39400, rank: 'UD8' }, { score: 38700, rank: 'UD7' }, 
        { score: 38100, rank: 'UD6' }, { score: 37500, rank: 'UD5' }, { score: 36800, rank: 'UD4' }, 
        { score: 36200, rank: 'UD3' }, { score: 35600, rank: 'UD2' }, { score: 35000, rank: 'UD1' }, { score: 34400, rank: 'UD' },
        { score: 33800, rank: 'UE9' }, { score: 33200, rank: 'UE8' }, { score: 32700, rank: 'UE7' }, 
        { score: 32100, rank: 'UE6' }, { score: 31500, rank: 'UE5' }, { score: 31000, rank: 'UE4' }, 
        { score: 30400, rank: 'UE3' }, { score: 29900, rank: 'UE2' }, { score: 29400, rank: 'UE1' }, { score: 28800, rank: 'UE' },
        { score: 28300, rank: 'UF9' }, { score: 27800, rank: 'UF8' }, { score: 27300, rank: 'UF7' }, 
        { score: 26800, rank: 'UF6' }, { score: 26300, rank: 'UF5' }, { score: 25800, rank: 'UF4' }, 
        { score: 25300, rank: 'UF3' }, { score: 24800, rank: 'UF2' }, { score: 24300, rank: 'UF1' }, { score: 23900, rank: 'UF' },
        { score: 23400, rank: 'UG9' }, { score: 23000, rank: 'UG8' }, { score: 22500, rank: 'UG7' }, 
        { score: 22100, rank: 'UG6' }, { score: 21600, rank: 'UG5' }, { score: 21200, rank: 'UG4' }, 
        { score: 20800, rank: 'UG3' }, { score: 20400, rank: 'UG2' }, { score: 20000, rank: 'UG1' }, { score: 19600, rank: 'UG' },
        { score: 19200, rank: 'SS+' }, { score: 17500, rank: 'SS' },  { score: 15900, rank: 'S+' }, 
        { score: 14500, rank: 'S' },   { score: 12100, rank: 'A+' },  { score: 10000, rank: 'A' },
        { score: 8200, rank: 'B+' },   { score: 7000, rank: 'B' },    { score: 5800, rank: 'C+' },
        { score: 3800, rank: 'C' },    { score: 2300, rank: 'D+' },   { score: 1300, rank: 'D' },
        { score: 600, rank: 'E+' },    { score: 300, rank: 'E' },     { score: 150, rank: 'F+' },
        { score: 50, rank: 'F' },      { score: 10, rank: 'G+' },     { score: 0, rank: 'G' }
    ];

    function getRankClass(rank) {
        const baseRank = rank.replace(/[0-9+]/g, '');
        return 'rank-' + baseRank.toLowerCase();
    }

    function updateRank() {
        const score = parseInt(scoreInput.value, 10);
        let selectedRank = 'G';
        
        if (!isNaN(score)) {
            for (const item of rankThresholds) {
                if (score >= item.score) {
                    selectedRank = item.rank;
                    break;
                }
            }
        }
        rankInput.value = selectedRank;
        rankInput.className = ''; // Reset classes first
        rankInput.classList.add(getRankClass(selectedRank));
    }
    
    scoreInput.addEventListener('input', updateRank);
    updateRank();
});
</script>
<?php include '../templates/footer.php'; ?>
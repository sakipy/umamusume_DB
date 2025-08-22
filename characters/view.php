<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘 詳細';
$current_page = 'characters';
$base_path = '../';

// ========== DB接続とデータ取得 ==========
if (empty($_GET['id'])) { die("IDが指定されていません。"); }
$id = (int)$_GET['id'];

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// === 1. メインとなるキャラクターの基本情報を取得 ===
$stmt_char = $conn->prepare("
    SELECT c.*, p.face_image_url 
    FROM characters c
    LEFT JOIN pokedex p ON c.pokedex_id = p.id
    WHERE c.id = ?
");
$stmt_char->bind_param("i", $id);
$stmt_char->execute();
$character = $stmt_char->get_result()->fetch_assoc();
$stmt_char->close();

if (!$character) { 
    $conn->close();
    die("指定されたキャラクターが見つかりません。"); 
}
$page_title = '詳細: ' . htmlspecialchars($character['character_name']);

// === 2. 所持スキルを取得（skillsテーブルから直接取得に変更） ===
$owned_skills = [];
$sql_skills = "
    SELECT 
        s.id,
        s.skill_name,
        s.skill_description,
        s.skill_type,
        s.required_skill_points,
        CASE 
            WHEN s.base_skill_id IS NOT NULL THEN CONCAT('「', base_s.skill_name, '」から進化')
            WHEN s.required_skill_points IS NOT NULL THEN CONCAT(s.required_skill_points, 'pt')
            ELSE '初期'
        END as unlock_condition,
        base_s.skill_name as base_skill_name,
        base_s.id as base_skill_id
    FROM skills s
    JOIN character_skills cs ON s.id = cs.skill_id
    LEFT JOIN skills base_s ON s.base_skill_id = base_s.id
    WHERE cs.character_id = ?
    ORDER BY FIELD(s.skill_type, '固有スキル'), s.id
";

$stmt_skills = $conn->prepare($sql_skills);
$stmt_skills->bind_param("i", $id);
$stmt_skills->execute();
$result_skills = $stmt_skills->get_result();
while($row = $result_skills->fetch_assoc()){
    $owned_skills[] = $row;
}
$stmt_skills->close();
$conn->close();

// 編集モード設定のグローバル変数を読み込む
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../templates/header.php';
?>

<style>
    /* ステータス＆成長率グリッドのレイアウト */
    .status-growth-grid.view-mode {
        display: grid !important;
        grid-template-columns: 60px repeat(5, 1fr);
        gap: 8px 5px;
        align-items: center;
        text-align: center;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        background-color: #fdfdfd;
    }
    /* 進化元スキル表示用のスタイル */
    .evolution-source {
        font-size: 0.85em;
        color: #666;
        background-color: #f0f8ff;
        padding: 5px 10px;
        margin-top: 8px;
        border-radius: 4px;
        border-left: 3px solid #87ceeb;
    }
    .evolution-source a {
        color: #1a0dab;
        text-decoration: none;
    }
    .evolution-source a:hover {
        text-decoration: underline;
    }
</style>

<div class="container">
    <div class="character-view-header">
        <div class="character-view-rarity"><?php echo str_repeat('★', $character['rarity']); ?></div>
        <h1><?php echo htmlspecialchars($character['character_name']); ?></h1>
    </div>

    <div class="character-view-grid">
        <div class="character-view-image-container">
            <div class="image-wrapper">
                <?php if (!empty($character['image_url_suit']) && file_exists('../' . $character['image_url_suit'])): ?>
                    <img id="character-image-suit" src="../<?php echo htmlspecialchars($character['image_url_suit']); ?>" alt="<?php echo htmlspecialchars($character['character_name']); ?> (勝負服)" class="character-view-image active rarity-<?php echo $character['rarity']; ?>">
                <?php else: ?>
                    <div id="character-image-suit" class="no-image rarity-<?php echo $character['rarity']; ?>" style="height: 350px;">勝負服画像なし</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="character-view-details">
            <h2 class="section-title-bar">初期ステータスと成長率</h2>
            <div class="status-growth-grid view-mode">
                <div></div>
                <div class="grid-header">スピ</div><div class="grid-header">スタ</div><div class="grid-header">パワ</div><div class="grid-header">根性</div><div class="grid-header">賢さ</div>
                <div class="grid-label">初期値</div>
                <div class="grid-data"><?php echo $character['initial_speed']; ?></div>
                <div class="grid-data"><?php echo $character['initial_stamina']; ?></div>
                <div class="grid-data"><?php echo $character['initial_power']; ?></div>
                <div class="grid-data"><?php echo $character['initial_guts']; ?></div>
                <div class="grid-data"><?php echo $character['initial_wisdom']; ?></div>
                <div class="grid-label">成長率</div>
                <div class="grid-data growth-rate <?php if($character['growth_rate_speed'] > 0) echo 'has-growth'; ?>"><?php echo $character['growth_rate_speed']; ?>%</div>
                <div class="grid-data growth-rate <?php if($character['growth_rate_stamina'] > 0) echo 'has-growth'; ?>"><?php echo $character['growth_rate_stamina']; ?>%</div>
                <div class="grid-data growth-rate <?php if($character['growth_rate_power'] > 0) echo 'has-growth'; ?>"><?php echo $character['growth_rate_power']; ?>%</div>
                <div class="grid-data growth-rate <?php if($character['growth_rate_guts'] > 0) echo 'has-growth'; ?>"><?php echo $character['growth_rate_guts']; ?>%</div>
                <div class="grid-data growth-rate <?php if($character['growth_rate_wisdom'] > 0) echo 'has-growth'; ?>"><?php echo $character['growth_rate_wisdom']; ?>%</div>
            </div>

            <h2 class="section-title-bar">適性</h2>
            <div class="aptitude-view-grid">
                <div class="aptitude-category">
                    <div class="aptitude-label">バ場</div>
                    <div class="aptitude-item"><span class="aptitude-name">芝</span><span class="aptitude-rank rank-<?php echo $character['surface_aptitude_turf']; ?>"><?php echo $character['surface_aptitude_turf']; ?></span></div>
                    <div class="aptitude-item"><span class="aptitude-name">ダート</span><span class="aptitude-rank rank-<?php echo $character['surface_aptitude_dirt']; ?>"><?php echo $character['surface_aptitude_dirt']; ?></span></div>
                </div>
                 <div class="aptitude-category">
                    <div class="aptitude-label">距離</div>
                    <div class="aptitude-item"><span class="aptitude-name">短距離</span><span class="aptitude-rank rank-<?php echo $character['distance_aptitude_short']; ?>"><?php echo $character['distance_aptitude_short']; ?></span></div>
                    <div class="aptitude-item"><span class="aptitude-name">マイル</span><span class="aptitude-rank rank-<?php echo $character['distance_aptitude_mile']; ?>"><?php echo $character['distance_aptitude_mile']; ?></span></div>
                    <div class="aptitude-item"><span class="aptitude-name">中距離</span><span class="aptitude-rank rank-<?php echo $character['distance_aptitude_medium']; ?>"><?php echo $character['distance_aptitude_medium']; ?></span></div>
                    <div class="aptitude-item"><span class="aptitude-name">長距離</span><span class="aptitude-rank rank-<?php echo $character['distance_aptitude_long']; ?>"><?php echo $character['distance_aptitude_long']; ?></span></div>
                </div>
                 <div class="aptitude-category">
                    <div class="aptitude-label">脚質</div>
                     <div class="aptitude-item"><span class="aptitude-name">逃げ</span><span class="aptitude-rank rank-<?php echo $character['strategy_aptitude_runner']; ?>"><?php echo $character['strategy_aptitude_runner']; ?></span></div>
                     <div class="aptitude-item"><span class="aptitude-name">先行</span><span class="aptitude-rank rank-<?php echo $character['strategy_aptitude_leader']; ?>"><?php echo $character['strategy_aptitude_leader']; ?></span></div>
                     <div class="aptitude-item"><span class="aptitude-name">差し</span><span class="aptitude-rank rank-<?php echo $character['strategy_aptitude_chaser']; ?>"><?php echo $character['strategy_aptitude_chaser']; ?></span></div>
                     <div class="aptitude-item"><span class="aptitude-name">追込</span><span class="aptitude-rank rank-<?php echo $character['strategy_aptitude_trailer']; ?>"><?php echo $character['strategy_aptitude_trailer']; ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <h2 class="section-title-bar">所持スキル</h2>
    <div class="skill-section">
        <?php if (!empty($owned_skills)): ?>
            <table class="skill-table">
                <thead>
                    <tr>
                        <th>スキル情報</th>
                        <th>タイプ</th>
                        <th>解放条件 / Pt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($owned_skills as $skill): ?>
                    <?php
                        $row_class = ''; $name_class = '';
                        if ($skill['skill_type'] == 'レアスキル') { $row_class = 'type-rare'; $name_class = 'text-rare'; } 
                        elseif ($skill['skill_type'] == '進化スキル') { $row_class = 'type-evolution'; $name_class = 'text-evolution'; }
                        elseif ($skill['skill_type'] == '固有スキル') { $row_class = 'type-unique'; $name_class = 'text-rainbow'; }
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <a href="../skills/view.php?id=<?php echo $skill['id']; ?>" class="<?php echo $name_class; ?>" style="font-weight: bold; text-decoration: none;"><?php echo htmlspecialchars($skill['skill_name']); ?></a>
                            <div class="skill-description"><?php echo nl2br(htmlspecialchars($skill['skill_description'])); ?></div>
                            <?php if (!empty($skill['base_skill_name'])): ?>
                                <div class="evolution-source">
                                    進化元: <a href="../skills/view.php?id=<?php echo $skill['base_skill_id']; ?>"><?php echo htmlspecialchars($skill['base_skill_name']); ?></a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($skill['skill_type']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($skill['unlock_condition']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>このウマ娘に登録されているスキルはありません。</p>
        <?php endif; ?>
    </div>
    
    <div class="controls-container" style="display: flex; justify-content: center; margin-top: 30px;">
        <div class="page-actions">
            <?php if (!empty($GLOBALS['edit_mode_enabled']) && $GLOBALS['edit_mode_enabled']): ?>
                <a href="edit.php?id=<?php echo $character['id']; ?>" class="action-button button-edit">このウマ娘を編集する</a>
            <?php endif; ?>
            <a href="index.php" class="back-link">&laquo; 一覧に戻る</a>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
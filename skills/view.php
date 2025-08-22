<?php
// ========== データベース接続設定 ==========
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'umamusume_db';

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die('接続に失敗: ' . $conn->connect_error);
}

// 文字セットを明示的に設定
$conn->set_charset("utf8mb4");

// スキルIDの取得
$skill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$skill_id) {
    die('スキルIDが指定されていません。');
}

// スキル詳細情報を取得
$stmt = $conn->prepare("SELECT * FROM skills WHERE id = ?");
$stmt->bind_param("i", $skill_id);
$stmt->execute();
$skill = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$skill) { 
    die("指定されたスキルが見つかりません。"); 
}

$default_icon_position = -8;
$default_icon_size = 200;

// このスキルを持つウマ娘を取得
$char_stmt = $conn->prepare(
    "SELECT c.id, c.character_name, c.image_url, c.image_url_suit
     FROM characters c
     JOIN character_skills cs ON c.id = cs.character_id
     WHERE cs.skill_id = ?"
);
$char_stmt->bind_param("i", $skill_id);
$char_stmt->execute();
$characters_result = $char_stmt->get_result();
$characters = [];
while ($row = $characters_result->fetch_assoc()) {
    $characters[] = $row;
}
$char_stmt->close();

// 進化関係を取得（このスキルの進化元）
if ($skill['base_skill_id']) {
    $evolution_base_stmt = $conn->prepare(
        "SELECT s.id, s.skill_name, s.skill_description, s.skill_type
         FROM skills s
         WHERE s.id = ?"
    );
    $evolution_base_stmt->bind_param("i", $skill['base_skill_id']);
    $evolution_base_stmt->execute();
    $evolution_base_result = $evolution_base_stmt->get_result();
    $evolution_bases = [];
    while ($row = $evolution_base_result->fetch_assoc()) {
        $evolution_bases[] = $row;
    }
    $evolution_base_stmt->close();
} else {
    $evolution_bases = [];
}

// 進化関係を取得（このスキルから進化するもの）
$evolution_evolved_stmt = $conn->prepare(
    "SELECT s.id, s.skill_name, s.skill_description, s.skill_type
     FROM skills s
     WHERE s.base_skill_id = ?"
);
$evolution_evolved_stmt->bind_param("i", $skill_id);
$evolution_evolved_stmt->execute();
$evolution_evolved_result = $evolution_evolved_stmt->get_result();
$evolution_evolved = [];
while ($row = $evolution_evolved_result->fetch_assoc()) {
    $evolution_evolved[] = $row;
}
$evolution_evolved_stmt->close();

$conn->close();

/**
 * 画像から顔位置を検出し、適切な表示位置を計算する
 */
function calculateOptimalImagePosition($imagePath, $characterName) {
    // 1. キャッシュから既存の設定をチェック
    static $position_cache = null;
    if ($position_cache === null) {
        $cache_file = '../cache/face_positions.json';
        if (file_exists($cache_file)) {
            $position_cache = json_decode(file_get_contents($cache_file), true) ?: [];
        } else {
            $position_cache = [];
        }
    }
    
    // キャッシュに存在する場合は使用
    if (isset($position_cache[$characterName])) {
        return $position_cache[$characterName];
    }
    
    // 2. 顔検出を試行
    $face_position = detectFaceWithMultipleMethods($imagePath);
    
    if ($face_position) {
        // 顔が検出された場合、円形表示に最適な位置を計算
        $optimal_position = calculateCircularDisplayPosition($face_position);
    } else {
        // 検出できない場合はデフォルト値
        $optimal_position = [
            'background_position_y' => -8,
            'background_size' => 200,
            'confidence' => 0.1
        ];
    }
    
    // 3. キャッシュに保存
    $position_cache[$characterName] = $optimal_position;
    if (!is_dir('../cache')) {
        mkdir('../cache', 0755, true);
    }
    file_put_contents('../cache/face_positions.json', json_encode($position_cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return $optimal_position;
}

/**
 * 複数の方法で顔検出を試行
 */
function detectFaceWithMultipleMethods($imagePath) {
    // 画像ファイルが存在しない場合は処理を停止
    if (!file_exists($imagePath) || strpos($imagePath, 'http') === 0) {
        return null;
    }
    
    // 方法1: GDライブラリベースの簡易顔検出
    $gd_result = detectFaceWithGD($imagePath);
    if ($gd_result && $gd_result['confidence'] > 0.3) {
        return $gd_result;
    }
    
    // 方法2: 色彩解析による顔領域推定
    $color_result = detectFaceByColorAnalysis($imagePath);
    if ($color_result && $color_result['confidence'] > 0.2) {
        return $color_result;
    }
    
    return null;
}

/**
 * GDライブラリを使用した簡易顔検出
 */
function detectFaceWithGD($imagePath) {
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    
    try {
        $imageData = @file_get_contents($imagePath);
        if ($imageData === false) {
            return null;
        }
        
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);
            return null;
        }
        
        // 肌色の検出（簡易的な顔検出）
        $skin_pixels = [];
        $step = max(1, min($width, $height) / 50); // サンプリング間隔を調整
        
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // 肌色判定（簡易的）
                if (isSkinColor($r, $g, $b)) {
                    $skin_pixels[] = ['x' => $x, 'y' => $y];
                }
            }
        }
        
        imagedestroy($image);
        
        if (count($skin_pixels) > 5) {
            // 肌色ピクセルの重心を計算
            $center_x = array_sum(array_column($skin_pixels, 'x')) / count($skin_pixels);
            $center_y = array_sum(array_column($skin_pixels, 'y')) / count($skin_pixels);
            
            return [
                'x_percent' => ($center_x / $width) * 100,
                'y_percent' => ($center_y / $height) * 100,
                'confidence' => min(count($skin_pixels) / 50, 1.0)
            ];
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 色彩解析による顔検出
 */
function detectFaceByColorAnalysis($imagePath) {
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    
    try {
        $imageData = @file_get_contents($imagePath);
        if ($imageData === false) {
            return null;
        }
        
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // 上部1/3の領域で最も明るい部分を検出（顔は通常上部にある）
        $brightest_x = $width / 2;
        $brightest_y = $height / 3;
        $max_brightness = 0;
        
        $step = max(1, min($width, $height) / 30);
        
        for ($y = 0; $y < $height / 2; $y += $step) {
            for ($x = $width * 0.2; $x < $width * 0.8; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // 明度計算
                $brightness = ($r * 0.299 + $g * 0.587 + $b * 0.114);
                
                // 肌色っぽい明るい部分を重視
                if (isSkinColor($r, $g, $b) && $brightness > $max_brightness) {
                    $max_brightness = $brightness;
                    $brightest_x = $x;
                    $brightest_y = $y;
                }
            }
        }
        
        imagedestroy($image);
        
        if ($max_brightness > 100) {
            return [
                'x_percent' => ($brightest_x / $width) * 100,
                'y_percent' => ($brightest_y / $height) * 100,
                'confidence' => min($max_brightness / 200, 0.8)
            ];
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 肌色判定関数
 */
function isSkinColor($r, $g, $b) {
    // 基本的な肌色判定
    return ($r > 95 && $g > 40 && $b > 20 && 
            $r > $g && $r > $b && 
            abs($r - $g) > 15 &&
            ($r + $g + $b) > 220 &&
            ($r + $g + $b) < 600);
}

/**
 * 顔位置から円形表示に最適な位置を計算
 */
function calculateCircularDisplayPosition($face_position) {
    if (!$face_position) {
        return ['background_position_y' => -8, 'background_size' => 200, 'confidence' => 0.1];
    }
    
    $face_y_percent = $face_position['y_percent'];
    
    // 顔が上部にある場合は下に移動、下部にある場合は上に移動
    if ($face_y_percent < 25) {
        // 顔が上の方 → 背景を下にずらす
        $background_y = -($face_y_percent * 0.4) + 5;
    } elseif ($face_y_percent > 60) {
        // 顔が下の方 → 背景を上にずらす
        $background_y = -($face_y_percent * 0.8) + 20;
    } else {
        // 顔が中央付近 → 軽微な調整
        $background_y = -($face_y_percent * 0.2) - 3;
    }
    
    // 背景サイズの調整
    $background_size = ($face_position['confidence'] > 0.6) ? 220 : 200;
    
    return [
        'background_position_y' => round($background_y),
        'background_size' => $background_size,
        'confidence' => $face_position['confidence']
    ];
}

/**
 * キャラクター名を接頭語と本体に分割する関数
 * 接尾語は接頭語の前に移動させる
 */
function splitCharacterName($fullName) {
    $prefixes = [];
    $main = $fullName;

    // 1. 接尾語(例: (水着)) を抽出し、接頭語リストの最初に移動
    if (preg_match('/(.*)(【(.+?)】|\((.+?)\))$/u', $main, $matches)) {
        $main = trim($matches[1]);
        // 括弧の中身だけを抽出 (複数がマッチする場合)
        $suffixContent = !empty($matches[3]) ? $matches[3] : $matches[4];
        array_unshift($prefixes, $suffixContent);
    }

    // 2. 接頭語(例: [新衣装]) を抽出し、接頭語リストに追加
    if (preg_match('/^([\[【](.+?)[\]】])(.*)/u', $main, $matches)) {
        $main = trim($matches[3]);
        $prefixes[] = $matches[2]; // 括弧の中身だけ
    }
    
    // 3. 特定の単語の接頭語(例: 水着ヴィブロス) を抽出し、接頭語リストに追加
    $prefix_words = ['水着'];
    foreach ($prefix_words as $p) {
        if (strpos($main, $p) === 0) {
            // 他の接頭語と重複していないかチェック
            if (!in_array($p, $prefixes)) {
                 $prefixes[] = $p;
            }
            $main = trim(substr($main, strlen($p)));
            break;
        }
    }

    return [
        'prefix' => implode(' ', $prefixes), // 配列をスペースで連結
        'main'   => trim($main)
    ];
}

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
    .skill-details-header h1 { margin: 0; border: none; padding: 0; }
    .skill-details-header h1::after { display: none; }
    .skill-details-body p { font-size: 1.1em; line-height: 1.8; }
    .skill-details-body strong { display: inline-block; min-width: 80px; color: #888; }
    .character-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    .character-item {
        text-align: center;
    }
    .character-item a {
        text-decoration: none;
        color: #333;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .character-icon-wrapper {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-size: 200%;
        background-repeat: no-repeat;
        background-position: center -8%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
        overflow: hidden;
        background-color: #ffffff !important; /* ←!importantで確実に白を適用 */
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #6c757d;
        position: relative;
    }
    
    .character-icon-wrapper.auto-detected {
        border: 2px solid #28a745;
    }
    
    .character-icon-wrapper.low-confidence {
        border: 2px dashed #ffc107;
    }
    
    .character-item a:hover .character-icon-wrapper {
    transform: translateY(-5px);
    background-color: #ffffff !important;
    }

    .character-item span { font-weight: bold; display: block; width: 100%; }

    .char-name-prefix, .char-name-suffix {
        font-size: 0.8em;
        color: #888;
        font-weight: normal;
    }
    .char-name-main {
        display: block; /* 本体名は改行して表示 */
    }
    
    .confidence-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        background: #28a745;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .confidence-indicator.medium { background: #ffc107; color: #000; }
    .confidence-indicator.low { background: #dc3545; }
</style>

<div class="page-wrapper-with-sidebar">
    <div class="main-content-area">
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

            <div class="controls-container" style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; justify-content: center;">
                <div class="page-actions">
                    <a href="edit.php?id=<?php echo $skill['id']; ?>" class="action-button button-edit">このスキルを編集する</a>
                    <a href="index.php" class="back-link">&laquo; スキル一覧に戻る</a>
                </div>
            </div>

            <?php if (!empty($evolution_bases) || !empty($evolution_evolved)): ?>
            <div class="evolution-container" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <h2 class="section-title">スキル進化関係</h2>
                
                <?php if (!empty($evolution_bases)): ?>
                <div class="evolution-section">
                    <h3>進化元スキル</h3>
                    <div class="evolution-skills">
                        <?php foreach ($evolution_bases as $base_skill): ?>
                        <div class="evolution-skill-item">
                            <a href="view.php?id=<?php echo $base_skill['id']; ?>" class="evolution-skill-link">
                                <?php
                                    $type_class = '';
                                    if ($base_skill['skill_type'] == 'レアスキル') { $type_class = ' type-rare'; } 
                                    elseif ($base_skill['skill_type'] == '進化スキル') { $type_class = ' type-evolution'; }
                                    elseif ($base_skill['skill_type'] == '固有スキル') { $type_class = ' type-unique'; }
                                ?>
                                <span class="skill-card-type<?php echo $type_class; ?>"><?php echo htmlspecialchars($base_skill['skill_type']); ?></span>
                                <h4><?php echo htmlspecialchars($base_skill['skill_name']); ?></h4>
                                <div class="skill-description"><?php echo nl2br(htmlspecialchars($base_skill['skill_description'])); ?></div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($evolution_evolved)): ?>
                <div class="evolution-arrow">↓</div>
                <div class="evolution-section">
                    <h3>進化先スキル</h3>
                    <div class="evolution-skills two-column">
                        <?php foreach ($evolution_evolved as $evolved_skill): ?>
                        <div class="evolution-skill-item">
                            <a href="view.php?id=<?php echo $evolved_skill['id']; ?>" class="evolution-skill-link">
                                <?php
                                    $type_class = '';
                                    if ($evolved_skill['skill_type'] == 'レアスキル') { $type_class = ' type-rare'; } 
                                    elseif ($evolved_skill['skill_type'] == '進化スキル') { $type_class = ' type-evolution'; }
                                    elseif ($evolved_skill['skill_type'] == '固有スキル') { $type_class = ' type-unique'; }
                                ?>
                                <span class="skill-card-type<?php echo $type_class; ?>"><?php echo htmlspecialchars($evolved_skill['skill_type']); ?></span>
                                <h4><?php echo htmlspecialchars($evolved_skill['skill_name']); ?></h4>
                                <div class="skill-description"><?php echo nl2br(htmlspecialchars($evolved_skill['skill_description'])); ?></div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="related-info-sidebar">
        <div class="related-info-container">
            <h2 class="section-title">このスキルを持つウマ娘</h2>
            <?php if (!empty($characters)): ?>
                <div class="character-list">
                    <?php foreach ($characters as $character): ?>
                        <div class="character-item">
                            <a href="../characters/view.php?id=<?php echo $character['id']; ?>">
                                <?php
                                    $image_path = '';
                                    if (!empty($character['image_url_suit'])) {
                                        $image_path = (strpos($character['image_url_suit'], 'http') === 0) ? $character['image_url_suit'] : '../' . $character['image_url_suit'];
                                    } elseif (!empty($character['image_url'])) {
                                        $image_path = (strpos($character['image_url'], 'http') === 0) ? $character['image_url'] : '../' . $character['image_url'];
                                    } else {
                                        $image_path = '../images/default_face.png';
                                    }
                                    
                                    $char_name_full = $character['character_name'];
                                    $optimal_position = calculateOptimalImagePosition($image_path, $char_name_full);
                                    
                                    $style  = "background-image: url('" . htmlspecialchars($image_path) . "'); ";
                                    $style .= "background-position: center " . $optimal_position['background_position_y'] . "%; ";
                                    $style .= "background-size: " . $optimal_position['background_size'] . "%;";

                                    $css_class = 'character-icon-wrapper';
                                    if ($optimal_position['confidence'] > 0.2) { $css_class .= ' auto-detected'; } else { $css_class .= ' low-confidence'; }

                                    $name_parts = splitCharacterName($char_name_full);
                                ?>
                                <div class="<?php echo $css_class; ?>" style="<?php echo $style; ?>" title="<?php echo $char_name_full; ?> - 検出信頼度: <?php echo round($optimal_position['confidence'] * 100); ?>%">
                                    <?php if ($optimal_position['confidence'] > 0.2): ?>
                                        <div class="confidence-indicator <?php echo $optimal_position['confidence'] > 0.6 ? 'high' : ($optimal_position['confidence'] > 0.4 ? 'medium' : 'low'); ?>">
                                            <?php echo round($optimal_position['confidence'] * 100); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="character-name">
                                    <?php if (!empty($name_parts['prefix'])): ?>
                                        <span class="char-name-prefix"><?php echo htmlspecialchars($name_parts['prefix']); ?></span>
                                    <?php endif; ?>
                                    <span class="char-name-main"><?php echo htmlspecialchars($name_parts['main']); ?></span>
                                </span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>このスキルを持つウマ娘は登録されていません。</p>
            <?php endif; ?>
        </div>
    </div>
</div>  

<style>
.evolution-section {
    margin: 20px 0;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.evolution-section h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 1.2em;
}

.evolution-skills {
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: center;
}

.evolution-skill-item {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    max-width: 500px; /* 幅を広げて説明文を読みやすく */
    width: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.evolution-skill-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.evolution-skill-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.evolution-skill-link h4 {
    margin: 8px 0;
    color: #333;
    font-size: 1.1em;
}

.evolution-skill-link .skill-description {
    color: #666;
    font-size: 0.9em;
    line-height: 1.6; /* 行間を広げて読みやすく */
    margin: 10px 0 0 0;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #007bff;
}

.evolution-arrow {
    font-size: 24px;
    color: #007bff;
    font-weight: bold;
    text-align: center;
    margin: 10px 0;
}

.skill-card-type {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    font-weight: bold;
    background-color: #6c757d;
    color: white;
}

.skill-card-type.type-rare { background-color: #dc3545; }
.skill-card-type.type-evolution { background-color: #28a745; }
.skill-card-type.type-unique { background-color: #fd7e14; }
</style>

<!-- デバッグ用：画像パスと顔検出情報を確認（開発時のみ使用） -->
<?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
<div style="background: #f0f0f0; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px; border-radius: 5px;">
    <strong>🔍 顔検出デバッグ情報:</strong><br><br>
    <?php foreach ($characters as $character): ?>
        <?php
            $debug_image_path = '';
            if (!empty($character['image_url_suit'])) {
                if (strpos($character['image_url_suit'], 'http') === 0) {
                    $debug_image_path = $character['image_url_suit'];
                } else {
                    $debug_image_path = '../' . $character['image_url_suit'];
                }
            } elseif (!empty($character['image_url'])) {
                if (strpos($character['image_url'], 'http') === 0) {
                    $debug_image_path = $character['image_url'];
                } else {
                    $debug_image_path = '../' . $character['image_url'];
                }
            } else {
                $debug_image_path = '../images/default_face.png';
            }
            
            $debug_optimal_position = calculateOptimalImagePosition($debug_image_path, $character['character_name']);
        ?>
        <div style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 3px;">
            <strong><?php echo htmlspecialchars($character['character_name']); ?></strong><br>
            📁 画像パス: <?php echo htmlspecialchars($debug_image_path); ?><br>
            📊 Y位置: <?php echo $debug_optimal_position['background_position_y']; ?>% | 
            📏 サイズ: <?php echo $debug_optimal_position['background_size']; ?>% | 
            🎯 信頼度: <?php echo round($debug_optimal_position['confidence'] * 100); ?>%
        </div>
    <?php endforeach; ?>
    <div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-radius: 3px;">
        <strong>📝 説明:</strong><br>
        • 緑枠: 高精度で顔検出成功 (信頼度60%以上)<br>
        • 黄色点線枠: 低精度での検出 (信頼度20%以上)<br>
        • 右下の数字: 検出信頼度パーセンテージ<br>
        • Y位置: 負の値は上に、正の値は下に画像を移動
    </div>
</div>
<?php endif; ?>

<?php include '../templates/footer.php'; ?>
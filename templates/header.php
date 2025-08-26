<?php
// ▼▼▼ このブロックをファイルの先頭に追加 ▼▼▼
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// DB接続と設定値の読み込み
$db_host_header = 'localhost'; $db_user_header = 'root'; $db_pass_header = ''; $db_name_header = 'umamusume_db';
$conn_header = new mysqli($db_host_header, $db_user_header, $db_pass_header, $db_name_header);
if (!$conn_header->connect_error) {
    $conn_header->set_charset("utf8mb4");
    $result_header = $conn_header->query("SELECT setting_value FROM settings WHERE setting_key = 'edit_mode_enabled'");
    $GLOBALS['edit_mode_enabled'] = ($result_header && $result_header->fetch_assoc()['setting_value'] == 1);
    $conn_header->close();
} else {
    $GLOBALS['edit_mode_enabled'] = false; // DB接続失敗時は安全のためOFFに
}
// ▲▲▲ ここまで ▲▲▲
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'ウマ娘DB'); ?></title>
    
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/base.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/support_card.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/skills.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/racecourses.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/characters.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/pokedex.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/races.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/trained.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/ranks.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/modal.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/tools.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700;800&display=swap" rel="stylesheet">
</head>
<body class="body-fade-in">
<header class="global-header">
    <div class="header-content">
        <a href="<?php echo $base_path; ?>index.php" class="header-logo">ウマ娘DB</a>
        <nav class="nav-links">
            <a href="<?php echo $base_path; ?>index.php" class="<?php if ($current_page === 'home') echo 'active'; ?>">ホーム</a>
            <div class="nav-dropdown">
                <a href="#" class="nav-link <?php if ($current_page === 'characters' || $current_page === 'pokedex') echo 'active'; ?>">ウマ娘 ▼</a>
                <div class="dropdown-content">
                    <a href="<?php echo $base_path; ?>characters/index.php">ウマ娘管理</a>
                    <a href="<?php echo $base_path; ?>pokedex/index.php">ウマ娘図鑑</a>
                    <a href="<?php echo $base_path; ?>trained/index.php">育成ウマ娘</a>
                </div>
            </div>
            <a href="<?php echo $base_path; ?>support_card/index.php" class="<?php if ($current_page === 'support_card') echo 'active'; ?>">サポートカード</a>
            <div class="nav-dropdown">
                <a href="#" class="nav-link <?php if ($current_page === 'skills' || $current_page === 'tools/skill_analyzer') echo 'active'; ?>">スキル ▼</a>
                <div class="dropdown-content">
                    <a href="<?php echo $base_path; ?>skills/index.php">スキル</a>
                    <a href="<?php echo $base_path; ?>tools/skill_analyzer.php">スキル相性診断</a>
               </div>
            </div>
            <a href="<?php echo $base_path; ?>races/index.php" class="<?php if ($current_page === 'races') echo 'active'; ?>">レース</a>
            <a href="<?php echo $base_path; ?>racecourses/index.php" class="<?php if ($current_page === 'racecourses') echo 'active'; ?>">競馬場</a>
            <a href="<?php echo $base_path; ?>settings/index.php" class="<?php if ($current_page === 'settings') echo 'active'; ?>">設定</a>
        </nav>
    </div>
</header>

<header class="scrolling-header">
    <div class="header-content">
        <a href="<?php echo $base_path; ?>index.php" class="header-logo">ウマ娘DB</a>
        <nav class="nav-links">
            <a href="<?php echo $base_path; ?>index.php" class="<?php if ($current_page === 'home') echo 'active'; ?>">ホーム</a>
            <div class="nav-dropdown">
                <a href="#" class="nav-link <?php if ($current_page === 'characters' || $current_page === 'pokedex') echo 'active'; ?>">ウマ娘 ▼</a>
                <div class="dropdown-content">
                    <a href="<?php echo $base_path; ?>characters/index.php">ウマ娘管理</a>
                    <a href="<?php echo $base_path; ?>pokedex/index.php">ウマ娘図鑑</a>
                    <a href="<?php echo $base_path; ?>trained/index.php">育成ウマ娘</a>
                </div>
            </div>
            <a href="<?php echo $base_path; ?>support_card/index.php" class="<?php if ($current_page === 'support_card') echo 'active'; ?>">サポートカード</a>
            <a href="<?php echo $base_path; ?>skills/index.php" class="<?php if ($current_page === 'skills') echo 'active'; ?>">スキル</a>
            <a href="<?php echo $base_path; ?>races/index.php" class="<?php if ($current_page === 'races') echo 'active'; ?>">レース</a>
            <a href="<?php echo $base_path; ?>racecourses/index.php" class="<?php if ($current_page === 'racecourses') echo 'active'; ?>">競馬場</a>
            <a href="<?php echo $base_path; ?>settings/index.php" class="<?php if ($current_page === 'settings') echo 'active'; ?>">設定</a>
        </nav>
    </div>
</header>
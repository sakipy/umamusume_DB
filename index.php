<?php 
$page_title = 'ウマ娘 データベース ホーム'; 

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- メニュー項目をDBから取得 ---
$menu_items = [];
$result = $conn->query("SELECT * FROM homepage_menu ORDER BY sort_order ASC");
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/homepage.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700;800&display=swap" rel="stylesheet">
    
    <style>
        html, body { height: 100%; }
        body { display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .menu { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; width: 100%; max-width: 600px; }
        .menu-item { display: block; text-decoration: none; background-color: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; display: flex; align-items: center; border: 2px solid transparent; }
        .menu-item:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); border-color: var(--gold-color); }
        .menu-item-image { width: 100px; height: 100px; object-fit: contain; flex-shrink: 0; background-color: #f0f0f0; padding: 5px; box-sizing: border-box; }
        .menu-item-text { padding: 20px; font-size: 1.4em; font-weight: 700; color: #4a2e19; }

        /* ▼▼▼ このスタイルを追加 ▼▼▼ */
        .settings-link {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #6c757d;
            color: #fff;
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: all 0.2s ease-in-out;
        }
        .settings-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            background-color: #5a6268;
        }
        /* ▲▲▲ ここまで ▲▲▲ */
    </style>
</head>
<body class="body-fade-in">

    <a href="homepage/index.php" class="settings-link">画像管理</a>
    <div class="container" style="border: none; box-shadow: none; background: none;">
        <h1>ウマ娘 データベース</h1>
        
        <div class="menu">
            <?php foreach ($menu_items as $item): ?>
                <a href="<?php echo htmlspecialchars($item['link_url']); ?>" class="menu-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="menu-item-image">
                    <span class="menu-item-text"><?php echo htmlspecialchars($item['title']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('a');
        links.forEach(function(link) {
            link.addEventListener('click', function(event) {
                const url = this.href;
                if (url.includes('#') || url.startsWith('mailto:') || this.target === '_blank') return;
                event.preventDefault();
                document.body.classList.add('body-fade-out');
                setTimeout(() => { window.location.href = url; }, 300);
            });
        });
    });
    </script>
</body>
</html>
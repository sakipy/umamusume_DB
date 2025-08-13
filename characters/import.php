<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘データをインポート';
$current_page = 'characters';
$base_path = '../';

// 必要なライブラリを読み込む
require_once $base_path . 'vendor/autoload.php';
use Goutte\Client;

$scraped_data = null;
$error_message = '';
$url = '';

// フォームが送信されたらスクレイピングを実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    $client = new Client();
    
    try {
        $crawler = $client->request('GET', $url);
        
        // --- ▼▼▼ ここからスクレイピング処理 ▼▼▼ ---
        // ※注意：以下のセレクタはGameWithのサイト構造が変わると機能しなくなります。
        // その場合は、ブラウザの開発者ツール（F12キー）で新しいセレクタを確認し、修正が必要です。
        
        $scraped_data = [];

        // 名前の取得
        $scraped_data['character_name'] = $crawler->filter('.umamusume-name')->text();
        
        // 初期ステータスの取得
        $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
        $crawler->filter('.status-table-v2 tbody tr')->eq(1)->filter('td')->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['initial_' . $status_map[$i]] = (int)$node->text();
            }
        });

        // 成長率の取得
        $crawler->filter('.status-table-v2 tbody tr')->eq(2)->filter('td')->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['growth_rate_' . $status_map[$i]] = (float)str_replace('%', '', $node->text());
            }
        });

        // 適性の取得
        $aptitude_map = [
            0 => 'surface_aptitude_turf', 1 => 'surface_aptitude_dirt',
            2 => 'distance_aptitude_short', 3 => 'distance_aptitude_mile', 4 => 'distance_aptitude_medium', 5 => 'distance_aptitude_long',
            6 => 'strategy_aptitude_runner', 7 => 'strategy_aptitude_leader', 8 => 'strategy_aptitude_chaser', 9 => 'strategy_aptitude_trailer'
        ];
        $crawler->filter('.tekisei-table td > span')->each(function ($node, $i) use (&$scraped_data, $aptitude_map) {
            if (isset($aptitude_map[$i])) {
                $scraped_data[$aptitude_map[$i]] = $node->text();
            }
        });
        
        // --- ▲▲▲ スクレイピング処理ここまで ▲▲▲ ---

    } catch (Exception $e) {
        $error_message = "情報の取得に失敗しました。URLが正しいか、またはサイトの構造が変更されていないか確認してください。<br>エラー: " . $e->getMessage();
    }
}

// スクレイピングしたデータをセッションに保存してadd.phpに渡す
if ($scraped_data) {
    session_start();
    $_SESSION['scraped_data'] = $scraped_data;
    header('Location: add.php');
    exit;
}

include '../templates/header.php';
?>
<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p>GameWithのウマ娘個別ページのURLを貼り付けてください。<br>例: <code>https://gamewith.jp/uma-musume/article/show/255224</code></p>
    
    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="import.php" method="POST">
        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" id="url" name="url" placeholder="https://gamewith.jp/uma-musume/article/show/..." value="<?php echo htmlspecialchars($url); ?>" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="button-primary">情報を読み込む</button>
            <a href="index.php" class="back-link">キャンセル</a>
        </div>
    </form>
</div>
<?php include '../templates/footer.php'; ?>
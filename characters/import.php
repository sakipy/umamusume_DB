<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘データをインポート';
$current_page = 'characters';
$base_path = '../';

// 必要なライブラリを読み込む
require_once $base_path . 'vendor/autoload.php';
use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;

$scraped_data = null;
$error_message = '';
$url = '';

// フォームが送信されたらスクレイピングを実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    $client = new Client(HttpClient::create(['timeout' => 60, 'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ]]));
    
    try {
        $crawler = $client->request('GET', $url);
        
        // --- ▼▼▼【最終修正版】ここからスクレイピング処理 ▼▼▼ ---
        $scraped_data = [];

        // 名前の取得 (h2#hyokaの見出しから取得)
        $nameNode = $crawler->filter('h2#hyoka');
        if ($nameNode->count() === 0) {
            throw new Exception("ウマ娘の名前が見つかるh2タグ(id='hyoka')が見つかりませんでした。");
        }
        $name_with_suffix = $nameNode->text();
        $scraped_data['character_name'] = str_replace('の評価', '', $name_with_suffix);
        
        // h3の見出し「基礎能力と成長率」を基準にする
        $base_h3 = $crawler->filter('h3:contains("基礎能力と成長率")');
        if ($base_h3->count() === 0) {
            throw new Exception("「基礎能力と成長率」のh3見出しが見つかりませんでした。");
        }
        
        // h4の見出しから各テーブルを特定
        $initialStatusTableNode = $base_h3->nextAll()->filter('h4:contains("基礎能力")')->first()->nextAll()->filter('table')->first();
        $growthRateTableNode = $base_h3->nextAll()->filter('h4:contains("成長率")')->first()->nextAll()->filter('table')->first();

        if ($initialStatusTableNode->count() === 0) throw new Exception("初期ステータステーブルが見つかりませんでした。");
        if ($growthRateTableNode->count() === 0) throw new Exception("成長率テーブルが見つかりませんでした。");

        // 初期ステータスの取得
        $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
        $initialStatusNodes = $initialStatusTableNode->filter('tbody tr')->eq(1)->filter('td');
        if ($initialStatusNodes->count() < 5) throw new Exception("初期ステータスのデータ数が不足しています。");
        
        $initialStatusNodes->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['initial_' . $status_map[$i]] = (int)preg_replace('/[^0-9]/', '', $node->text());
            }
        });

        // 成長率の取得
        $growthRateNodes = $growthRateTableNode->filter('tbody tr')->eq(0)->filter('td');
        if ($growthRateNodes->count() < 5) throw new Exception("成長率のデータ数が不足しています。");
        
        $growthRateNodes->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['growth_rate_' . $status_map[$i]] = (float)str_replace('%', '', $node->text());
            }
        });

        // h3の見出し「初期適性」を基準にテーブルを特定
        $aptitudeTableNode = $crawler->filter('h3:contains("初期適性")')->nextAll()->filter('table')->first();
        if ($aptitudeTableNode->count() === 0) {
            throw new Exception("適性テーブルが見つかりませんでした。");
        }
        
        function getRankFromSrc($src) {
            if (preg_match('/i_rank_([A-G])p?\.png/', $src, $matches)) {
                $rank = $matches[1];
                if (strpos($src, 'p.png') !== false) {
                    $rank .= '+';
                }
                return $rank;
            }
            return '';
        }

        $aptitudeTableNode->filter('tbody tr')->each(function ($tr, $rowIndex) use (&$scraped_data) {
            $aptitude_keys = [
                0 => ['surface_aptitude_turf', 'surface_aptitude_dirt'],
                1 => ['distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long'],
                2 => ['strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer']
            ];
            if(isset($aptitude_keys[$rowIndex])) {
                $keys = $aptitude_keys[$rowIndex];
                $tr->filter('td img')->each(function ($imgNode, $i) use (&$scraped_data, $keys) {
                    if (isset($keys[$i])) {
                        $src = $imgNode->attr('data-original') ?: $imgNode->attr('src');
                        $scraped_data[$keys[$i]] = getRankFromSrc($src);
                    }
                });
            }
        });
        
        // --- ▲▲▲ スクレイピング処理ここまで ▲▲▲ ---

    } catch (Exception $e) {
        $error_message = "情報の取得に失敗しました。URLが正しいか、またはサイトの構造が変更されていないか確認してください。<br>エラー: " . $e->getMessage();
    }
}

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
    <p>GameWithのウマ娘個別ページのURLを貼り付けてください。<br>例: <code>https://gamewith.jp/uma-musume/article/show/345496</code></p>
    
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
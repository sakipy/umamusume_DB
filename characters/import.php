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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    $client = new Client(HttpClient::create(['timeout' => 60]));
    
    try {
        $crawler = $client->request('GET', $url);
        
        $scraped_data = [];

        // 名前の取得
        $nameNode = $crawler->filter('h2#hyoka');
        if ($nameNode->count() === 0) {
            throw new Exception("名前の基準となる見出し(h2#hyoka)が見つかりませんでした。");
        }
        $scraped_data['character_name'] = str_replace('の評価', '', $nameNode->text());
        
        // h3「基礎能力と成長率」を見つける
        $base_h3 = $crawler->filter('h3:contains("基礎能力と成長率")');
        if ($base_h3->count() === 0) {
            throw new Exception("「基礎能力と成長率」の見出しが見つかりませんでした。");
        }

        // h4の見出しから各テーブルを特定
        $initialStatusTable = $base_h3->nextAll()->filter('h4:contains("基礎能力")')->first()->nextAll()->filter('.uma_fix_table table')->first();
        $growthRateTable = $base_h3->nextAll()->filter('h4:contains("成長率")')->first()->nextAll()->filter('.uma_fix_table table')->first();

        if ($initialStatusTable->count() === 0) throw new Exception("初期ステータステーブルが見つかりませんでした。");
        if ($growthRateTable->count() === 0) throw new Exception("成長率テーブルが見つかりませんでした。");

        // 初期ステータスの取得
        $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
        $initialStatusTable->filter('tbody tr')->eq(1)->filter('td')->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['initial_' . $status_map[$i]] = (int)preg_replace('/[^0-9]/', '', $node->text());
            }
        });

        // 成長率の取得
        $growthRateTable->filter('tbody tr')->eq(0)->filter('td')->each(function ($node, $i) use (&$scraped_data, $status_map) {
            if (isset($status_map[$i])) {
                $scraped_data['growth_rate_' . $status_map[$i]] = (float)str_replace('%', '', $node->text());
            }
        });

        // 適性テーブルの特定とデータ取得
        $aptitudeTable = $crawler->filter('h3:contains("初期適性")')->nextAll()->filter('.uma_fix_table table')->first();
        if ($aptitudeTable->count() === 0) throw new Exception("適性テーブルが見つかりませんでした。");

        function getRankFromSrc($src) {
            // "i_rank_Gp.png" のような形式からランクを抽出
            if (preg_match('/i_rank_([A-G])(p?)\.png/', $src, $matches)) {
                // $matches[1] は 'G', $matches[2] は 'p' または空
                return $matches[1] . ($matches[2] === 'p' ? '+' : '');
            }
            return 'G'; // 見つからない場合はデフォルト
        }
        
        $aptitudes_map = [
            'バ場' => ['surface_aptitude_turf', 'surface_aptitude_dirt'],
            '距離' => ['distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long'],
            '脚質' => ['strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer']
        ];
        
        $aptitudeTable->filter('tbody tr')->each(function ($tr) use (&$scraped_data, $aptitudes_map) {
            $thText = $tr->filter('th')->text();
            if (isset($aptitudes_map[$thText])) {
                $keys = $aptitudes_map[$thText];
                $tr->filter('td img')->each(function ($imgNode, $i) use (&$scraped_data, $keys) {
                    if (isset($keys[$i])) {
                        $src = $imgNode->attr('data-original') ?: $imgNode->attr('src');
                        $scraped_data[$keys[$i]] = getRankFromSrc($src);
                    }
                });
            }
        });
        
    } catch (Exception $e) {
        $error_message = "情報の取得に失敗しました。<br>エラー: " . $e->getMessage();
    }
}

// データをセッションに保存してリダイレクト
if ($scraped_data && empty($error_message)) {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
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
<?php
// ========== ページ設定 ==========
$page_title = 'Game8から図鑑データをインポート';
$current_page = 'pokedex';
$base_path = '../';

// Composerのオートローダーを読み込む
require '../vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;

// 処理のタイムアウトを無制限に設定
set_time_limit(0);

$message = '';
$error_message = '';
$imported_characters = [];

// ========== DB接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// --- 既存のウマ娘名を全て取得しておく ---
$existing_characters = [];
$result_existing = $conn->query("SELECT pokedex_name FROM pokedex");
while ($row = $result_existing->fetch_assoc()) {
    $existing_characters[] = $row['pokedex_name'];
}

// --- POSTリクエスト（インポート実行）の場合の処理 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $client = new Client(HttpClient::create(['timeout' => 120, 'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]]));
        
        $crawler = $client->request('GET', 'https://game8.jp/umamusume/225382');

        $character_links = $crawler->filter('.c-card-grid__item a.a-link--card')->each(function ($node) {
            return $node->attr('href');
        });

        if (empty($character_links)) {
            throw new Exception("Game8からキャラクターの一覧を取得できませんでした。サイトの構造が変更された可能性があります。");
        }

        $conn->begin_transaction();

        foreach ($character_links as $link) {
            sleep(1); 
            
            $char_crawler = $client->request('GET', $link);
            
            $pokedex_name_node = $char_crawler->filter('h1.a-article__title');
            if ($pokedex_name_node->count() === 0) continue;
            $pokedex_name = trim($pokedex_name_node->text());
            
            if (in_array($pokedex_name, $existing_characters)) {
                $imported_characters[] = ['name' => $pokedex_name, 'status' => 'スキップ (登録済み)'];
                continue;
            }

            // --- ▼▼▼ プロフィールテーブルから全てのデータを動的に取得 (ご指摘のセレクタを使用) ▼▼▼ ---
            $profile = [];
            // "プロフィール"という見出しを持つテーブル内のtbody trをループ
            $char_crawler->filter('h2.a-header-2:contains("プロフィール") + .a-table-responsive table.a-table tbody tr')->each(function ($tr_node) use (&$profile) {
                $key_node = $tr_node->filter('th');
                $value_node = $tr_node->filter('td');
                if ($key_node->count() > 0 && $value_node->count() > 0) {
                    $key = trim($key_node->text());
                    $value = trim($value_node->text());
                    $profile[$key] = $value;
                }
            });

            // 取得したデータを各変数に格納
            $cv = $profile['CV'] ?? '未登録';
            $birthday = $profile['誕生日'] ?? '未登録';
            $height = $profile['身長'] ?? '未登録';
            $weight = $profile['体重'] ?? '未登録';
            $three_sizes = $profile['スリーサイズ'] ?? '未登録';
            
            $description_node = $char_crawler->filter('.a-big-img-box__txt');
            $description = $description_node->count() > 0 ? trim($description_node->text()) : '（説明文取得失敗）';

            // --- 画像のダウンロード処理 ---
            function download_image($url, $save_dir, $prefix) {
                if(empty($url)) return null;
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                $image_content = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if($http_code != 200 || $image_content === false) return null;
                
                if (!file_exists($save_dir)) { mkdir($save_dir, 0777, true); }
                $file_name = time() . '_' . $prefix . '_' . uniqid() . '.png';
                $save_path = $save_dir . $file_name;
                
                file_put_contents($save_path, $image_content);
                return 'uploads/pokedex/' . $file_name;
            }
            
            $save_directory = '../uploads/pokedex/';
            $face_image_url_node = $char_crawler->filter('.a-big-img-box__img-container img');
            $face_image_url = $face_image_url_node->count() ? download_image($face_image_url_node->attr('data-src'), $save_directory, 'face') : null;
            
            $winning_outfit_image_url = $face_image_url;
            $uniform_image_url = $face_image_url;
            
            // --- データベースへの登録 ---
            $stmt = $conn->prepare(
                "INSERT INTO pokedex (
                    pokedex_name, cv, birthday, height, weight, three_sizes, description, 
                    winning_outfit_image_url, face_image_url, uniform_image_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssssssss",
                $pokedex_name, $cv, $birthday, $height, $weight, $three_sizes, $description,
                $winning_outfit_image_url, $face_image_url, $uniform_image_url
            );
            $stmt->execute();
            $stmt->close();
            $imported_characters[] = ['name' => $pokedex_name, 'status' => '成功'];
            $existing_characters[] = $pokedex_name;
        }
        $conn->commit();
        $message = "インポート処理が完了しました。";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "処理中にエラーが発生しました: " . $e->getMessage();
    }
}
$conn->close();

include '../templates/header.php';
?>

<div class="container">
    <h1>Game8から図鑑データをインポート</h1>
    <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="instruction-box">
        <h3>実行前の注意</h3>
        <ul>
            <li>この処理は、ゲーム攻略サイト「Game8」のキャラクターページにアクセスし、情報を自動で取得します。</li>
            <li>サイトの利用規約によっては、このような自動アクセスが禁止されている場合があります。**自己責任**で実行してください。</li>
            <li>処理には数分かかる場合があります。処理が完了するまでページを閉じないでください。</li>
            <li>既に図鑑に登録されているウマ娘は、名前が完全に一致する場合、自動的にスキップされます。</li>
        </ul>
    </div>

    <form action="scrape.php" method="POST" style="margin-top: 30px;">
        <button type="submit" style="width: 100%;" onclick="return confirm('Game8からデータを取得します。よろしいですか？');">インポート実行</button>
    </form>
    
    <?php if (!empty($imported_characters)): ?>
        <h3 style="margin-top: 30px;">処理結果</h3>
        <table class="skill-table">
            <thead>
                <tr>
                    <th>ウマ娘名</th>
                    <th>結果</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($imported_characters as $char): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($char['name']); ?></td>
                        <td><?php echo htmlspecialchars($char['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="index.php" class="back-link" style="margin-top: 24px;">&laquo; 図鑑一覧に戻る</a>
</div>

<style>
.instruction-box { background: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 20px; color: #713f12; }
</style>

<?php include '../templates/footer.php'; ?>
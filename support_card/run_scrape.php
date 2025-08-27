<?php
// エラーを画面に表示する設定
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // 処理のタイムアウトを無制限に

// リアルタイム出力のためのおまじない
if (ob_get_level() == 0) ob_start();

// Composerのオートローダーを読み込む
require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../includes/db_connection.php'; // DB接続

use Goutte\Client as GoutteClient;
use Symfony\Component\HttpClient\HttpClient;

// ログ出力用の関数
function log_message($message, $type = 'info') {
    $color = '';
    switch ($type) {
        case 'success': $color = '#28a745'; break;
        case 'error': $color = '#dc3545'; break;
        case 'warn': $color = '#b98900'; break; // 黄色のテキストは見えにくいので調整
        case 'info': $color = '#6c757d'; break;
    }
    $bgColor = ($type == 'warn') ? 'background: #fff3cd;' : '';
    echo "<div style='font-family: monospace; line-height: 1.6; color: {$color}; {$bgColor} border-bottom: 1px solid #eee; padding: 2px 5px;'><strong>" . date('[H:i:s]') . "</strong> " . htmlspecialchars($message) . "</div>";
    ob_flush();
    flush();
    usleep(10000); // 描画のためのウェイト
}

// URL結合用の関数
function urljoin($base, $relative) {
    if (parse_url($relative, PHP_URL_SCHEME) !== null) return $relative;
    if (strpos($relative, "//") === 0) return "https:" . $relative;
    $base_parts = parse_url($base);
    if ($base_parts === false) return $relative;
    $scheme = $base_parts['scheme'] ?? 'https';
    $host = $base_parts['host'] ?? '';
    $path = $base_parts['path'] ?? '/';
    if (substr($relative, 0, 1) === '/') return "{$scheme}://{$host}{$relative}";
    $path = preg_replace('/[^\/]+$/', '', $path);
    return "{$scheme}://{$host}{$path}{$relative}";
}

// サポートカード詳細ページから追加情報を取得
function getCardDetailsFromUrl($detail_url) {
    $additional_info = [
        'character_name' => '',
        'max_speed' => 0,
        'max_stamina' => 0,
        'max_power' => 0,
        'max_guts' => 0,
        'max_wisdom' => 0,
        'skills' => []
    ];

    try {
        $client = new GoutteClient(HttpClient::create(['timeout' => 30]));
        $detail_crawler = $client->request('GET', $detail_url);
        
        // ウマ娘名の取得
        $detail_crawler->filter('h1, h2, h3')->each(function($node) use (&$additional_info) {
            $text = trim($node->text());
            // "【SSR】スペシャルウィーク（ススメ、カメラは回ってる！）"のような形式から抽出
            if (preg_match('/【[SR]+】([^（]+)/', $text, $matches)) {
                $additional_info['character_name'] = trim($matches[1]);
            }
        });

        // 能力値の取得（テーブルから）
        $detail_crawler->filter('table')->each(function($table) use (&$additional_info) {
            $table->filter('tr')->each(function($row) use (&$additional_info) {
                $cells = $row->filter('td');
                if ($cells->count() >= 2) {
                    $label = trim($cells->eq(0)->text());
                    $value = trim($cells->eq(1)->text());
                    
                    // 数値のみ抽出
                    if (preg_match('/(\d+)/', $value, $matches)) {
                        $num_value = intval($matches[1]);
                        
                        if (strpos($label, 'スピード') !== false) {
                            $additional_info['max_speed'] = $num_value;
                        } elseif (strpos($label, 'スタミナ') !== false) {
                            $additional_info['max_stamina'] = $num_value;
                        } elseif (strpos($label, 'パワー') !== false) {
                            $additional_info['max_power'] = $num_value;
                        } elseif (strpos($label, '根性') !== false) {
                            $additional_info['max_guts'] = $num_value;
                        } elseif (strpos($label, '賢さ') !== false) {
                            $additional_info['max_wisdom'] = $num_value;
                        }
                    }
                }
            });
        });

        // スキル情報の取得
        $detail_crawler->filter('.skill-name, .skill-title, h4, h5')->each(function($node) use (&$additional_info) {
            $text = trim($node->text());
            // スキル名らしいものを抽出（「〇〇のコツ」「〇〇」など）
            if (!empty($text) && 
                strlen($text) > 2 && 
                strlen($text) < 50 && 
                !preg_match('/評価|攻略|一覧|詳細|情報/', $text) &&
                (strpos($text, 'コツ') !== false || 
                 strpos($text, '練習') !== false || 
                 strpos($text, '友情') !== false ||
                 preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $text))) {
                if (!in_array($text, $additional_info['skills'])) {
                    $additional_info['skills'][] = $text;
                }
            }
        });

    } catch (Exception $e) {
        log_message("詳細取得エラー: " . $e->getMessage(), 'warn');
    }

    return $additional_info;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サポートカード インポート処理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f4f7f9; color: #333; line-height: 1.6; padding: 20px; }
        .log-container { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { text-align: center; color: #444; }
        .final-message { text-align: center; font-size: 1.2em; font-weight: bold; margin-top: 20px; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="log-container">
        <h1><i class="fas fa-cogs"></i> サポートカードインポート処理実行中...</h1>
<?php
try {
    $client = new GoutteClient(HttpClient::create(['timeout' => 60]));
    $url = 'https://gamewith.jp/uma-musume/article/show/255035';
    log_message("ターゲットURLからHTMLを取得します: {$url}");

    $crawler = $client->request('GET', $url);
    log_message("HTMLの取得に成功しました。");

    // 既存のカード名リストを取得
    $existing_cards_result = $conn->query("SELECT card_name FROM support_cards");
    $existing_cards = [];
    while($row = $existing_cards_result->fetch_assoc()) {
        $existing_cards[] = $row['card_name'];
    }
    log_message(count($existing_cards) . "件の既存サポートカードを読み込みました。");

    $rarity_map = ['SSR' => 'SSR', 'SR' => 'SR', 'R' => 'R'];
    $type_map = [
        'speed' => 'スピード', 'stamina' => 'スタミナ', 'power' => 'パワー',
        'guts' => '根性', 'wisdom' => '賢さ', 'friend' => '友人', 'group' => 'グループ'
    ];
    
    $import_count = 0;
    $skip_count = 0;
    $error_count = 0;

    // キャラクター名から図鑑IDを取得する関数
    function getCharacterIdFromName($character_name, $conn) {
        if (empty($character_name)) return null;
        
        $stmt = $conn->prepare("SELECT id FROM characters WHERE name LIKE ? OR name_kana LIKE ? LIMIT 1");
        $name_pattern = '%' . $character_name . '%';
        $stmt->bind_param("ss", $name_pattern, $name_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row['id'] : null;
    }

    // スキル名からスキルIDを取得し、関連付けを保存する関数
    function saveCardSkills($card_id, $skills, $conn) {
        if (empty($skills) || !is_array($skills)) return;
        
        // まずsupport_card_skillsテーブルが存在するかチェック
        $table_check = $conn->query("SHOW TABLES LIKE 'support_card_skills'");
        if ($table_check->num_rows == 0) {
            log_message("support_card_skillsテーブルが存在しません。スキル関連付けをスキップします。", 'warn');
            return;
        }
        
        foreach ($skills as $skill_name) {
            $skill_name = trim($skill_name);
            if (empty($skill_name)) continue;
            
            $stmt = $conn->prepare("SELECT id FROM skills WHERE skill_name LIKE ? LIMIT 1");
            $skill_pattern = '%' . $skill_name . '%';
            $stmt->bind_param("s", $skill_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $skill_row = $result->fetch_assoc();
            $stmt->close();
            
            if ($skill_row) {
                $stmt = $conn->prepare("INSERT IGNORE INTO support_card_skills (support_card_id, skill_id, skill_relation, skill_order) VALUES (?, ?, ?, ?)");
                $skill_relation = 'and'; // デフォルトはAND関係
                $skill_order = count($current_skill_relations);
                $stmt->bind_param("iisi", $card_id, $skill_row['id'], $skill_relation, $skill_order);
                if ($stmt->execute()) {
                    log_message("スキル関連付け: {$skill_name} (ID: {$skill_row['id']})", 'info');
                }
                $stmt->close();
            } else {
                log_message("未登録スキル: {$skill_name}", 'warn');
            }
        }
    }

    foreach ($rarity_map as $rarity => $table_id) {
        log_message("------------ {$rarity} レアリティの処理を開始 ------------", 'info');
        
        $crawler->filter("div#{$table_id} table tbody tr")->each(function ($node) use (&$conn, &$existing_cards, &$import_count, &$skip_count, &$error_count, $rarity, $type_map, $url) {
            
            if ($node->filter('td')->count() < 2) return;

            // カード名とリンク
            $nameNode = $node->filter('td')->eq(0)->filter('a');
            $card_name = $nameNode->count() ? trim($nameNode->text()) : '';
            $detail_link = $nameNode->count() ? $nameNode->attr('href') : '';

            // タイプの画像SRC
            $typeImgNode = $node->filter('td')->eq(1)->filter('img');
            $type_src = $typeImgNode->count() ? $typeImgNode->attr('src') : '';

            // 画像URL
            $imgNode = $node->filter('td')->eq(0)->filter('img');
            $image_url = $imgNode->count() ? $imgNode->attr('data-original') : '';

            if (empty($card_name) || empty($image_url)) {
                log_message("データが不完全なためスキップします。", 'warn');
                return;
            }
            
            // タイプの判別
            $card_type = 'その他';
            foreach ($type_map as $key => $value) {
                if (strpos($type_src, $key) !== false) {
                    $card_type = $value;
                    break;
                }
            }
            
            // 既存チェック
            if (in_array($card_name, $existing_cards)) {
                log_message("[既存] {$card_name}", 'info');
                $skip_count++;
                return;
            }

            // 詳細ページから追加情報を取得
            $additional_info = [];
            if (!empty($detail_link)) {
                $detail_url = urljoin($url, $detail_link);
                log_message("詳細情報取得中: {$card_name}", 'info');
                $additional_info = getCardDetailsFromUrl($detail_url);
                usleep(500000); // 0.5秒待機
            }

            // 画像ダウンロード
            $image_full_url = urljoin($url, $image_url);
            $image_data = @file_get_contents($image_full_url);

            if (!$image_data) {
                log_message("[画像エラー] {$card_name} の画像ダウンロードに失敗しました。URL: {$image_full_url}", 'error');
                $error_count++;
                return;
            }

            $upload_dir = __DIR__ . '/../uploads/support_cards/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $safe_filename = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $card_name);
            $image_extension = pathinfo(parse_url($image_full_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = time() . '_' . $safe_filename . '.' . $image_extension;
            $db_path = 'uploads/support_cards/' . $filename;
            $save_path = $upload_dir . $filename;
            
            file_put_contents($save_path, $image_data);
            log_message("画像保存成功: {$db_path}");

            // ウマ娘図鑑との紐づけ
            $character_id = null;
            if (!empty($additional_info['character_name'])) {
                $character_id = getCharacterIdFromName($additional_info['character_name'], $conn);
                if ($character_id) {
                    log_message("図鑑ID {$character_id} と紐づけました: {$additional_info['character_name']}");
                }
            }

            // DBに保存（能力値も含む）
            $stmt = $conn->prepare("INSERT INTO support_cards (card_name, rarity, support_type, character_id, max_speed, max_stamina, max_power, max_guts, max_wisdom, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("sssiiiiiss", 
                $card_name, 
                $rarity, 
                $card_type, 
                $character_id,
                $additional_info['max_speed'] ?? 0,
                $additional_info['max_stamina'] ?? 0,
                $additional_info['max_power'] ?? 0,
                $additional_info['max_guts'] ?? 0,
                $additional_info['max_wisdom'] ?? 0,
                $db_path
            );

            if ($stmt->execute()) {
                $card_id = $conn->insert_id;
                log_message("[成功] {$card_name} をデータベースに登録しました。(ID: {$card_id})", 'success');
                
                // スキル情報の保存
                if (!empty($additional_info['skills'])) {
                    saveCardSkills($card_id, $additional_info['skills'], $conn);
                    log_message("スキル情報も保存しました: " . count($additional_info['skills']) . "個", 'success');
                }
                
                $import_count++;
                $existing_cards[] = $card_name;
            } else {
                log_message("[DBエラー] {$card_name} のデータベース登録に失敗: " . $stmt->error, 'error');
                $error_count++;
                unlink($save_path); // 失敗したら画像を削除
            }
            $stmt->close();
        });
    }

    log_message("------------ 処理完了 ------------", 'info');
    
    // データベース統計の取得
    $total_cards_result = $conn->query("SELECT COUNT(*) as count FROM support_cards");
    $total_cards = $total_cards_result->fetch_assoc()['count'];
    
    $linked_cards_result = $conn->query("SELECT COUNT(*) as count FROM support_cards WHERE character_id IS NOT NULL");
    $linked_cards = $linked_cards_result->fetch_assoc()['count'];
    
    echo "<div class='final-message' style='background-color: #eafaf1; color: #28a745;'>";
    echo "<strong><i class='fas fa-check-circle'></i> 全ての処理が完了しました</strong><br>";
    echo "新規追加: {$import_count}件 | 既存スキップ: {$skip_count}件 | エラー: {$error_count}件<br>";
    echo "データベース総数: {$total_cards}件 | 図鑑連携済み: {$linked_cards}件";
    echo "</div>";

    log_message("=== 最終結果 ===", 'success');
    log_message("新規追加: {$import_count}件", 'success');
    log_message("既存スキップ: {$skip_count}件", 'info');
    log_message("エラー: {$error_count}件", ($error_count > 0 ? 'error' : 'info'));
    log_message("データベース総数: {$total_cards}件", 'info');
    log_message("図鑑連携済み: {$linked_cards}件", 'info');

} catch (Exception $e) {
    log_message("致命的なエラーが発生しました: " . $e->getMessage(), 'error');
    echo "<div class='final-message' style='background-color: #fce8e6; color: #dc3545;'><strong><i class='fas fa-times-circle'></i> 処理が異常終了しました。</strong></div>";
} finally {
    $conn->close();
}
?>
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" style="text-decoration: none; padding: 10px 20px; background-color: #007bff; color: #fff; border-radius: 5px;">サポートカード一覧に戻る</a>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>
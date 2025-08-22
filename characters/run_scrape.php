<?php
// エラーを画面に表示する設定
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // 処理のタイムアウトを無制限に

// リアルタイム出力のためのおまじない
if (ob_get_level() == 0) ob_start();

// Composerのオートローダーを読み込む
require_once __DIR__ . '/../vendor/autoload.php';

use Goutte\Client as GoutteClient;
use Symfony\Component\HttpClient\HttpClient;

// ログ出力用の関数
function log_message($message, $type = 'info') {
    echo "<div class='log-entry log-{$type}'>" . date('[H:i:s]') . " " . htmlspecialchars($message) . "</div>";
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

    if (substr($relative, 0, 1) === '/') return "$scheme://$host$relative";

    $path = preg_replace('#/[^/]*$#', '/', $path);
    $absolute_path = $path . $relative;

    while (preg_match('#/[^/]+/\.\./#', $absolute_path)) {
        $absolute_path = preg_replace('#/[^/]+/\.\./#', '/', $absolute_path);
    }
    return "$scheme://$host$absolute_path";
}

// 適性ランク取得用の関数
function getRankFromElement($element) {
    $img = $element->filter('img');
    if ($img->count() > 0) {
        $src = $img->attr('data-original') ?: $img->attr('src');
        if ($src && preg_match('/i_rank_([A-G])(p?)\.png/i', $src, $matches)) {
            return $matches[1] . ($matches[2] === 'p' ? '+' : '');
        }
        if ($src && preg_match('/rank[_-]([A-G])/i', $src, $matches)) {
            return strtoupper($matches[1]);
        }
    }
    $text = trim($element->text());
    if (preg_match('/^([A-G]\+?)$/', $text, $matches)) {
        return $matches[1];
    }
    $class = $element->attr('class');
    if ($class && preg_match('/rank[_-]([A-G])/i', $class, $matches)) {
        return strtoupper($matches[1]);
    }
    return null;
}

// ▼▼▼【追加】スキル関連の関数をimportから移植 ▼▼▼
function getSkillIdByName($conn, $skill_name) {
    $stmt = $conn->prepare("SELECT id FROM skills WHERE skill_name = ?");
    $stmt->bind_param("s", $skill_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    $stmt->close();
    return null;
}

function registerOrUpdateSkill($conn, $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type, $base_skill_id = null, $required_skill_points = null) {
    // 1. スキルが既に存在するか確認
    $stmt = $conn->prepare("SELECT id FROM skills WHERE skill_name = ?");
    $stmt->bind_param("s", $skill_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        // 2. 存在する場合：UPDATEで情報を更新
        $row = $result->fetch_assoc();
        $existing_id = $row['id'];
        
        $stmt_update = $conn->prepare(
            "UPDATE skills SET skill_description = ?, skill_type = ?, distance_type = ?, strategy_type = ?, surface_type = ?, base_skill_id = COALESCE(?, base_skill_id), required_skill_points = COALESCE(?, required_skill_points) WHERE id = ?"
        );
        $stmt_update->bind_param("sssssiii", $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type, $base_skill_id, $required_skill_points, $existing_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        return $existing_id;
    } else {
        // 3. 存在しない場合：INSERTで新規登録
        $stmt_insert = $conn->prepare(
            "INSERT INTO skills (skill_name, skill_description, skill_type, distance_type, strategy_type, surface_type, base_skill_id, required_skill_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert->bind_param("ssssssii", $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type, $base_skill_id, $required_skill_points);
        
        if ($stmt_insert->execute()) {
            $new_id = $conn->insert_id;
            $stmt_insert->close();
            return $new_id;
        } else {
            $error = $stmt_insert->error;
            $stmt_insert->close();
            throw new Exception("スキル登録に失敗: " . $error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>一括インポート実行ログ</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        body { 
            background: #f4f7f9; 
        }
        .container { 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px; 
            background: #fff; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            text-align: center; 
        }
        #log-container { 
            background-color: #2c3e50; 
            color: #ecf0f1; 
            font-family: 'Courier New', Courier, monospace; 
            padding: 20px; 
            border-radius: 8px; 
            height: 60vh; 
            overflow-y: auto; 
            font-size: 14px; 
            line-height: 1.6; 
            border: 1px solid #34495e; 
        }
        .log-entry { 
            margin-bottom: 5px; 
        }
        .log-success { 
            color: #2ecc71; 
        }
        .log-error { 
            color: #e74c3c; 
            font-weight: bold; 
        }
        .log-warning { 
            color: #f39c12; 
        }
        .log-info { 
            color: #3498db; 
        }
    </style>
</head>
<body class="body-fade-in">
    <div class="container">
        <h1>一括インポート実行ログ</h1>
        <div id="log-container">
<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_message("不正なアクセスです。", "error");
    die("</body></html>");
}

log_message("処理を開始します...");

// データベース接続
$conn = new mysqli('localhost', 'root', '', 'umamusume_db');
if ($conn->connect_error) {
    log_message("DB接続失敗: " . $conn->connect_error, "error");
    die("</body></html>");
}

// 文字コード設定を明示的に行う
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");

log_message("データベースに接続しました。文字コード: " . $conn->character_set_name(), "success");

// 既存キャラクターの取得
$existing_characters = [];
$result = $conn->query("SELECT character_name FROM characters");
while ($row = $result->fetch_assoc()) {
    $existing_characters[] = $row['character_name'];
}
log_message(count($existing_characters) . "件の既存ウマ娘データを読み込みました。");

// 図鑑データの取得
$pokedex_map = [];
$result = $conn->query("SELECT id, pokedex_name FROM pokedex");
while ($row = $result->fetch_assoc()) {
    $pokedex_map[trim($row['pokedex_name'])] = $row['id'];
}
log_message(count($pokedex_map) . "件の図鑑データを読み込みました。");

// 次のIDを取得
$result = $conn->query("SELECT MAX(id) as max_id FROM characters");
$next_id = ($result->fetch_assoc()['max_id'] ?? 0) + 1;
log_message("次のIDは " . $next_id . " です。");

$transaction_started = false;
try {
    // HTTPクライアントの設定
    $client = new GoutteClient(HttpClient::create([
        'timeout' => 60,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]));
    
    // GameWithのウマ娘一覧ページにアクセス
    $crawler = $client->request('GET', 'https://gamewith.jp/uma-musume/article/show/253241');
    
    // キャラクター個別ページのURLを取得
    $character_links = [];
    $crawler->filter('a[href*="/uma-musume/article/show/"]')->each(function ($node) use (&$character_links) {
        $url = $node->attr('href');
        if (preg_match('/\/article\/show\/\d+/', $url)) {
            $character_links[] = $url;
        }
    });
    
    if (empty($character_links)) {
        throw new Exception("キャラクターのURLが取得できませんでした。");
    }
    
    $character_links = array_unique($character_links);
    log_message(count($character_links) . "件のキャラクターURLを取得しました。", "success");

    // トランザクション開始
    $conn->begin_transaction();
    $transaction_started = true;
    
    $import_count = 0;
    foreach ($character_links as $i => $link) {
        log_message("--------------------");
        log_message(($i + 1) . "/" . count($character_links) . ": " . $link);
        
        // 個別のトランザクション開始
        $conn->begin_transaction();
        
        try {
            $char_crawler = $client->request('GET', $link);
            sleep(rand(1, 2)); // 負荷軽減
            
            // 1. 名前の取得部分を修正
            $character_name = '';
            try {
                $name_node = $char_crawler->filter('h2#hyoka');
                if ($name_node->count() > 0) {
                    // 文字列を取得（この時点でGoutteがUTF-8に変換済み）
                    $raw_name = $name_node->text();
                    log_message("取得した生のテキスト: " . $raw_name, "info");

                    // 1. 不要な空白と制御文字を除去
                    $clean_name = trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $raw_name));

                    // 2. Unicode正規化 (NFC形式) を行い、文字表現を統一
                    if (class_exists('Normalizer')) {
                        $normalized_name = Normalizer::normalize($clean_name, Normalizer::FORM_C);
                    } else {
                        $normalized_name = $clean_name;
                    }

                    // 3. 伸ばし棒やハイフンなどを全角長音符「ー」に統一
                    $name_with_normalized_bar = str_replace(['―', '－', '-', '━', '─'], 'ー', $normalized_name);
                    
                    // 4. 「の評価」という接尾辞を除去
                    $character_name = str_replace('の評価', '', $name_with_normalized_bar);
                    
                    // 文字化けのフォールバック処理
                    if (empty($character_name) || !mb_check_encoding($character_name, 'UTF-8')) {
                        log_message("名前の解析に失敗したため、タイトルから再取得を試みます。", "warning");
                        $title = $char_crawler->filter('title')->text();
                        if (preg_match('/^(.+?)の評価/', $title, $matches)) {
                            // タイトルから取得した場合も同様の正規化処理を行う
                            $temp_name = trim($matches[1]);
                            if (class_exists('Normalizer')) {
                                $temp_name = Normalizer::normalize($temp_name, Normalizer::FORM_C);
                            }
                            $character_name = str_replace(['―', '－', '-', '━', '─'], 'ー', $temp_name);
                        } else {
                            throw new Exception("キャラクター名の取得とフォールバックに失敗しました");
                        }
                    }

                    log_message("処理後のキャラクター名: " . $character_name, "success");

                } else {
                    throw new Exception("名前が含まれるh2#hyoka要素が見つかりません。");
                }
            } catch (Exception $e) {
                log_message("名前の取得処理でエラー: {$e->getMessage()}", "error");
                continue;
            }

            // 文字化けチェック用の追加バリデーション
            if (empty($character_name) || strlen($character_name) < 2) {
                log_message("キャラクター名が短すぎます", "error");
                continue;
            }

            if (preg_match('/^[\x20-\x7E]*$/', $character_name)) {
                log_message("キャラクター名がASCII文字のみです", "warning");
                continue;
            }

            if (in_array($character_name, $existing_characters)) {
                log_message("{$character_name} は既に登録済みです。スキップします。", "info");
                continue;
            }

            // 1.5 図鑑IDの取得部分を修正
            $pokedex_id = null;
            try {
                // キャラクター名から図鑑名を生成
                $base_name = preg_replace('/\s*[\(（].*?[\)）]/u', '', $character_name); // 括弧を除去
                $base_name = preg_replace('/^(水着|制服|私服|浴衣|ダンス衣装|クリスマス|バレンタイン|ハロウィン)\s*/u', '', $base_name); // 接頭語を除去
                
                log_message("図鑑検索用名前: {$base_name}", "info");

                // 完全一致を試す
                if (isset($pokedex_map[$base_name])) {
                    $pokedex_id = intval($pokedex_map[$base_name]);
                    log_message("図鑑ID取得（完全一致）: {$base_name} -> {$pokedex_id}", "success");
                } else {
                    // 部分一致で探す
                    foreach ($pokedex_map as $pokedex_name => $id) {
                        // 名前の比較をより緩やかに
                        similar_text($base_name, $pokedex_name, $percent);
                        if ($percent > 80) {
                            $pokedex_id = intval($id);
                            log_message("図鑑ID取得（類似度{$percent}%）: {$base_name} -> {$id} ({$pokedex_name})", "success");
                            break;
                        }
                    }
                }
                
                // 最終的な図鑑IDの状態を出力
                log_message("最終的な図鑑ID: " . ($pokedex_id ?? 'NULL'), "info");
                
            } catch (Exception $e) {
                log_message("図鑑ID取得エラー: " . $e->getMessage(), "warning");
                $pokedex_id = 0;  // エラー時は0を設定
            }

            // 2. ステータスと成長率の取得を修正
            $stats = [];
            $status_found = false;
            $growth_found = false;
            $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];

            // テーブルの処理（importから流用）
            $char_crawler->filter('table')->each(function ($table) use (&$stats, &$status_found, &$growth_found, $status_map) {
                $rows = $table->filter('tr');
                $growth_values_accum = []; // 縦型テーブル用
                $table_is_growth_vertical = false;

                $rows->each(function ($row) use (&$stats, &$status_found, &$growth_found, &$growth_values_accum, &$table_is_growth_vertical, $status_map) {
                    $cells = $row->filter('td, th');
                    if ($cells->count() > 0) {
                        $row_text = '';
                        $cell_values = [];
                        
                        $cells->each(function ($cell, $cell_index) use (&$row_text, &$cell_values) {
                            $text = trim($cell->text());
                            $cell_values[] = $text;
                            if ($cell_index == 0) {
                                $row_text = $text;
                            }
                        });
                        
                        // 初期ステータス行の判定
                        if (!$status_found && count($cell_values) >= 6) {
                            $numeric_count = 0;
                            $numeric_values = [];
                            
                            for ($i = 1; $i < count($cell_values) && $i <= 5; $i++) {
                                if (preg_match('/\d+/', $cell_values[$i], $matches)) {
                                    $numeric_count++;
                                    $numeric_values[] = (int)$matches[0];
                                }
                            }
                            
                            if ($numeric_count >= 5 && 
                                (preg_match('/(基礎|初期|ステータス|能力|星3)/u', $row_text) || 
                                 $numeric_values[0] > 50)) {
                                
                                foreach ($status_map as $index => $stat) {
                                    if (isset($numeric_values[$index])) {
                                        $stats['initial_' . $stat] = $numeric_values[$index];
                                    }
                                }
                                $status_found = true;
                                log_message("初期ステータス取得: " . implode(',', array_slice($numeric_values, 0, 5)), "success");
                            }
                        }
                        
                        // 成長率行の判定
                        if (!$growth_found && count($cell_values) >= 5 && preg_match('/(成長|%)/u', implode(' ', $cell_values))) {
                            $growth_values = [];
                            
                            for ($i = 0; $i < count($cell_values); $i++) {
                                if (preg_match('/(\d+(?:\.\d+)?)/', $cell_values[$i], $matches)) {
                                    $growth_values[] = (float)$matches[1];
                                }
                            }
                            
                            if (count($growth_values) >= 5) {
                                foreach ($status_map as $index => $stat) {
                                    if (isset($growth_values[$index])) {
                                        $stats['growth_rate_' . $stat] = $growth_values[$index];
                                    }
                                }
                                $growth_found = true;
                                log_message("成長率取得: " . implode(',', array_slice($growth_values, 0, 5)), "success");
                            }
                        }
                    }
                });
            });

            // 3. 適性の取得
            $aptitudes = [];
            $aptitudes_found = false;

            try {
                // 適性マッピング
                $aptitudes_map = [
                    'バ場' => ['surface_aptitude_turf', 'surface_aptitude_dirt'],
                    '距離' => ['distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long'],
                    '脚質' => ['strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer']
                ];
                
                // テーブルごとの処理
                $char_crawler->filter('table')->each(function ($table) use (&$aptitudes, $aptitudes_map, &$aptitudes_found) {
                    $rows = $table->filter('tr');
                    $rows->each(function ($row) use (&$aptitudes, $aptitudes_map, &$aptitudes_found) {
                        $th = $row->filter('th');
                        $tds = $row->filter('td');
                        
                        if ($th->count() > 0 && $tds->count() >= 2) {
                            $thText = trim($th->text());
                            
                            // 適性タイプを特定
                            foreach ($aptitudes_map as $key => $fields) {
                                if (strpos($thText, $key) !== false) {
                                    // 各セルからランクを取得
                                    $ranks_found = [];
                                    $tds->each(function ($td) use (&$ranks_found) {
                                        $rank = getRankFromElement($td);
                                        if ($rank) {
                                            $ranks_found[] = $rank;
                                        }
                                    });
                                    
                                    // フィールドに値を設定
                                    foreach ($fields as $field_index => $field_name) {
                                        if (isset($ranks_found[$field_index])) {
                                            $aptitudes[$field_name] = $ranks_found[$field_index];
                                            $aptitudes_found = true;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    });
                });
                
                // デフォルト値設定
                $default_aptitudes = [
                    'surface_aptitude_turf' => 'C', 'surface_aptitude_dirt' => 'C',
                    'distance_aptitude_short' => 'C', 'distance_aptitude_mile' => 'C',
                    'distance_aptitude_medium' => 'C', 'distance_aptitude_long' => 'C',
                    'strategy_aptitude_runner' => 'C', 'strategy_aptitude_leader' => 'C',
                    'strategy_aptitude_chaser' => 'C', 'strategy_aptitude_trailer' => 'C'
                ];
                
                // 見つからなかった適性にデフォルト値を設定
                foreach ($default_aptitudes as $key => $default_value) {
                    if (!isset($aptitudes[$key])) {
                        $aptitudes[$key] = $default_value;
                    }
                }

                log_message("適性データを取得しました", "success");

            } catch (Exception $e) {
                log_message("適性の取得に失敗: " . $e->getMessage(), "warning");
                // デフォルト値を設定
                $aptitudes = [
                    'surface_aptitude_turf' => 'C', 'surface_aptitude_dirt' => 'C',
                    'distance_aptitude_short' => 'C', 'distance_aptitude_mile' => 'C',
                    'distance_aptitude_medium' => 'C', 'distance_aptitude_long' => 'C',
                    'strategy_aptitude_runner' => 'C', 'strategy_aptitude_leader' => 'C',
                    'strategy_aptitude_chaser' => 'C', 'strategy_aptitude_trailer' => 'C'
                ];
            }

            // デフォルト値設定（ステータス・成長率）
            if (!$status_found || !$growth_found) {
                foreach ($status_map as $stat) {
                    if (!isset($stats['initial_' . $stat])) {
                        $stats['initial_' . $stat] = 100;
                    }
                    if (!isset($stats['growth_rate_' . $stat])) {
                        $stats['growth_rate_' . $stat] = 0;
                    }
                }
            }

            // 4. スキル処理（importから移植）
            $character_skills = [];
            $skills_data_list = [];

            try {
                log_message("スキル情報の取得を開始します...", "info");
                
                $skill_nodes = $char_crawler->filter('ol.wd-skill-list li, ul.wd-skill-list li');
                log_message("スキルノード数: " . $skill_nodes->count(), "info");

                // スキル情報を配列に格納
                $skill_nodes->each(function($node) use (&$skills_data_list) {
                    $skill_data = [
                        'name' => '', 'description' => '', 'type' => 'ノーマルスキル',
                        'distance' => '', 'strategy' => '', 'surface' => '',
                        'unlock_condition' => '初期', 'base_skill_name' => null,
                        'required_skill_points' => null
                    ];

                    $head_node = $node->filter('._body ._head');
                    $name_node = $head_node->filter('a');
                    $desc_node = $node->filter('._body ._text');
                    $skill_data['name'] = ($name_node->count() > 0) ? trim($name_node->text()) : '';
                    
                    // 解放条件の取得（必要スキルポイント含む）
                    $unlock_condition_found = false;
                    
                    // 1. class="_point"要素から最初に検索
                    $point_node = $node->filter('._point');
                    if ($point_node->count() > 0) {
                        $point_text = $point_node->text();
                        $cleaned_point_text = preg_replace('/^::before\s*/u', '', $point_text);
                        
                        if (preg_match('/(\d+)(?:pt|ポイント|P|point)?/ui', $cleaned_point_text, $matches)) {
                            $skill_data['required_skill_points'] = (int)$matches[1];
                            $skill_data['unlock_condition'] = $matches[1] . 'pt';
                            $unlock_condition_found = true;
                        }
                    }
                    
                    // 2. ヘッダー部分から検索
                    if (!$unlock_condition_found && $head_node->count() > 0) {
                        $head_text = $head_node->text();
                        if (preg_match('/(\d+)(?:pt|ポイント|P|point)/ui', $head_text, $matches)) {
                            $skill_data['required_skill_points'] = (int)$matches[1];
                            $skill_data['unlock_condition'] = $matches[1] . 'pt';
                            $unlock_condition_found = true;
                        } elseif (preg_match('/(レベル\d+|Lv\.?\d+|\d+レベル)/u', $head_text, $matches)) {
                            $skill_data['unlock_condition'] = $matches[1];
                            $unlock_condition_found = true;
                        }
                    }
                    
                    // 発動条件を説明文末尾の<>内から取得し、適性情報として利用
                    if ($desc_node->count() > 0) {
                        $full_text_for_desc = $desc_node->html();
                        $parts = explode('<br>', $full_text_for_desc);
                        $skill_data['description'] = trim(strip_tags(array_shift($parts))) ?: 'スキル効果の詳細は確認中です。';
                        $full_text_for_conditions = $desc_node->text();
                        
                        $condition_text = '';
                        
                        // 発動条件取得（括弧内から）
                        if (preg_match('/＜([^＞]+)＞/u', $full_text_for_conditions, $matches)) {
                            $condition_text = trim($matches[1]);
                        } elseif (preg_match('/<([^>]+)>/u', $full_text_for_conditions, $matches)) {
                            $condition_text = trim($matches[1]);
                        } elseif (preg_match('/（([^）]+)）/u', $full_text_for_conditions, $matches)) {
                            $condition_candidate = trim($matches[1]);
                            if (!preg_match('/^(\d+|レベル\d+|Lv\d+|\d+レベル|\d+(?:pt|ポイント|P))$/u', $condition_candidate)) {
                                $condition_text = $condition_candidate;
                            }
                        } elseif (preg_match('/\(([^)]+)\)/u', $full_text_for_conditions, $matches)) {
                            $condition_candidate = trim($matches[1]);
                            if (!preg_match('/^(\d+|レベル\d+|Lv\d+|\d+レベル|\d+(?:pt|ポイント|P))$/u', $condition_candidate)) {
                                $condition_text = $condition_candidate;
                            }
                        }
                        
                        // 発動条件から適性を抽出（DB統一形式：カンマ区切り）
                        if (!empty($condition_text)) {
                            // 距離適性
                            if (preg_match('/短距離[\/／]マイル/u', $condition_text)) {
                                $skill_data['distance'] = '短距離,マイル';
                            } elseif (preg_match('/マイル[\/／]中距離/u', $condition_text)) {
                                $skill_data['distance'] = 'マイル,中距離';
                            } elseif (preg_match('/中距離[\/／]長距離/u', $condition_text)) {
                                $skill_data['distance'] = '中距離,長距離';
                            } elseif (preg_match('/(短距離)/u', $condition_text)) {
                                $skill_data['distance'] = '短距離';
                            } elseif (preg_match('/(マイル)/u', $condition_text)) {
                                $skill_data['distance'] = 'マイル';
                            } elseif (preg_match('/(中距離)/u', $condition_text)) {
                                $skill_data['distance'] = '中距離';
                            } elseif (preg_match('/(長距離)/u', $condition_text)) {
                                $skill_data['distance'] = '長距離';
                            }
                            
                            // 脚質適性
                            if (preg_match('/逃げ[\/／]先行/u', $condition_text)) {
                                $skill_data['strategy'] = '逃げ,先行';
                            } elseif (preg_match('/先行[\/／]差し/u', $condition_text)) {
                                $skill_data['strategy'] = '先行,差し';
                            } elseif (preg_match('/差し[\/／]追込/u', $condition_text)) {
                                $skill_data['strategy'] = '差し,追込';
                            } elseif (preg_match('/(逃げ)/u', $condition_text)) {
                                $skill_data['strategy'] = '逃げ';
                            } elseif (preg_match('/(先行)/u', $condition_text)) {
                                $skill_data['strategy'] = '先行';
                            } elseif (preg_match('/(差し)/u', $condition_text)) {
                                $skill_data['strategy'] = '差し';
                            } elseif (preg_match('/(追込)/u', $condition_text)) {
                                $skill_data['strategy'] = '追込';
                            }
                            
                            // 馬場適性
                            if (str_contains($condition_text, '芝') && str_contains($condition_text, 'ダート')) {
                                $skill_data['surface'] = '芝,ダート';
                            } elseif (preg_match('/(芝)/u', $condition_text)) {
                                $skill_data['surface'] = '芝';
                            } elseif (preg_match('/(ダート)/u', $condition_text)) {
                                $skill_data['surface'] = 'ダート';
                            }
                        }
                    } else {
                        $skill_data['description'] = 'スキル効果の詳細は確認中です。';
                        $skill_data['unlock_condition'] = '初期';
                    }

                    // スキルタイプの判定
                    $li_class = $node->attr('class');
                    if (str_contains((string)$li_class, 'unique')) {
                        $skill_data['type'] = '固有スキル';
                    } elseif (str_contains((string)$li_class, 'evo')) {
                        $skill_data['type'] = '進化スキル';
                        
                        // ▼▼▼【修正】進化元スキル名を取得し、解放条件に設定 ▼▼▼
                        $base_skill_found = false;
                        
                        // 1. _beforeクラス要素から取得（::before疑似要素のテキストも含む）
                        $before_node = $node->filter('._before');
                        if ($before_node->count() > 0) {
                            $before_text = $before_node->text();
                            // ::beforeテキストを除去して実際のテキストを取得
                            $cleaned_text = preg_replace('/^::before\s*/', '', $before_text);
                            if (!empty($cleaned_text)) {
                                $skill_data['base_skill_name'] = trim($cleaned_text);
                                // 進化スキルの解放条件は進化元スキル名に設定
                                $skill_data['unlock_condition'] = '「' . $skill_data['base_skill_name'] . '」から進化';
                                $base_skill_found = true;
                            }
                        }
                        
                        // 2. _noteセクションから取得
                        if (!$base_skill_found) {
                            $note_node = $node->filter('._body ._note');
                            if ($note_node->count() > 0) {
                                $note_text = $note_node->text();
                                if (preg_match('/「(.+?)」(?:から|が)進化/u', $note_text, $matches)) {
                                    $skill_data['base_skill_name'] = trim($matches[1]);
                                    $skill_data['unlock_condition'] = '「' . $skill_data['base_skill_name'] . '」から進化';
                                    $base_skill_found = true;
                                }
                            }
                        }
                        
                        // 3. 説明文から取得
                        if (!$base_skill_found && $desc_node->count() > 0) {
                            $desc_text = $desc_node->text();
                            if (preg_match('/「(.+?)」(?:から|が)進化/u', $desc_text, $matches)) {
                                $skill_data['base_skill_name'] = trim($matches[1]);
                                $skill_data['unlock_condition'] = '「' . $skill_data['base_skill_name'] . '」から進化';
                                $base_skill_found = true;
                            }
                        }
                        
                        // 4. HTML構造全体から取得（最後の手段）
                        if (!$base_skill_found) {
                            $full_html = $node->html();
                            if (preg_match('/class="_before"[^>]*>([^<]+)</i', $full_html, $matches)) {
                                $extracted_text = trim(strip_tags($matches[1]));
                                $cleaned_text = preg_replace('/^::before\s*/', '', $extracted_text);
                                if (!empty($cleaned_text)) {
                                    $skill_data['base_skill_name'] = $cleaned_text;
                                    $skill_data['unlock_condition'] = '「' . $skill_data['base_skill_name'] . '」から進化';
                                    $base_skill_found = true;
                                }
                            }
                        }
                        
                        // デバッグ用：進化元スキルが見つからない場合の警告
                        if (!$base_skill_found) {
                            log_message("警告: 進化スキル「{$skill_data['name']}」の進化元スキルが検出できませんでした。", "warning");
                        }
                        
                    } elseif (str_contains((string)$li_class, 'rare')) {
                        $skill_data['type'] = 'レアスキル';
                    }
                    
                    if (!empty($skill_data['name'])) {
                        $skills_data_list[] = $skill_data;
                    }
                });

                // スキルをデータベースに登録・更新
                foreach ($skills_data_list as $skill_data) {
                    $base_skill_id = null;
                    
                    // 進化スキルの場合
                    if ($skill_data['type'] === '進化スキル' && !empty($skill_data['base_skill_name'])) {
                        $base_skill_id = getSkillIdByName($conn, $skill_data['base_skill_name']);
                        
                        if (!$base_skill_id) {
                            $base_skill_id = registerOrUpdateSkill(
                                $conn,
                                $skill_data['base_skill_name'],
                                '進化元スキルです。詳細は確認中です。',
                                'ノーマルスキル',
                                $skill_data['distance'],
                                $skill_data['strategy'],
                                $skill_data['surface'],
                                null,
                                null
                            );
                            log_message("進化元スキル「{$skill_data['base_skill_name']}」を新規作成 (ID:{$base_skill_id})", "info");
                        }
                    }
        
                    // スキル本体を登録
                    $skill_id = registerOrUpdateSkill(
                        $conn,
                        $skill_data['name'],
                        $skill_data['description'],
                        $skill_data['type'],
                        $skill_data['distance'],
                        $skill_data['strategy'],
                        $skill_data['surface'],
                        $base_skill_id,
                        $skill_data['required_skill_points']
                    );
                    
                    if ($skill_id) {
                        $character_skills[] = [
                            'skill_id' => $skill_id,
                            'unlock_condition' => $skill_data['unlock_condition']
                        ];
                        log_message("スキル「{$skill_data['name']}」を処理 (ID:{$skill_id})", "info");
                    }
                }

                log_message(count($character_skills) . "個のスキルを処理しました", "success");

            } catch (Exception $e) {
                log_message("スキル取得エラー: " . $e->getMessage(), "warning");
            }

            // 5. 画像の取得処理を修正（importから流用）
            $image_url = '';
            $image_url_suit = '';
            try {
                $image_node = $char_crawler->filter('img[alt*="のアイキャッチ"], img[src*="/chara"], img[src*="/character"]');
                if ($image_node->count() > 0) {
                    $relative_src = $image_node->first()->attr('data-original') ?: $image_node->first()->attr('src');
                    $image_url = urljoin($link, $relative_src);
                    
                    $upload_dir = __DIR__ . '/../uploads/characters/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = time() . '_suit_' . basename(parse_url($image_url, PHP_URL_PATH));
                    $target_path = $upload_dir . $file_name;
                    
                    $image_content = @file_get_contents($image_url);
                    if ($image_content !== false) {
                        if (file_put_contents($target_path, $image_content)) {
                            $image_url_suit = 'uploads/characters/' . $file_name;
                            log_message("勝負服画像を保存しました: {$file_name}", "success");
                            chmod($target_path, 0644);
                        }
                    }
                }
            } catch (Exception $e) {
                log_message("画像の取得に失敗: {$e->getMessage()}", "warning");
            }

            // 6. データベースに登録
            $columns = [
                'id', 'character_name', 'rarity', 'pokedex_id', 
                'image_url', 'image_url_suit',
                'initial_speed', 'initial_stamina', 'initial_power', 
                'initial_guts', 'initial_wisdom',
                'growth_rate_speed', 'growth_rate_stamina', 'growth_rate_power', 
                'growth_rate_guts', 'growth_rate_wisdom',
                'surface_aptitude_turf', 'surface_aptitude_dirt',
                'distance_aptitude_short', 'distance_aptitude_mile', 
                'distance_aptitude_medium', 'distance_aptitude_long',
                'strategy_aptitude_runner', 'strategy_aptitude_leader', 
                'strategy_aptitude_chaser', 'strategy_aptitude_trailer'
            ];

            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $sql = "INSERT INTO characters (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // バインドパラメータ用の変数を準備
            $rarity = 3; // デフォルト値を3に設定
            $pokedex_id = ($pokedex_id !== null) ? intval($pokedex_id) : 0;

            // ステータス変数の準備
            $initial_speed = intval($stats['initial_speed'] ?? 100);
            $initial_stamina = intval($stats['initial_stamina'] ?? 100);
            $initial_power = intval($stats['initial_power'] ?? 100);
            $initial_guts = intval($stats['initial_guts'] ?? 100);
            $initial_wisdom = intval($stats['initial_wisdom'] ?? 100);
            
            // 成長率変数の準備
            $growth_speed = floatval($stats['growth_rate_speed'] ?? 0);
            $growth_stamina = floatval($stats['growth_rate_stamina'] ?? 0);
            $growth_power = floatval($stats['growth_rate_power'] ?? 0);
            $growth_guts = floatval($stats['growth_rate_guts'] ?? 0);
            $growth_wisdom = floatval($stats['growth_rate_wisdom'] ?? 0);
            
            // 適性変数の準備
            $apt_turf = $aptitudes['surface_aptitude_turf'];
            $apt_dirt = $aptitudes['surface_aptitude_dirt'];
            $apt_short = $aptitudes['distance_aptitude_short'];
            $apt_mile = $aptitudes['distance_aptitude_mile'];
            $apt_medium = $aptitudes['distance_aptitude_medium'];
            $apt_long = $aptitudes['distance_aptitude_long'];
            $apt_runner = $aptitudes['strategy_aptitude_runner'];
            $apt_leader = $aptitudes['strategy_aptitude_leader'];
            $apt_chaser = $aptitudes['strategy_aptitude_chaser'];
            $apt_trailer = $aptitudes['strategy_aptitude_trailer'];

            // バインドパラメータの型を指定
            $stmt->bind_param("isiissiiiiidddddssssssssss", 
                $next_id,          // id (i)
                $character_name,   // character_name (s)
                $rarity,          // rarity (i) - デフォルト値3を使用
                $pokedex_id,      // pokedex_id (i)
                $image_url,       // image_url (s)
                $image_url_suit,  // image_url_suit (s)
                $initial_speed,   // initial_speed (i)
                $initial_stamina, // initial_stamina (i)
                $initial_power,   // initial_power (i)
                $initial_guts,    // initial_guts (i)
                $initial_wisdom,  // initial_wisdom (i)
                $growth_speed,    // growth_rate_speed (d)
                $growth_stamina,  // growth_rate_stamina (d)
                $growth_power,    // growth_rate_power (d)
                $growth_guts,     // growth_rate_guts (d)
                $growth_wisdom,   // growth_rate_wisdom (d)
                $apt_turf,        // surface_aptitude_turf (s)
                $apt_dirt,        // surface_aptitude_dirt (s)
                $apt_short,       // distance_aptitude_short (s)
                $apt_mile,        // distance_aptitude_mile (s)
                $apt_medium,      // distance_aptitude_medium (s)
                $apt_long,        // distance_aptitude_long (s)
                $apt_runner,      // strategy_aptitude_runner (s)
                $apt_leader,      // strategy_aptitude_leader (s)
                $apt_chaser,      // strategy_aptitude_chaser (s)
                $apt_trailer      // strategy_aptitude_trailer (s)
            );

            if ($stmt->execute()) {
                // キャラクタースキル関係を登録
                foreach ($character_skills as $char_skill) {
                    $cs_stmt = $conn->prepare("INSERT INTO character_skills (character_id, skill_id, unlock_condition) VALUES (?, ?, ?)");
                    $cs_stmt->bind_param("iis", $next_id, $char_skill['skill_id'], $char_skill['unlock_condition']);
                    $cs_stmt->execute();
                    $cs_stmt->close();
                }
                
                $conn->commit();  // 成功時にコミット
                log_message("{$character_name} を登録しました (ID: {$next_id}, スキル数: " . count($character_skills) . ")", "success");
                $existing_characters[] = $character_name;
                $next_id++;
                $import_count++;
            } else {
                $conn->rollback();  // 失敗時にロールバック
                throw new Exception("Insert failed: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();  // エラー時にロールバック
            log_message("エラー発生: {$e->getMessage()}", "error");
            continue;
        }
    }

    log_message("--------------------");
    log_message("処理が完了しました。{$import_count}件のウマ娘を登録しました。", "success");

} catch (Exception $e) {
    log_message("致命的なエラーが発生しました: " . $e->getMessage(), "error");
} finally {
    $conn->close();
}
?>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="button">&laquo; ウマ娘一覧に戻る</a>
        </div>
    </div>
</body>
</html>
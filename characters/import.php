<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘データをインポート';
$current_page = 'characters';
$base_path = '../';

// 必要なライブラリを読み込む
require_once $base_path . 'vendor/autoload.php';
use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;

// URL結合用の関数
function urljoin($base, $relative) {
    if (parse_url($relative, PHP_URL_SCHEME) !== null) return $relative;
    $base_parts = parse_url($base);
    if ($base_parts === false) return $relative;
    $scheme = $base_parts['scheme'] ?? 'https';
    $host = $base_parts['host'] ?? '';
    $path = preg_replace('#/[^/]*$#', '/', $base_parts['path'] ?? '/');
    if (str_starts_with($relative, '/')) return "$scheme://$host$relative";
    $absolute_path = $path . $relative;
    $absolute_path = preg_replace('#/(\./)+#', '/', $absolute_path);
    while (preg_match('#/[^/]+/\.\./#', $absolute_path)) {
        $absolute_path = preg_replace('#/[^/]+/\.\./#', '/', $absolute_path);
    }
    return "$scheme://$host$absolute_path";
}

// ▼▼▼【重要修正】スキル名からIDを検索するヘルパー関数 ▼▼▼
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

// ▼▼▼【重要修正】スキルを「登録または更新」する関数のバインドパラメータ修正 ▼▼▼
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
        // ▼▼▼【修正】バインドパラメータの型を修正（iを追加）▼▼▼
        $stmt_update->bind_param("sssssiii", $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type, $base_skill_id, $required_skill_points, $existing_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        return $existing_id;
    } else {
        // 3. 存在しない場合：INSERTで新規登録
        $stmt_insert = $conn->prepare(
            "INSERT INTO skills (skill_name, skill_description, skill_type, distance_type, strategy_type, surface_type, base_skill_id, required_skill_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // ▼▼▼【修正】バインドパラメータの型を修正（iを追加）▼▼▼
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


$scraped_data = null;
$error_message = '';
$url = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    $debug_mode = isset($_POST['debug']) && $_POST['debug'] == '1';

    $conn = new mysqli('localhost', 'root', '', 'umamusume_db');
    if ($conn->connect_error) {
        $error_message = "データベース接続に失敗しました: " . $conn->connect_error;
    } else {
        $conn->set_charset("utf8mb4");
        $pokedex_map = [];
        $result = $conn->query("SELECT id, pokedex_name FROM pokedex");
        while ($row = $result->fetch_assoc()) {
            $pokedex_map[trim($row['pokedex_name'])] = $row['id'];
        }
    }

    $client = new Client(HttpClient::create(['timeout' => 60, 'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']]));

    try {
        if (!empty($error_message)) {
            throw new Exception($error_message);
        }

        $crawler = $client->request('GET', $url);
        
        if ($debug_mode) {
            $debug_info .= "URL: " . $url . "<br>";
            $debug_info .= "ページタイトル: " . $crawler->filter('title')->text() . "<br>";
        }
        
        $scraped_data = [];

        // --- 1. 名前の取得 ---
        $character_name = '';
        $name_node = $crawler->filter('h2#hyoka');
        if ($name_node->count() > 0) {
            $raw_name = trim($name_node->text());
            if (class_exists('Normalizer')) $raw_name = Normalizer::normalize($raw_name, Normalizer::FORM_C);
            $name_with_normalized_bar = str_replace(['–', '－', '-', '―', '─'], 'ー', $raw_name);
            $character_name = str_replace('の評価', '', $name_with_normalized_bar);
        }
        if (empty($character_name)) {
            $title = $crawler->filter('title')->text();
            if (preg_match('/^(.+?)の評価/', $title, $matches)) {
                $temp_name = trim($matches[1]);
                if (class_exists('Normalizer')) $temp_name = Normalizer::normalize($temp_name, Normalizer::FORM_C);
                $character_name = str_replace(['–', '－', '-', '―', '─'], 'ー', $temp_name);
            } else {
                throw new Exception("キャラクター名の取得に失敗しました。");
            }
        }
        $scraped_data['character_name'] = $character_name;
        
        // --- 図鑑IDの自動照合処理 ---
        $base_name = preg_replace('/\s*[\(（].*?[\)）]/u', '', $character_name);
        $base_name = preg_replace('/^(水着|制服|私服|浴衣|ダンス衣装|クリスマス|バレンタイン|ハロウィン|新衣装)\s*/u', '', $base_name);
        if (isset($pokedex_map[$base_name])) {
            $scraped_data['pokedex_id'] = $pokedex_map[$base_name];
        } else {
            foreach ($pokedex_map as $pokedex_name => $id) {
                similar_text($base_name, $pokedex_name, $percent);
                if ($percent > 85) {
                    $scraped_data['pokedex_id'] = $id;
                    break;
                }
            }
        }

        // --- 2. 基礎能力と成長率の取得 ---
        try {
            if ($debug_mode) $debug_info .= "<strong>--- 基礎能力の取得開始 ---</strong><br>";
            
            $all_tables = $crawler->filter('table');
            $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
            $status_found = false;
            $growth_found = false;

            if ($debug_mode) $debug_info .= "ページ内のテーブル数: " . $all_tables->count() . "<br>";

            $all_tables->each(function ($table, $table_index) use (&$scraped_data, $status_map, &$status_found, &$growth_found, $debug_mode, &$debug_info) {
                if ($debug_mode) $debug_info .= "<br><strong>テーブル {$table_index}:</strong><br>";
                
                $rows = $table->filter('tr');
                $growth_values_accum = [];
                $table_is_growth_vertical = false;

                $rows->each(function ($row, $row_index) use (&$scraped_data, $status_map, &$status_found, &$growth_found, $debug_mode, &$debug_info, &$growth_values_accum, &$table_is_growth_vertical) {
                    $cells = $row->filter('td, th');
                    if ($cells->count() > 0) {
                        $cell_values = $cells->each(function ($cell) { return trim($cell->text()); });
                        if ($debug_mode && count($cell_values) > 1) $debug_info .= "  行{$row_index}: " . implode(' | ', $cell_values) . "<br>";

                        // 初期ステータス行の判定
                        if (!$status_found && count($cell_values) >= 6) {
                            $numeric_values = [];
                            for ($i = 1; $i <= 5; $i++) {
                                if (isset($cell_values[$i]) && preg_match('/\d+/', $cell_values[$i], $matches)) {
                                    $numeric_values[] = (int)$matches[0];
                                }
                            }
                            if (count($numeric_values) >= 5 && (preg_match('/(基礎|初期|ステータス|能力|星3)/u', $cell_values[0]) || (isset($numeric_values[0]) && $numeric_values[0] > 50) )) {
                                foreach ($status_map as $index => $stat) {
                                    if (isset($numeric_values[$index])) {
                                        $scraped_data['initial_' . $stat] = $numeric_values[$index];
                                    }
                                }
                                $status_found = true;
                                if ($debug_mode) $debug_info .= "  ★初期ステータス発見！<br>";
                            }
                        }
                        
                        // 成長率行の判定（横型）
                        if (!$growth_found && count($cell_values) >= 5 && preg_match('/(成長|%)/u', implode(' ', $cell_values))) {
                            $growth_values = [];
                            foreach ($cell_values as $cell_value) {
                                if (preg_match('/(\d+(?:\.\d+)?)/', $cell_value, $matches)) {
                                    $growth_values[] = (float)$matches[1];
                                }
                            }
                            if (count($growth_values) >= 5) {
                                foreach ($status_map as $index => $stat) {
                                    if (isset($growth_values[$index])) {
                                        $scraped_data['growth_rate_' . $stat] = $growth_values[$index];
                                    }
                                }
                                $growth_found = true;
                                if ($debug_mode) $debug_info .= "  ★成長率発見（横型）！<br>";
                            }
                        }
                        
                        // 縦型成長率の蓄積
                        if (count($cell_values) == 2 && preg_match('/%/', $cell_values[1])) {
                            $table_is_growth_vertical = true;
                            if (preg_match('/(\d+(?:\.\d+)?)/', $cell_values[1], $matches)) {
                                $growth_values_accum[] = (float)$matches[1];
                            }
                        }
                    }
                });

                if (!$growth_found && $table_is_growth_vertical && count($growth_values_accum) == 5) {
                    foreach ($status_map as $index => $stat) {
                        $scraped_data['growth_rate_' . $stat] = $growth_values_accum[$index];
                    }
                    $growth_found = true;
                    if ($debug_mode) $debug_info .= "  ★成長率発見（縦型）！<br>";
                }

                if ($status_found && $growth_found) return false;
            });
            
            if (!$status_found) {
                foreach ($status_map as $stat) $scraped_data['initial_' . $stat] = 100;
                if ($debug_mode) $debug_info .= "初期ステータスが見つからなかったため、デフォルト値(100)を設定<br>";
            }
            if (!$growth_found) {
                foreach ($status_map as $stat) $scraped_data['growth_rate_' . $stat] = 0;
                if ($debug_mode) $debug_info .= "成長率が見つからなかったため、デフォルト値(0)を設定<br>";
            }

        } catch (Exception $e) {
            $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
            foreach ($status_map as $stat) {
                $scraped_data['initial_' . $stat] = 100;
                $scraped_data['growth_rate_' . $stat] = 0;
            }
            if ($debug_mode) $debug_info .= "ステータス取得エラー: " . $e->getMessage() . "<br>";
        }

        // --- 3. 適性の取得 ---
        try {
            if ($debug_mode) $debug_info .= "<br><strong>--- 適性の取得開始 ---</strong><br>";
            $aptitudes_map = [
                'バ場' => ['surface_aptitude_turf', 'surface_aptitude_dirt'],
                '距離' => ['distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long'],
                '脚質' => ['strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer']
            ];
            $aptitudes_found = false;

            function getRankFromElement($element) {
                $img = $element->filter('img');
                if ($img->count() > 0) {
                    $src = $img->attr('data-original') ?: $img->attr('src');
                    if ($src && preg_match('/[_-]([A-G])(p?)\.png/i', $src, $matches)) {
                        return strtoupper($matches[1]) . ($matches[2] === 'p' ? '+' : '');
                    }
                }
                $text = trim($element->text());
                if (preg_match('/^([A-G]\+?)$/', $text, $matches)) return $matches[1];
                $class = $element->attr('class');
                if ($class && preg_match('/rank[_-]([A-G])/i', $class, $matches)) return strtoupper($matches[1]);
                return null;
            }

            $crawler->filter('table')->each(function ($table) use (&$scraped_data, $aptitudes_map, &$aptitudes_found, $debug_mode, &$debug_info) {
                $rows = $table->filter('tr');
                $rows->each(function ($row) use (&$scraped_data, $aptitudes_map, &$aptitudes_found, $debug_mode, &$debug_info) {
                    $th = $row->filter('th');
                    $tds = $row->filter('td');
                    if ($th->count() > 0 && $tds->count() > 0) {
                        $thText = trim($th->text());
                        foreach ($aptitudes_map as $key => $fields) {
                            if (strpos($thText, $key) !== false) {
                                $ranks_found = [];
                                $tds->each(function ($td) use (&$ranks_found) {
                                    $rank = getRankFromElement($td);
                                    if ($rank) $ranks_found[] = $rank;
                                });
                                foreach ($fields as $field_index => $field_name) {
                                    if (isset($ranks_found[$field_index])) {
                                        $scraped_data[$field_name] = $ranks_found[$field_index];
                                        $aptitudes_found = true;
                                        if ($debug_mode) $debug_info .= "  設定: {$field_name} = {$ranks_found[$field_index]}<br>";
                                    }
                                }
                            }
                        }
                    }
                });
            });

            $default_aptitudes = ['surface_aptitude_turf' => 'C', 'surface_aptitude_dirt' => 'C', 'distance_aptitude_short' => 'C', 'distance_aptitude_mile' => 'C', 'distance_aptitude_medium' => 'C', 'distance_aptitude_long' => 'C', 'strategy_aptitude_runner' => 'C', 'strategy_aptitude_leader' => 'C', 'strategy_aptitude_chaser' => 'C', 'strategy_aptitude_trailer' => 'C'];
            foreach ($default_aptitudes as $key => $default_value) {
                if (!isset($scraped_data[$key])) {
                    $scraped_data[$key] = $default_value;
                }
            }
        } catch (Exception $e) {
            if ($debug_mode) $debug_info .= "適性取得エラー: " . $e->getMessage() . "<br>";
        }

        // --- 4. 画像の自動ダウンロード ---
        try {
            $image_node = $crawler->filter('img[alt*="のアイキャッチ"], img[src*="/chara"], img[src*="/character"]')->first();
            if ($image_node->count() > 0) {
                $relative_src = $image_node->attr('data-original') ?: $image_node->attr('src');
                $image_url = urljoin($url, $relative_src);
                if ($debug_mode) $debug_info .= "画像URL発見: " . $image_url . "<br>";
                
                $upload_dir = '../uploads/characters/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_name = time() . '_suit_' . basename(parse_url($image_url, PHP_URL_PATH));
                $target_file = $upload_dir . $file_name;
                
                $image_content = @file_get_contents($image_url);
                if ($image_content) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->buffer($image_content);
                    if (strpos($mime_type, 'image/') === 0) {
                        if (file_put_contents($target_file, $image_content)) {
                            $scraped_data['image_suit_path'] = 'uploads/characters/' . $file_name;
                            if ($debug_mode) $debug_info .= "画像ダウンロード成功: " . $target_file . "<br>";
                        } else {
                             if ($debug_mode) $debug_info .= "画像の保存に失敗しました。<br>";
                        }
                    } else {
                        if ($debug_mode) $debug_info .= "無効な画像形式です: $mime_type<br>";
                    }
                } else {
                    if ($debug_mode) $debug_info .= "画像ダウンロード失敗。<br>";
                }
            } else {
                if ($debug_mode) $debug_info .= "画像が見つかりませんでした。<br>";
            }
        } catch (Exception $e) {
            if ($debug_mode) $debug_info .= "画像取得エラー: " . $e->getMessage() . "<br>";
        }

        // --- 5. スキル情報の取得とデータベース登録 ---
        try {
            if ($debug_mode) $debug_info .= "<br><strong>--- スキル情報の取得開始 ---</strong><br>";
            
            $scraped_data['character_skills'] = [];
            $skills_data_list = []; // スキル情報を一時的に保持する配列

            $skill_nodes = $crawler->filter('ol.wd-skill-list li, ul.wd-skill-list li');
            
            if ($debug_mode && $skill_nodes->count() === 0) {
                $debug_info .= "<strong style='color:red;'>警告: スキルリストが見つかりませんでした。</strong><br>";
            }

            // ▼▼▼【重要修正】1回目のループで、全スキルの情報を配列に格納（解放条件・必要スキルポイント追加） ▼▼▼
            $skill_nodes->each(function($node) use (&$skills_data_list, $debug_mode, &$debug_info) {
                $skill_data = [
                    'name' => '', 'description' => '', 'type' => 'ノーマルスキル',
                    'distance' => '', 'strategy' => '', 'surface' => '',
                    'unlock_condition' => '初期', 'base_skill_name' => null,
                    'required_skill_points' => null // 必要スキルポイント追加
                ];

                $head_node = $node->filter('._body ._head');
                $name_node = $head_node->filter('a');
                $desc_node = $node->filter('._body ._text');
                $skill_data['name'] = ($name_node->count() > 0) ? trim($name_node->text()) : '';
                
                // ▼▼▼【修正】解放条件の取得（必要スキルポイント含む）- 検索範囲を拡大 ▼▼▼
                $unlock_condition_found = false;
                
                // 0. class="_point"要素から最初に検索（最も確実）
                $point_node = $node->filter('._point');
                if ($point_node->count() > 0) {
                    $point_text = $point_node->text();
                    
                    // ::beforeテキストを除去してから数値を抽出
                    $cleaned_point_text = preg_replace('/^::before\s*/u', '', $point_text);
                    
                    if (preg_match('/(\d+)(?:pt|ポイント|P|point)?/ui', $cleaned_point_text, $matches)) {
                        $skill_data['required_skill_points'] = (int)$matches[1];
                        $skill_data['unlock_condition'] = $matches[1] . 'pt';
                        $unlock_condition_found = true;
                        if ($debug_mode) {
                            $debug_info .= "✅ 解放条件（必要スキルポイント）を._pointから取得: {$skill_data['required_skill_points']}pt (元テキスト: 「{$point_text}」)<br>";
                        }
                    }
                }
                
                // 1. ヘッダー部分から解放条件を検索
                if ($head_node->count() > 0) {
                    $head_text = $head_node->text();
                    
                    // 必要スキルポイントのパターンを検索（より広範囲のパターン）
                    if (preg_match('/(\d+)(?:pt|ポイント|P|point)/ui', $head_text, $matches)) {
                        $skill_data['required_skill_points'] = (int)$matches[1];
                        $skill_data['unlock_condition'] = $matches[1] . 'pt';
                        $unlock_condition_found = true;
                        if ($debug_mode) {
                            $debug_info .= "✅ 解放条件（必要スキルポイント）をヘッダーから取得: {$skill_data['required_skill_points']}pt<br>";
                        }
                    }
                    // レベル条件の検索
                    elseif (preg_match('/(レベル\d+|Lv\.?\d+|\d+レベル)/u', $head_text, $matches)) {
                        $skill_data['unlock_condition'] = $matches[1];
                        $unlock_condition_found = true;
                        if ($debug_mode) {
                            $debug_info .= "✅ 解放条件（レベル）をヘッダーから取得: {$skill_data['unlock_condition']}<br>";
                        }
                    }
                }
                
                // 2. View要素やボタン周辺から解放条件を検索
                if (!$unlock_condition_found) {
                    $view_nodes = $node->filter('.view, ._view, [class*="view"], .button, .btn, ._button');
                    $view_nodes->each(function($view_node) use (&$skill_data, &$unlock_condition_found, $debug_mode, &$debug_info) {
                        if ($unlock_condition_found) return;
                        
                        $view_text = $view_node->text();
                        
                        // 必要スキルポイントのパターンを検索
                        if (preg_match('/(\d+)(?:pt|ポイント|P|point)/ui', $view_text, $matches)) {
                            $skill_data['required_skill_points'] = (int)$matches[1];
                            $skill_data['unlock_condition'] = $matches[1] . 'pt';
                            $unlock_condition_found = true;
                            if ($debug_mode) {
                                $debug_info .= "✅ 解放条件（必要スキルポイント）をView要素から取得: {$skill_data['required_skill_points']}pt<br>";
                            }
                        }
                        // レベル条件の検索
                        elseif (preg_match('/(レベル\d+|Lv\.?\d+|\d+レベル)/u', $view_text, $matches)) {
                            $skill_data['unlock_condition'] = $matches[1];
                            $unlock_condition_found = true;
                            if ($debug_mode) {
                                $debug_info .= "✅ 解放条件（レベル）をView要素から取得: {$skill_data['unlock_condition']}<br>";
                            }
                        }
                    });
                }
                
                // 3. ノード全体のHTMLから解放条件を検索（最後の手段）
                if (!$unlock_condition_found) {
                    $full_html = $node->html();
                    
                    // 必要スキルポイントのパターンを検索（より広範囲のパターン）
                    if (preg_match('/(\d+)(?:pt|ポイント|P|point)/ui', $full_html, $matches)) {
                        $skill_data['required_skill_points'] = (int)$matches[1];
                        $skill_data['unlock_condition'] = $matches[1] . 'pt';
                        $unlock_condition_found = true;
                        if ($debug_mode) {
                            $debug_info .= "✅ 解放条件（必要スキルポイント）をHTML全体から取得: {$skill_data['required_skill_points']}pt<br>";
                        }
                    }
                    // レベル条件の検索
                    elseif (preg_match('/(レベル\d+|Lv\.?\d+|\d+レベル)/u', $full_html, $matches)) {
                        $skill_data['unlock_condition'] = $matches[1];
                        $unlock_condition_found = true;
                        if ($debug_mode) {
                            $debug_info .= "✅ 解放条件（レベル）をHTML全体から取得: {$skill_data['unlock_condition']}<br>";
                        }
                    }
                }
                
                // ▼▼▼【修正】より広範囲の検索（データ属性・classなど）▼▼▼
                if (!$unlock_condition_found) {
                    // ._pointクラス要素から再検索（念のため）
                    $all_point_nodes = $node->filter('*[class*="point"]');
                    $all_point_nodes->each(function($point_element) use (&$skill_data, &$unlock_condition_found, $debug_mode, &$debug_info) {
                        if ($unlock_condition_found) return;
                        
                        $element_text = $point_element->text();
                        // ::beforeを除去
                        $cleaned_text = preg_replace('/^::before\s*/u', '', $element_text);
                        
                        if (preg_match('/(\d+)(?:pt|ポイント|P|point)?/ui', $cleaned_text, $matches)) {
                            $skill_data['required_skill_points'] = (int)$matches[1];
                            $skill_data['unlock_condition'] = $matches[1] . 'pt';
                            $unlock_condition_found = true;
                            if ($debug_mode) {
                                $class_name = $point_element->attr('class');
                                $debug_info .= "✅ 解放条件（必要スキルポイント）をclass=\"{$class_name}\"要素から取得: {$skill_data['required_skill_points']}pt (元テキスト: 「{$element_text}」)<br>";
                            }
                        }
                    });
                    
                    // data属性から検索
                    if (!$unlock_condition_found) {
                        $data_skill_points = $node->attr('data-skill-points') ?: $node->attr('data-points');
                        if ($data_skill_points && is_numeric($data_skill_points)) {
                            $skill_data['required_skill_points'] = (int)$data_skill_points;
                            $skill_data['unlock_condition'] = $data_skill_points . 'pt';
                            $unlock_condition_found = true;
                            if ($debug_mode) {
                                $debug_info .= "✅ 解放条件（必要スキルポイント）をdata属性から取得: {$skill_data['required_skill_points']}pt<br>";
                            }
                        }
                    }
                    
                    // class名からパターン検索
                    if (!$unlock_condition_found) {
                        $class_attr = $node->attr('class');
                        if ($class_attr && preg_match('/(?:skill|point)[-_](\d+)/i', $class_attr, $matches)) {
                            $skill_data['required_skill_points'] = (int)$matches[1];
                            $skill_data['unlock_condition'] = $matches[1] . 'pt';
                            $unlock_condition_found = true;
                            if ($debug_mode) {
                                $debug_info .= "✅ 解放条件（必要スキルポイント）をclass属性から取得: {$skill_data['required_skill_points']}pt<br>";
                            }
                        }
                    }
                }
                
                if ($debug_mode && !$unlock_condition_found) {
                    $debug_info .= "⚠️ 解放条件（必要スキルポイント）が見つかりませんでした<br>";
                    // デバッグ用：._pointクラス要素の詳細を表示
                    $point_debug = $node->filter('._point');
                    if ($point_debug->count() > 0) {
                        $debug_info .= "🔍 ._point要素のテキスト: 「" . htmlspecialchars($point_debug->text()) . "」<br>";
                        $debug_info .= "🔍 ._point要素のHTML: " . htmlspecialchars($point_debug->html()) . "<br>";
                    }
                    // デバッグ用：ノードのHTMLを一部表示
                    $debug_html = substr($node->html(), 0, 500);
                    $debug_info .= "🔍 ノードHTML（先頭500文字）: " . htmlspecialchars($debug_html) . "...<br>";
                }
                
                // ▼▼▼【修正】発動条件を説明文末尾の<>内から取得し、適性情報として利用 ▼▼▼
                if ($desc_node->count() > 0) {
                    $full_text_for_desc = $desc_node->html();
                    $parts = explode('<br>', $full_text_for_desc);
                    $skill_data['description'] = trim(strip_tags(array_shift($parts))) ?: 'スキル効果の詳細は確認中です。';
                    $full_text_for_conditions = $desc_node->text();
                    
                    // 発動条件を説明文の末尾から取得（適性情報として使用）
                    if ($debug_mode) {
                        $debug_info .= "==== 発動条件取得デバッグ ====<br>";
                        $debug_info .= "スキル名: 「{$skill_data['name']}」<br>";
                        $debug_info .= "説明文全文: 「{$full_text_for_conditions}」<br>";
                    }
                    
                    $condition_text = ''; // 実際の発動条件テキストを格納
                    
                    // 1. 全角の＜＞で囲まれた部分を抽出（説明文から）
                    if (preg_match('/＜([^＞]+)＞/u', $full_text_for_conditions, $matches)) {
                        $condition_text = trim($matches[1]);
                        if ($debug_mode) {
                            $debug_info .= "✅ 発動条件を説明文内の全角＜＞から取得: 「{$condition_text}」<br>";
                        }
                    }
                    // 2. 半角の<>で囲まれた部分を抽出（説明文から）
                    elseif (preg_match('/<([^>]+)>/u', $full_text_for_conditions, $matches)) {
                        $condition_text = trim($matches[1]);
                        if ($debug_mode) {
                            $debug_info .= "✅ 発動条件を説明文内の半角<>から取得: 「{$condition_text}」<br>";
                        }
                    }
                    // 3. 全角の（）で囲まれた部分を抽出（説明文から）
                    elseif (preg_match('/（([^）]+)）/u', $full_text_for_conditions, $matches)) {
                        $condition_candidate = trim($matches[1]);
                        // レベル表記やスキルポイントは除外（これらは解放条件）
                        if (!preg_match('/^(\d+|レベル\d+|Lv\d+|\d+レベル|\d+(?:pt|ポイント|P))$/u', $condition_candidate)) {
                            $condition_text = $condition_candidate;
                            if ($debug_mode) {
                                $debug_info .= "✅ 発動条件を説明文内の全角（）から取得: 「{$condition_text}」<br>";
                            }
                        } else {
                            if ($debug_mode) {
                                $debug_info .= "❌ 全角（）内が除外対象「{$condition_candidate}」のため無視<br>";
                            }
                        }
                    }
                    // 4. 半角の()で囲まれた部分を抽出（説明文から）
                    elseif (preg_match('/\(([^)]+)\)/u', $full_text_for_conditions, $matches)) {
                        $condition_candidate = trim($matches[1]);
                        // レベル表記やスキルポイントは除外（これらは解放条件）
                        if (!preg_match('/^(\d+|レベル\d+|Lv\d+|\d+レベル|\d+(?:pt|ポイント|P))$/u', $condition_candidate)) {
                            $condition_text = $condition_candidate;
                            if ($debug_mode) {
                                $debug_info .= "✅ 発動条件を説明文内の半角()から取得: 「{$condition_text}」<br>";
                            }
                        } else {
                            if ($debug_mode) {
                                $debug_info .= "❌ 半角()内が除外対象「{$condition_candidate}」のため無視<br>";
                            }
                        }
                    }
                    // 5. 見つからない場合
                    else {
                        if ($debug_mode) {
                            $debug_info .= "❌ 説明文内で発動条件が検出できませんでした<br>";
                        }
                    }
                    
                    if ($debug_mode) {
                        $debug_info .= "🔍 発動条件テキスト: 「{$condition_text}」<br>";
                    }
                    
                    // ▼▼▼【重要修正】発動条件から適性情報のみを抽出（解放条件には保存しない） ▼▼▼
                    if (!empty($condition_text)) {
                        // 距離適性の取得（発動条件テキストから）- DB統一形式でカンマ区切り
                        if (preg_match('/短距離[\/／]マイル/u', $condition_text)) {
                            $skill_data['distance'] = '短距離,マイル';
                        } elseif (preg_match('/マイル[\/／]中距離/u', $condition_text)) {
                            $skill_data['distance'] = 'マイル,中距離';
                        } elseif (preg_match('/中距離[\/／]長距離/u', $condition_text)) {
                            $skill_data['distance'] = '中距離,長距離';
                        } elseif (preg_match('/短距離[\/／]中距離/u', $condition_text)) {
                            $skill_data['distance'] = '短距離,中距離';
                        } elseif (preg_match('/マイル[\/／]長距離/u', $condition_text)) {
                            $skill_data['distance'] = 'マイル,長距離';
                        } elseif (preg_match('/(短距離)/u', $condition_text)) {
                            $skill_data['distance'] = '短距離';
                        } elseif (preg_match('/(マイル)/u', $condition_text)) {
                            $skill_data['distance'] = 'マイル';
                        } elseif (preg_match('/(中距離)/u', $condition_text)) {
                            $skill_data['distance'] = '中距離';
                        } elseif (preg_match('/(長距離)/u', $condition_text)) {
                            $skill_data['distance'] = '長距離';
                        }
                        
                        // 脚質適性の取得（発動条件テキストから）- DB統一形式でカンマ区切り
                        if (preg_match('/逃げ[\/／]先行/u', $condition_text)) {
                            $skill_data['strategy'] = '逃げ,先行';
                        } elseif (preg_match('/先行[\/／]差し/u', $condition_text)) {
                            $skill_data['strategy'] = '先行,差し';
                        } elseif (preg_match('/差し[\/／]追込/u', $condition_text)) {
                            $skill_data['strategy'] = '差し,追込';
                        } elseif (preg_match('/逃げ[\/／]差し/u', $condition_text)) {
                            $skill_data['strategy'] = '逃げ,差し';
                        } elseif (preg_match('/先行[\/／]追込/u', $condition_text)) {
                            $skill_data['strategy'] = '先行,追込';
                        } elseif (preg_match('/逃げ[\/／]追込/u', $condition_text)) {
                            $skill_data['strategy'] = '逃げ,追込';
                        } elseif (preg_match('/(逃げ)/u', $condition_text)) {
                            $skill_data['strategy'] = '逃げ';
                        } elseif (preg_match('/(先行)/u', $condition_text)) {
                            $skill_data['strategy'] = '先行';
                        } elseif (preg_match('/(差し)/u', $condition_text)) {
                            $skill_data['strategy'] = '差し';
                        } elseif (preg_match('/(追込)/u', $condition_text)) {
                            $skill_data['strategy'] = '追込';
                        }
                        
                        // 馬場適性の取得（発動条件テキストから）- DB統一形式でカンマ区切り
                        if (preg_match('/芝[\/／]ダート/u', $condition_text) || 
                            (str_contains($condition_text, '芝') && str_contains($condition_text, 'ダート'))) {
                            $skill_data['surface'] = '芝,ダート';
                        } elseif (preg_match('/(芝)/u', $condition_text)) {
                            $skill_data['surface'] = '芝';
                        } elseif (preg_match('/(ダート)/u', $condition_text)) {
                            $skill_data['surface'] = 'ダート';
                        }
                        
                        if ($debug_mode) {
                            $debug_info .= "📍 適性情報（発動条件から抽出・DB統一形式） - 距離: 「{$skill_data['distance']}」 脚質: 「{$skill_data['strategy']}」 馬場: 「{$skill_data['surface']}」<br>";
                        }
                    } else {
                        if ($debug_mode) {
                            $debug_info .= "⚠️ 発動条件テキストが空のため、適性情報は設定しません<br>";
                        }
                    }
                    
                } else {
                    // 説明文が見つからない場合のデフォルト値
                    $skill_data['description'] = 'スキル効果の詳細は確認中です。';
                    if ($debug_mode) {
                        $debug_info .= "❌ 説明文ノードが見つからないため初期値を設定<br>";
                    }
                }

                // スキルタイプの判定と進化スキルの解放条件設定
                $li_class = $node->attr('class');
                if (str_contains((string)$li_class, 'unique')) {
                    $skill_data['type'] = '固有スキル';
                } elseif (str_contains((string)$li_class, 'evo')) {
                    $skill_data['type'] = '進化スキル';
                    
                    // 進化元スキル名を取得する複数のパターンを試行
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
                            if ($debug_mode) {
                                $debug_info .= "._beforeから進化元スキル名を取得: 「{$skill_data['base_skill_name']}」<br>";
                                $debug_info .= "進化スキルの解放条件を設定: 「{$skill_data['unlock_condition']}」<br>";
                            }
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
                                if ($debug_mode) {
                                    $debug_info .= "._noteから進化元スキル名を取得: 「{$skill_data['base_skill_name']}」<br>";
                                    $debug_info .= "進化スキルの解放条件を設定: 「{$skill_data['unlock_condition']}」<br>";
                                }
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
                            if ($debug_mode) {
                                $debug_info .= "説明文から進化元スキル名を取得: 「{$skill_data['base_skill_name']}」<br>";
                                $debug_info .= "進化スキルの解放条件を設定: 「{$skill_data['unlock_condition']}」<br>";
                            }
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
                                if ($debug_mode) {
                                    $debug_info .= "HTMLから進化元スキル名を取得: 「{$skill_data['base_skill_name']}」<br>";
                                    $debug_info .= "進化スキルの解放条件を設定: 「{$skill_data['unlock_condition']}」<br>";
                                }
                            }
                        }
                    }
                    
                    // デバッグ情報
                    if (!$base_skill_found && $debug_mode) {
                        $debug_info .= "<strong style='color:orange;'>警告: 進化スキル「{$skill_data['name']}」の進化元スキルが検出できませんでした。</strong><br>";
                    }
                } elseif (str_contains((string)$li_class, 'rare')) {
                    $skill_data['type'] = 'レアスキル';
                }
                
                if ($debug_mode) {
                    $debug_info .= "🎯 最終的な解放条件: 「{$skill_data['unlock_condition']}」<br>";
                    $debug_info .= "💰 必要スキルポイント: " . ($skill_data['required_skill_points'] ?? 'なし') . "<br>";
                    if ($skill_data['required_skill_points']) {
                        $debug_info .= "💾 DBに登録される必要SP: " . $skill_data['required_skill_points'] . "<br>";
                    }
                    $debug_info .= "==============================<br><br>";
                }
                
                if (!empty($skill_data['name'])) {
                    $skills_data_list[] = $skill_data;
                }
            });

            // ▼▼▼【重要修正】2回目のループで、配列の情報を元にDBへ登録・更新を行う（必要スキルポイント対応） ▼▼▼
            foreach ($skills_data_list as $skill_data) {
                $base_skill_id = null;
                
                // 進化スキルの場合の処理
                if ($skill_data['type'] === '進化スキル' && !empty($skill_data['base_skill_name'])) {
                    // 1. 進化元スキルのIDを取得
                    $base_skill_id = getSkillIdByName($conn, $skill_data['base_skill_name']);
                    
                    // 2. 進化元スキルが存在しない場合は作成
                    if (!$base_skill_id) {
                        $base_skill_id = registerOrUpdateSkill(
                            $conn,
                            $skill_data['base_skill_name'],
                            '進化元スキルです。詳細は確認中です。',
                            'ノーマルスキル',
                            $skill_data['distance'],
                            $skill_data['strategy'],
                            $skill_data['surface'],
                            null, // 進化元なのでbase_skill_idはnull
                            null  // 進化元スキルの必要ポイントは不明
                        );
                        
                        if ($debug_mode) {
                            $debug_info .= "進化元スキル「{$skill_data['base_skill_name']}」を新規作成 (ID:{$base_skill_id})<br>";
                        }
                    }
                }
    
                // 3. スキル本体を登録（進化スキルの場合はbase_skill_idを設定、必要スキルポイント含む）
                $skill_id = registerOrUpdateSkill(
                    $conn,
                    $skill_data['name'],
                    $skill_data['description'],
                    $skill_data['type'],
                    $skill_data['distance'],
                    $skill_data['strategy'],
                    $skill_data['surface'],
                    $base_skill_id,
                    $skill_data['required_skill_points'] // 必要スキルポイント
                );
                
                if ($skill_id) {
                    $scraped_data['character_skills'][] = [
                        'skill_id' => $skill_id
                        // unlock_conditionを削除 - skillsテーブルから取得するため不要
                    ];
                    
                    if ($debug_mode) {
                        $progress = "スキル「{$skill_data['name']}」(ID:{$skill_id}) をタイプ「{$skill_data['type']}」で処理";
                        if ($base_skill_id) {
                            $progress .= " - 進化元「{$skill_data['base_skill_name']}」(ID:{$base_skill_id})と紐付け";
                        }
                        $progress .= " [距離:{$skill_data['distance']}, 脚質:{$skill_data['strategy']}, 馬場:{$skill_data['surface']}]";
                        if ($skill_data['required_skill_points']) {
                            $progress .= " [必要SP:{$skill_data['required_skill_points']}]";
                        }
                        $debug_info .= $progress . "<br>";
                    }
                }
            }

        } catch (Exception $e) {
            if ($debug_mode) $debug_info .= "スキル取得エラー: " . $e->getMessage() . "<br>";
            $scraped_data['character_skills'] = [];
        }


        if ($debug_mode && $scraped_data) {
            $debug_info .= "<br><strong>セッションに保存されるデータ:</strong><br>";
            $debug_info .= "<pre>" . print_r($scraped_data, true) . "</pre>";
        }

    } catch (Exception $e) {
        $error_message = "情報の取得に失敗しました。<br>エラー: " . $e->getMessage();
        if ($debug_mode) {
            $error_message .= "<br><br>デバッグ情報:<br>" . $debug_info;
        }
    } finally {
        if (isset($conn) && $conn) {
            $conn->close();
        }
    }
}

// ▼▼▼【修正】データをセッションに保存してリダイレクト（デバッグモード時は除外） ▼▼▼
if ($scraped_data && empty($error_message)) {
    // デバッグモードが有効でない場合のみリダイレクト
    if (!$debug_mode) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['scraped_data'] = $scraped_data;
        header('Location: add.php');
        exit;
    }
    // デバッグモード時はリダイレクトせず、成功メッセージを表示
    else {
        $success_message = "✅ データの取得が完了しました！<br>";
        $success_message .= "デバッグモードのため、自動リダイレクトを無効にしています。<br>";
        $success_message .= "<strong>取得されたデータ:</strong><br>";
        $success_message .= "• キャラクター名: " . htmlspecialchars($scraped_data['character_name']) . "<br>";
        $success_message .= "• スキル数: " . count($scraped_data['character_skills']) . "個<br>";
        if (isset($scraped_data['image_suit_path'])) {
            $success_message .= "• 画像: 取得済み<br>";
        }
        $success_message .= "<br><a href='add.php' class='button-primary' style='display: inline-block; margin: 10px 0; padding: 10px 15px; text-decoration: none;'>手動で登録ページへ進む</a>";
        
        // セッションにはデータを保存しておく（手動でadd.phpに行く場合のため）
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['scraped_data'] = $scraped_data;
    }
}

include '../templates/header.php';
?>
<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <p>GameWithのウマ娘個別ページのURLを貼り付けてください。<br>例: <code>https://gamewith.jp/uma-musume/article/show/345496</code></p>
    
    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="message success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724;">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($debug_info && isset($_POST['debug'])): ?>
        <div class="message debug" style="background: #f0f8ff; border: 1px solid #007acc;">
            <h3>🔍 詳細デバッグ情報:</h3>
            <?php echo $debug_info; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($scraped_data) && $debug_mode): ?>
        <div class="message debug" style="background: #f8f9fa; border: 1px solid #6c757d; margin-top: 20px;">
            <h3>📊 取得データの詳細:</h3>
            <details>
                <summary style="cursor: pointer; font-weight: bold; padding: 5px;">クリックして全取得データを表示</summary>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; margin-top: 10px;"><?php echo htmlspecialchars(print_r($scraped_data, true)); ?></pre>
            </details>
        </div>
    <?php endif; ?>

    <form action="import.php" method="POST">
        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" id="url" name="url" placeholder="https://gamewith.jp/uma-musume/article/show/..." value="<?php echo htmlspecialchars($url); ?>" required>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="debug" value="1" <?php echo (isset($_POST['debug']) && $_POST['debug'] == '1') ? 'checked' : ''; ?>>
                デバッグモード（詳細情報を表示）
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="button-primary">情報を読み込む</button>
            <a href="index.php" class="back-link">キャンセル</a>
        </div>
    </form>

    <div class="help-section" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <h3>取得される情報</h3>
        <ul>
            <li><strong>基本情報:</strong> キャラクター名、図鑑ID（自動照合）</li>
            <li><strong>ステータス:</strong> 初期能力値、成長率</li>
            <li><strong>適性:</strong> バ場、距離、脚質の各適性ランク</li>
            <li><strong>スキル:</strong> 所持スキル一覧（自動でスキルDBに登録）</li>
            <li><strong>解放条件:</strong> 必要スキルポイント、進化元スキル名</li>
            <li><strong>画像:</strong> キャラクター画像（自動ダウンロード）</li>
        </ul>
        
        <h3>トラブルシューティング</h3>
        <ul>
            <li>URLが正しいGameWithのウマ娘ページかどうか確認してください</li>
            <li>データが取得できない場合は、デバッグモードをチェックして再試行してください</li>
            <li>一部のデータが取得できない場合でも、取得できた分のデータは反映されます</li>
            <li>取得できなかったデータは手動で入力してください</li>
            <li>スキル情報は自動でデータベースに登録され、重複登録は回避されます</li>
            <li>必要スキルポイントも自動で検出・登録されます</li>
        </ul>
    </div>
</div>
<?php include '../templates/footer.php'; ?>
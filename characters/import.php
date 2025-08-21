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

// スキル登録関数
function registerSkillIfNotExists($conn, $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type) {
    $stmt = $conn->prepare("SELECT id FROM skills WHERE skill_name = ?");
    $stmt->bind_param("s", $skill_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO skills (skill_name, skill_description, skill_type, distance_type, strategy_type, surface_type) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
             throw new Exception("DB prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssssss", $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type);
        
        if ($stmt->execute()) {
            $skill_id = $conn->insert_id;
            $stmt->close();
            return $skill_id;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("スキル登録に失敗しました: " . $error);
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

            $skill_nodes = $crawler->filter('ol.wd-skill-list li, ul.wd-skill-list li');
            
            if ($debug_mode && $skill_nodes->count() === 0) {
                $debug_info .= "<strong style='color:red;'>警告: スキルリストが見つかりませんでした。('ol.wd-skill-list li, ul.wd-skill-list li')</strong><br>";
            }

            $skill_nodes->each(function($node) use (&$scraped_data, $conn, &$debug_info, $debug_mode) {
                $skill_name = '';
                $skill_description = '';
                $unlock_condition = '初期';
                $distance_type = '';
                $strategy_type = '';
                $surface_type = '';
                $skill_type = 'ノーマル';

                $head_node = $node->filter('._body ._head');
                $name_node = $head_node->filter('a');
                $desc_node = $node->filter('._body ._text');

                if ($name_node->count() > 0) {
                    $skill_name = trim($name_node->text());
                    
                    $full_head_text = trim($head_node->text());
                    $condition_text = trim(str_replace($skill_name, '', $full_head_text));
                    $unlock_condition = trim($condition_text, " ()");
                    if (empty($unlock_condition)) {
                        $unlock_condition = '初期';
                    }
                }
                
                if ($desc_node->count() > 0) {
                    $full_text_for_desc = $desc_node->html();
                    $parts = explode('<br>', $full_text_for_desc);
                    $skill_description = trim(strip_tags(array_shift($parts)));
                    $full_text_for_conditions = $desc_node->text();

                    if (preg_match('/(芝)/u', $full_text_for_conditions)) $surface_type = '芝';
                    if (preg_match('/(ダート)/u', $full_text_for_conditions)) $surface_type = 'ダート';
                    if (str_contains($full_text_for_conditions, '芝') && str_contains($full_text_for_conditions, 'ダート')) $surface_type = '芝/ダート';
                    if (preg_match('/(短距離)/u', $full_text_for_conditions)) $distance_type = '短距離';
                    if (preg_match('/(マイル)/u', $full_text_for_conditions)) $distance_type = 'マイル';
                    if (preg_match('/(中距離)/u', $full_text_for_conditions)) $distance_type = '中距離';
                    if (preg_match('/(長距離)/u', $full_text_for_conditions)) $distance_type = '長距離';
                    if (preg_match('/(逃げ)/u', $full_text_for_conditions)) $strategy_type = '逃げ';
                    if (preg_match('/(先行)/u', $full_text_for_conditions)) $strategy_type = '先行';
                    if (preg_match('/(差し)/u', $full_text_for_conditions)) $strategy_type = '差し';
                    if (preg_match('/(追込)/u', $full_text_for_conditions)) $strategy_type = '追込';
                }

                if (!empty($skill_name)) {
                    if (empty($skill_description)) $skill_description = 'スキル効果の詳細は確認中です。';
                    
                    // レア度はご自身で解決されたとのことなので、ここでは「ノーマル」で統一します。
                    // 必要に応じて、ご自身で実装されたレア度判別ロジックをここに組み込んでください。
                    $li_class = $node->attr('class');
                    if (str_contains((string)$li_class, 'unique')) $skill_type = '固有スキル';
                    elseif (str_contains((string)$li_class, 'evo')) $skill_type = '進化スキル';
                    elseif (str_contains((string)$li_class, 'rare')) $skill_type = 'レアスキル';
                    
                    $skill_id = registerSkillIfNotExists($conn, $skill_name, $skill_description, $skill_type, $distance_type, $strategy_type, $surface_type);
                    
                    if ($skill_id) {
                        $scraped_data['character_skills'][] = [
                            'skill_id' => $skill_id,
                            'unlock_condition' => $unlock_condition
                        ];
                        if ($debug_mode) {
                            $debug_info .= "スキル「{$skill_name}」(ID:{$skill_id}) を解放条件「{$unlock_condition}」タイプ「{$skill_type}」で取得<br>";
                        }
                    }
                }
            });

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

// データをセッションに保存してリダイレクト
if ($scraped_data && empty($error_message)) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
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

    <?php if ($debug_info && isset($_POST['debug'])): ?>
        <div class="message" style="background: #f0f8ff; border: 1px solid #007acc;">
            <h3>デバッグ情報:</h3>
            <?php echo $debug_info; ?>
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
            <li><strong>画像:</strong> キャラクター画像（自動ダウンロード）</li>
        </ul>
        
        <h3>トラブルシューティング</h3>
        <ul>
            <li>URLが正しいGameWithのウマ娘ページかどうか確認してください</li>
            <li>データが取得できない場合は、デバッグモードをチェックして再試行してください</li>
            <li>一部のデータが取得できない場合でも、取得できた分のデータは反映されます</li>
            <li>取得できなかったデータは手動で入力してください</li>
            <li>スキル情報は自動でデータベースに登録され、重複登録は回避されます</li>
        </ul>
    </div>
</div>
<?php include '../templates/footer.php'; ?>
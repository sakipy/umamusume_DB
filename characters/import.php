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
    // 相対URLがすでに絶対URLの場合、そのまま返す
    if (parse_url($relative, PHP_URL_SCHEME) !== null) {
        return $relative;
    }

    // ベースURLを解析
    $base_parts = parse_url($base);
    if ($base_parts === false) {
        return $relative;
    }

    // 絶対URLを構築
    $scheme = isset($base_parts['scheme']) ? $base_parts['scheme'] : 'https';
    $host = isset($base_parts['host']) ? $base_parts['host'] : '';
    $path = isset($base_parts['path']) ? $base_parts['path'] : '/';

    // ベースパスのファイル名を削除し、ディレクトリのみ保持
    $path = preg_replace('#/[^/]*$#', '/', $path);

    // 相対パスの処理
    if (substr($relative, 0, 1) === '/') {
        // ホストに対する絶対パス
        return "$scheme://$host$relative";
    }

    // パスを結合
    $absolute_path = $path . $relative;

    // ../ や ./ を解決
    $absolute_path = preg_replace('#/(\./)+#', '/', $absolute_path);
    while (preg_match('#/[^/]+/\.\./#', $absolute_path)) {
        $absolute_path = preg_replace('#/[^/]+/\.\./#', '/', $absolute_path);
    }

    return "$scheme://$host$absolute_path";
}

$scraped_data = null;
$error_message = '';
$url = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    
    // デバッグモード（開発時のみ有効にする）
    $debug_mode = isset($_POST['debug']) && $_POST['debug'] == '1';
    
    $client = new Client(HttpClient::create([
        'timeout' => 60,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]
    ]));
    
    try {
        $crawler = $client->request('GET', $url);
        
        // デバッグ用：ページのHTMLを一部確認
        if ($debug_mode) {
            $debug_info .= "URL: " . $url . "<br>";
            $debug_info .= "ページタイトル: " . $crawler->filter('title')->text() . "<br>";
        }
        
        $scraped_data = [];

        // 1. 名前の取得（複数パターンに対応）
        try {
            // パターン1: h2#hyoka（元のコード）
            $nameNode = $crawler->filter('h2#hyoka');
            if ($nameNode->count() > 0) {
                $scraped_data['character_name'] = str_replace('の評価', '', trim($nameNode->text()));
            } else {
                // パターン2: ページタイトルから抽出
                $title = $crawler->filter('title')->text();
                if (preg_match('/^(.+?)の/', $title, $matches)) {
                    $scraped_data['character_name'] = trim($matches[1]);
                } else {
                    // パターン3: h1タグから抽出
                    $h1Node = $crawler->filter('h1');
                    if ($h1Node->count() > 0) {
                        $h1Text = $h1Node->text();
                        $scraped_data['character_name'] = preg_replace('/の評価|評価|攻略/', '', $h1Text);
                        $scraped_data['character_name'] = trim($scraped_data['character_name']);
                    } else {
                        throw new Exception("キャラクター名を取得できませんでした。");
                    }
                }
            }
        } catch (Exception $e) {
            throw new Exception("名前の取得に失敗: " . $e->getMessage());
        }

        if ($debug_mode) {
            $debug_info .= "取得した名前: " . $scraped_data['character_name'] . "<br>";
        }

        // 2. 基礎能力と成長率の取得
        try {
            if ($debug_mode) {
                $debug_info .= "<strong>--- 基礎能力の取得開始 ---</strong><br>";
            }
            
            // 全てのテーブルを探索
            $all_tables = $crawler->filter('table');
            $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
            $status_found = false;
            $growth_found = false;
            
            if ($debug_mode) {
                $debug_info .= "ページ内のテーブル数: " . $all_tables->count() . "<br>";
            }
            
            // 各テーブルをチェック
            $all_tables->each(function ($table, $table_index) use (&$scraped_data, $status_map, &$status_found, &$growth_found, $debug_mode, &$debug_info) {
                if ($debug_mode) {
                    $debug_info .= "<br><strong>テーブル {$table_index}:</strong><br>";
                }
                
                $rows = $table->filter('tr');
                $growth_values_accum = []; // 縦型テーブルの蓄積用
                $table_is_growth_vertical = false;

                $rows->each(function ($row, $row_index) use (&$scraped_data, $status_map, &$status_found, &$growth_found, $debug_mode, &$debug_info, $table_index, &$growth_values_accum, &$table_is_growth_vertical) {
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
                        
                        if ($debug_mode && count($cell_values) > 1) {
                            $debug_info .= "  行{$row_index}: " . implode(' | ', $cell_values) . "<br>";
                        }
                        
                        // 初期ステータス行の判定
                        if (!$status_found && count($cell_values) >= 6) {
                            // 数値が5つ連続で含まれているかチェック
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
                                 $numeric_values[0] > 50)) { // 初期値は通常50以上
                                
                                foreach ($status_map as $index => $stat) {
                                    if (isset($numeric_values[$index])) {
                                        $scraped_data['initial_' . $stat] = $numeric_values[$index];
                                    }
                                }
                                $status_found = true;
                                
                                if ($debug_mode) {
                                    $debug_info .= "  ★初期ステータス発見！<br>";
                                }
                            }
                        }
                        
                        // 成長率行の判定（横型対応: 1行に5つの%を含むセル）
                        if (!$growth_found && count($cell_values) >= 5 && preg_match('/(成長|%)/u', implode(' ', $cell_values))) {
                            $growth_values = [];
                            
                            for ($i = 0; $i < count($cell_values); $i++) { // 横型なのでi=0から
                                if (preg_match('/(\d+(?:\.\d+)?)/', $cell_values[$i], $matches)) {
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
                                
                                if ($debug_mode) {
                                    $debug_info .= "  ★成長率発見（横型）！<br>";
                                }
                            }
                        }
                        
                        // 縦型成長率の蓄積（各行がth + td %）
                        if (count($cell_values) == 2 && preg_match('/%/', $cell_values[1])) {
                            $table_is_growth_vertical = true;
                            if (preg_match('/(\d+(?:\.\d+)?)/', $cell_values[1], $matches)) {
                                $growth_values_accum[] = (float)$matches[1];
                            }
                        }
                    }
                });
                
                // 縦型テーブルの確認
                if (!$growth_found && $table_is_growth_vertical && count($growth_values_accum) == 5) {
                    foreach ($status_map as $index => $stat) {
                        $scraped_data['growth_rate_' . $stat] = $growth_values_accum[$index];
                    }
                    $growth_found = true;
                    if ($debug_mode) {
                        $debug_info .= "  ★成長率発見（縦型）！<br>";
                    }
                }
                
                // 両方見つかったら終了
                if ($status_found && $growth_found) {
                    return false;
                }
            });
            
            // デフォルト値設定
            if (!$status_found) {
                foreach ($status_map as $stat) {
                    $scraped_data['initial_' . $stat] = 100;
                }
                if ($debug_mode) {
                    $debug_info .= "初期ステータスが見つからなかったため、デフォルト値(100)を設定<br>";
                }
            }
            
            if (!$growth_found) {
                foreach ($status_map as $stat) {
                    $scraped_data['growth_rate_' . $stat] = 0;
                }
                if ($debug_mode) {
                    $debug_info .= "成長率が見つからなかったため、デフォルト値(0)を設定<br>";
                }
            }

        } catch (Exception $e) {
            // エラー時はデフォルト値を設定
            $status_map = ['speed', 'stamina', 'power', 'guts', 'wisdom'];
            foreach ($status_map as $stat) {
                $scraped_data['initial_' . $stat] = 100;
                $scraped_data['growth_rate_' . $stat] = 0;
            }
            if ($debug_mode) {
                $debug_info .= "ステータス取得エラー: " . $e->getMessage() . "<br>";
            }
        }

        // 3. 適性の取得
        try {
            if ($debug_mode) {
                $debug_info .= "<br><strong>--- 適性の取得開始 ---</strong><br>";
            }
            
            // 適性テーブルを探す
            $aptitude_tables = $crawler->filter('table');
            $aptitudes_found = false;
            
            // 適性ランク判定関数（改良版）
            function getRankFromElement($element) {
                // 画像のsrcを確認
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
                
                // テキストから直接ランクを取得
                $text = trim($element->text());
                if (preg_match('/^([A-G]\+?)$/', $text, $matches)) {
                    return $matches[1];
                }
                
                // クラス名から取得
                $class = $element->attr('class');
                if ($class && preg_match('/rank[_-]([A-G])/i', $class, $matches)) {
                    return strtoupper($matches[1]);
                }
                
                return null;
            }
            
            // 適性マッピング
            $aptitudes_map = [
                'バ場' => ['surface_aptitude_turf', 'surface_aptitude_dirt'],
                '距離' => ['distance_aptitude_short', 'distance_aptitude_mile', 'distance_aptitude_medium', 'distance_aptitude_long'],
                '脚質' => ['strategy_aptitude_runner', 'strategy_aptitude_leader', 'strategy_aptitude_chaser', 'strategy_aptitude_trailer']
            ];
            
            // テーブルごとの処理
            $aptitude_tables->each(function ($table, $table_index) use (&$scraped_data, $aptitudes_map, &$aptitudes_found, $debug_mode, &$debug_info) {
                if ($debug_mode) {
                    $debug_info .= "<br><strong>適性テーブル {$table_index}:</strong><br>";
                }
                
                $rows = $table->filter('tr');
                $rows->each(function ($row, $row_index) use (&$scraped_data, $aptitudes_map, &$aptitudes_found, $debug_mode, &$debug_info) {
                    $th = $row->filter('th');
                    $tds = $row->filter('td');
                    
                    if ($th->count() > 0 && $tds->count() >= 2) {
                        $thText = trim($th->text());
                        
                        if ($debug_mode) {
                            $debug_info .= "  行{$row_index}: {$thText} (セル数: " . $tds->count() . ")<br>";
                        }
                        
                        // 適性タイプを特定
                        $matched_type = null;
                        $matched_fields = null;
                        
                        foreach ($aptitudes_map as $key => $fields) {
                            if (strpos($thText, $key) !== false) {
                                $matched_type = $key;
                                $matched_fields = $fields;
                                break;
                            }
                        }
                        
                        if ($matched_fields) {
                            if ($debug_mode) {
                                $debug_info .= "    マッチした適性タイプ: {$matched_type}<br>";
                            }
                            
                            // 各セルからランクを取得
                            $ranks_found = [];
                            $tds->each(function ($td, $td_index) use (&$ranks_found, $debug_mode, &$debug_info) {
                                $rank = getRankFromElement($td);
                                if ($rank) {
                                    $ranks_found[] = $rank;
                                    if ($debug_mode) {
                                        $debug_info .= "      セル{$td_index}: {$rank}<br>";
                                    }
                                } else {
                                    $text = trim($td->text());
                                    if ($debug_mode && $text) {
                                        $debug_info .= "      セル{$td_index}: {$text} (ランク取得失敗)<br>";
                                    }
                                }
                            });
                            
                            // フィールドに値を設定
                            foreach ($matched_fields as $field_index => $field_name) {
                                if (isset($ranks_found[$field_index])) {
                                    $scraped_data[$field_name] = $ranks_found[$field_index];
                                    $aptitudes_found = true;
                                    
                                    if ($debug_mode) {
                                        $debug_info .= "    設定: {$field_name} = {$ranks_found[$field_index]}<br>";
                                    }
                                }
                            }
                        }
                    }
                });
            });
            
            // テーブル以外での適性取得（保険として）
            if (!$aptitudes_found) {
                if ($debug_mode) {
                    $debug_info .= "テーブルから適性が見つからなかったため、代替セレクタを試行<br>";
                }
                
                // 代替: テキストベースで適性を探す
                $aptitude_sections = $crawler->filter('div, span, td')->filterXPath('//*[contains(text(), "適性")]');
                $aptitude_sections->each(function ($section) use (&$scraped_data, &$aptitudes_found, $aptitudes_map, $debug_mode, &$debug_info) {
                    $text = $section->text();
                    foreach ($aptitudes_map as $key => $fields) {
                        if (strpos($text, $key) !== false) {
                            $siblings = $section->siblings()->filter('span, td');
                            $ranks_found = [];
                            $siblings->each(function ($sibling) use (&$ranks_found, $debug_mode, &$debug_info) {
                                $rank = getRankFromElement($sibling);
                                if ($rank) {
                                    $ranks_found[] = $rank;
                                    if ($debug_mode) {
                                        $debug_info .= "      代替セレクタでランク発見: {$rank}<br>";
                                    }
                                }
                            });
                            
                            foreach ($fields as $field_index => $field_name) {
                                if (isset($ranks_found[$field_index])) {
                                    $scraped_data[$field_name] = $ranks_found[$field_index];
                                    $aptitudes_found = true;
                                }
                            }
                        }
                    }
                });
            }
            
            if ($debug_mode) {
                $debug_info .= "適性取得完了。見つかった適性: " . ($aptitudes_found ? 'あり' : 'なし') . "<br>";
            }
            
            // デフォルト値設定（適性が取得できなかった場合）
            $default_aptitudes = [
                'surface_aptitude_turf' => 'C',
                'surface_aptitude_dirt' => 'C',
                'distance_aptitude_short' => 'C',
                'distance_aptitude_mile' => 'C',
                'distance_aptitude_medium' => 'C',
                'distance_aptitude_long' => 'C',
                'strategy_aptitude_runner' => 'C',
                'strategy_aptitude_leader' => 'C',
                'strategy_aptitude_chaser' => 'C',
                'strategy_aptitude_trailer' => 'C'
            ];
            
            foreach ($default_aptitudes as $key => $default_value) {
                if (!isset($scraped_data[$key])) {
                    $scraped_data[$key] = $default_value;
                    if ($debug_mode) {
                        $debug_info .= "デフォルト設定: {$key} = {$default_value}<br>";
                    }
                }
            }

        } catch (Exception $e) {
            if ($debug_mode) {
                $debug_info .= "適性取得エラー: " . $e->getMessage() . "<br>";
            }
            // デフォルト値を設定
            $default_aptitudes = [
                'surface_aptitude_turf' => 'C', 'surface_aptitude_dirt' => 'C',
                'distance_aptitude_short' => 'C', 'distance_aptitude_mile' => 'C',
                'distance_aptitude_medium' => 'C', 'distance_aptitude_long' => 'C',
                'strategy_aptitude_runner' => 'C', 'strategy_aptitude_leader' => 'C',
                'strategy_aptitude_chaser' => 'C', 'strategy_aptitude_trailer' => 'C'
            ];
            $scraped_data = array_merge($scraped_data, $default_aptitudes);
        }

        // 4. 画像の自動ダウンロード
        try {
            // 複数のセレクタを試行
            $image_node = $crawler->filter('img[alt*="のアイキャッチ"], img[src*="/chara"], img[src*="/character"]');
            if ($image_node->count() > 0) {
                $relative_src = $image_node->first()->attr('data-original') ?: $image_node->first()->attr('src');
                $image_url = urljoin($url, $relative_src);
                
                if ($debug_mode) {
                    $debug_info .= "画像URL発見: " . $image_url . "<br>";
                }
                
                // ダウンロード
                $upload_dir = '../uploads/characters/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = time() . '_suit_' . basename(parse_url($image_url, PHP_URL_PATH));
                $target_file = $upload_dir . $file_name;
                
                // MIMEタイプのチェック
                $image_content = file_get_contents($image_url);
                if ($image_content) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->buffer($image_content);
                    if (strpos($mime_type, 'image/') !== 0) {
                        throw new Exception("無効な画像形式です: $mime_type");
                    }
                    
                    if (file_put_contents($target_file, $image_content)) {
                        $scraped_data['image_suit_path'] = 'uploads/characters/' . $file_name;
                        if ($debug_mode) {
                            $debug_info .= "画像ダウンロード成功: " . $target_file . "<br>";
                        }
                    } else {
                        throw new Exception("画像の保存に失敗しました");
                    }
                } else {
                    throw new Exception("画像ダウンロード失敗");
                }
            } else {
                if ($debug_mode) {
                    $debug_info .= "画像が見つかりませんでした。セレクタ: img[alt*=\"のアイキャッチ\"], img[src*=\"/chara\"], img[src*=\"/character\"]<br>";
                }
            }
        } catch (Exception $e) {
            if ($debug_mode) {
                $debug_info .= "画像取得エラー: " . $e->getMessage() . "<br>";
            }
        }

        // セッション保存前にデータを確認
        if ($debug_mode && $scraped_data) {
            $debug_info .= "<br><strong>セッションに保存されるデータ:</strong><br>";
            $debug_info .= "<pre>" . print_r($scraped_data, true) . "</pre>";
        }
        
    } catch (Exception $e) {
        $error_message = "情報の取得に失敗しました。<br>エラー: " . $e->getMessage();
        if ($debug_mode) {
            $error_message .= "<br><br>デバッグ情報:<br>" . $debug_info;
        }
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
        <h3>トラブルシューティング</h3>
        <ul>
            <li>URLが正しいGameWithのウマ娘ページかどうか確認してください</li>
            <li>データが取得できない場合は、デバッグモードをチェックして再試行してください</li>
            <li>一部のデータが取得できない場合でも、取得できた分のデータは反映されます</li>
            <li>取得できなかったデータは手動で入力してください</li>
        </ul>
    </div>
</div>
<?php include '../templates/footer.php'; ?>
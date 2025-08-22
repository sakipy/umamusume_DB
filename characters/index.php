<?php
// ========== ページ設定 ==========
$page_title = 'ウマ娘一覧';
$current_page = 'characters';
$base_path = '../';

// ========== DB接続 ==========
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'umamusume_db';

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die('接続失敗: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$total_characters_result = $conn->query("SELECT COUNT(*) as count FROM characters");
$total_characters = $total_characters_result->fetch_assoc()['count'];

// --- フォーム表示用の選択肢を定義 ---
$sort_options = ['id_desc' => '新着順', 'name_asc' => 'あいうえお順'];
$rarity_options = ['3' => '★3', '2' => '★2', '1' => '★1'];
$aptitude_options = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];

/**
 * ★★★ 新しく追加する関数 ★★★
 * キャラクター名を接頭語と本体に分割する関数
 * 接尾語は接頭語の前に移動させる
 */
function splitCharacterName($fullName) {
    $prefixes = [];
    $main = $fullName;

    // 1. 接尾語(例: (水着)) を抽出し、接頭語リストの最初に移動
    if (preg_match('/(.*)(【(.+?)】|\((.+?)\))$/u', $main, $matches)) {
        $main = trim($matches[1]);
        $suffixContent = !empty($matches[3]) ? $matches[3] : $matches[4];
        array_unshift($prefixes, $suffixContent);
    }

    // 2. 接頭語(例: [新衣装]) を抽出し、接頭語リストに追加
    if (preg_match('/^([\[【](.+?)[\]】])(.*)/u', $main, $matches)) {
        $main = trim($matches[3]);
        $prefixes[] = $matches[2]; // 括弧の中身だけ
    }
    
    // 3. 特定の単語の接頭語(例: 水着ヴィブロス) を抽出し、接頭語リストに追加
    $prefix_words = ['水着'];
    foreach ($prefix_words as $p) {
        if (strpos($main, $p) === 0) {
            if (!in_array($p, $prefixes)) {
                 $prefixes[] = $p;
            }
            $main = trim(substr($main, strlen($p)));
            break;
        }
    }

    return [
        'prefix' => implode(' ', $prefixes), // 配列をスペースで連結
        'main'   => trim($main)
    ];
}

// テンプレート読み込み
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container full-width">
    <h1>ウマ娘管理</h1>

    <div class="summary-bar">
        <span>登録数: <?php echo $total_characters; ?>人</span>
    </div>

    <form id="filterForm">
        <div class="controls-container">
            <div class="page-actions">
                <a href="add.php" class="add-link">新しいウマ娘を追加する</a>
                <a href="import.php" class="add-link" style="background-color: #f39c12; border-color: #d68910;">URLを指定してインポート</a>
                <a href="scrape_all.php" class="add-link" style="background-color: #e74c3c; border-color: #c0392b;">未登録データを一括インポート</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button type="button" id="open-advanced-filter" class="action-button button-edit">詳細絞り込み</button>
                <div class="active-filters-container" id="active-filters-container"></div>
            </div>
        </div>

        <div class="filter-container">
            <div class="filter-group">
                <label for="search_name">ウマ娘名:</label>
                <input type="text" name="search_name" id="search_name" placeholder="名前で検索...">
            </div>
            <div class="filter-group">
                <label for="filter_rarity">初期レアリティ:</label>
                <select name="rarity" id="filter_rarity">
                    <option value="">すべて</option>
                    <?php foreach ($rarity_options as $key => $text): ?><option value="<?php echo $key; ?>"><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="sort">並べ替え:</label>
                <select name="sort" id="sort">
                    <?php foreach ($sort_options as $key => $text): ?><option value="<?php echo $key; ?>"><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="back-link">リセット</a>
        </div>

        <div class="character-card-grid" id="character-card-grid">
            <p>読み込み中...</p>
        </div>

        <div id="advanced-filter-modal" class="modal-overlay">
            <div class="modal-content" style="width: 900px;">
                <button type="button" id="close-advanced-filter" class="modal-close-button">×</button>
                <h2>適性・成長率で絞り込み</h2>
                <div class="aptitude-filter-grid">
                    <div class="form-group">
                        <label>バ場適性</label>
                        <div class="aptitude-row">
                            <span>芝</span><select name="apt_turf" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>ダート</span><select name="apt_dirt" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>距離適性</label>
                        <div class="aptitude-row">
                            <span>短</span><select name="apt_short" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>マ</span><select name="apt_mile" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>中</span><select name="apt_medium" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>長</span><select name="apt_long" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>脚質適性</label>
                        <div class="aptitude-row">
                            <span>逃げ</span><select name="apt_runner" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>先行</span><select name="apt_leader" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>差し</span><select name="apt_chaser" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                            <span>追込</span><select name="apt_trailer" class="aptitude-select"><option value="">-</option><?php foreach($aptitude_options as $op) echo "<option value='$op'>$op</option>"; ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>成長率あり</label>
                         <div class="toggle-checkbox-group">
                            <input type="checkbox" name="growth[]" value="speed" id="growth_speed"><label for="growth_speed">スピード</label>
                            <input type="checkbox" name="growth[]" value="stamina" id="growth_stamina"><label for="growth_stamina">スタミナ</label>
                            <input type="checkbox" name="growth[]" value="power" id="growth_power"><label for="growth_power">パワー</label>
                            <input type="checkbox" name="growth[]" value="guts" id="growth_guts"><label for="growth_guts">根性</label>
                            <input type="checkbox" name="growth[]" value="wisdom" id="growth_wisdom"><label for="growth_wisdom">賢さ</label>
                        </div>
                    </div>
                </div>
                <button type="button" id="apply-advanced-filter" class="back-link" style="width: 100%; margin-top: 15px;">この条件で絞り込む</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const cardGrid = document.getElementById('character-card-grid');
    const allInputs = filterForm.querySelectorAll('input, select');
    let searchTimer;

    function performSearch() {
        cardGrid.classList.add('loading');
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        
        fetch('search_characters.php?' + params)
            .then(response => response.json())
            .then(data => {
                // Ajaxで取得したHTMLには名前の二段表示ロジックが含まれている必要がある
                cardGrid.innerHTML = data.card_html;
                document.getElementById('active-filters-container').innerHTML = data.badge_html;
            })
            .catch(error => {
                cardGrid.innerHTML = '<p>検索結果の読み込みに失敗しました。</p>';
                console.error('Fetch error:', error);
            })
            .finally(() => {
                cardGrid.classList.remove('loading');
            });
    }

    allInputs.forEach(input => {
        if (input.type === 'text') {
            input.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(performSearch, 500); });
        } else {
            input.addEventListener('change', performSearch);
        }
    });

    const modal = document.getElementById('advanced-filter-modal');
    const openBtn = document.getElementById('open-advanced-filter');
    const closeBtn = document.getElementById('close-advanced-filter');
    const applyBtn = document.getElementById('apply-advanced-filter');

    if(modal && openBtn && closeBtn && applyBtn) {
        openBtn.addEventListener('click', () => modal.classList.add('active'));
        const closeModal = () => { modal.classList.remove('active'); performSearch(); };
        closeBtn.addEventListener('click', closeModal);
        applyBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    }
    
    performSearch(); // 初回読み込み
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
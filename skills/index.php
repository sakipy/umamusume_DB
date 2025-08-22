<?php
$page_title = 'スキル一覧';
$current_page = 'skills';
$base_path = '../'; // このページから見たときのパスの基点

// ========== DB接続 ==========
$conn = new mysqli('localhost', 'root', '', 'umamusume_db');
if ($conn->connect_error) {
    die("DB接続失敗: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ▼▼▼【修正】DB接続後にクエリを実行するよう順番を修正 ▼▼▼
$total_skills_result = $conn->query("SELECT COUNT(*) as count FROM skills");
$total_skills = $total_skills_result->fetch_assoc()['count'];

// --- フォーム表示用の選択肢を定義 ---
$sort_options = ['id_desc' => '新着順', 'name_asc' => 'あいうえお順'];
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$skill_type_options = ['ノーマルスキル', 'レアスキル', '進化スキル', '固有スキル', 'その他'];
$surface_options = ['芝', 'ダート'];

// --- GETパラメータの受け取り（フォームの初期値設定にのみ使用） ---
$search_keyword = $_GET['search'] ?? '';
$filter_distance = $_GET['distance'] ?? '';
$filter_strategy = $_GET['strategy'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_surface = $_GET['surface'] ?? '';
$sort_key = $_GET['sort'] ?? 'id_desc';

// --- ここからHTMLの出力 ---
require_once __DIR__ . '/../templates/header.php'; // 共通ヘッダーを読み込む
?>

<div class="container full-width">
    <h1>スキル管理</h1>

    <div class="summary-bar">
        <span>登録数: <?php echo $total_skills; ?>件</span>
    </div>

    <form id="filterForm">
        <div class="controls-container">
            <div class="page-actions">
                <a href="add.php" class="add-link">新しいスキルを追加する</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button type="button" id="open-advanced-filter" class="action-button button-edit">詳細絞り込み</button>
                <div class="active-filters-container" id="active-filters-container"></div>
            </div>
        </div>
        
        <div class="filter-container">
            <div class="filter-group">
                <label for="search">スキル名</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="ひらがなでも検索可能">
            </div>
            <div class="filter-group">
                <label for="sort">並べ替え</label>
                <select name="sort" id="sort">
                    <?php foreach ($sort_options as $key => $text): ?>
                        <option value="<?php echo $key; ?>" <?php if ($key === $sort_key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="back-link">リセット</a>
        </div>

        <div class="skill-list-container" id="skill-card-grid">
            <p>読み込み中...</p>
        </div>

        <div id="advanced-filter-modal" class="modal-overlay">
            <div class="modal-content">
                <button type="button" id="close-advanced-filter" class="modal-close-button">&times;</button>
                <h2>詳細絞り込み</h2>
                <div class="form-group">
                    <label for="distance">距離:</label>
                    <select id="distance" name="distance">
                        <option value="">すべて</option>
                        <?php foreach($distance_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php if($filter_distance == $option) echo 'selected'; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                        <option value="none" <?php if($filter_distance == 'none') echo 'selected'; ?>>指定なし</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="strategy">脚質:</label>
                    <select id="strategy" name="strategy">
                        <option value="">すべて</option>
                        <?php foreach($strategy_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php if($filter_strategy == $option) echo 'selected'; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                        <option value="none" <?php if($filter_strategy == 'none') echo 'selected'; ?>>指定なし</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="surface">バ場適性:</label>
                    <select id="surface" name="surface">
                        <option value="">すべて</option>
                        <?php foreach($surface_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php if($filter_surface == $option) echo 'selected'; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                        <option value="none" <?php if($filter_surface == 'none') echo 'selected'; ?>>指定なし</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="type">スキルタイプ:</label>
                    <select id="type" name="type">
                        <option value="">すべて</option>
                        <?php foreach($skill_type_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php if($filter_type == $option) echo 'selected'; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="apply-advanced-filter" class="back-link" style="width: 100%; margin-top: 15px;">閉じる</button>
            </div>
        </div>
    </form>
</div>
    
<script>
document.addEventListener('DOMContentLoaded', function() {
    // JavaScript部分は変更なし！
    const filterForm = document.getElementById('filterForm');
    const cardGrid = document.getElementById('skill-card-grid');
    const activeFiltersContainer = document.getElementById('active-filters-container');
    const allInputs = filterForm.querySelectorAll('input, select');
    let searchTimer;

    function performSearch() {
        cardGrid.classList.add('loading');
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        
        fetch('search_skills.php?' + params)
            .then(response => response.json())
            .then(data => {
                cardGrid.innerHTML = data.skill_html;
                activeFiltersContainer.innerHTML = data.badge_html;
                cardGrid.classList.remove('loading');
            })
            .catch(error => {
                cardGrid.innerHTML = '<p>検索結果の読み込みに失敗しました。</p>';
                activeFiltersContainer.innerHTML = '';
                cardGrid.classList.remove('loading');
                console.error('Fetch error:', error);
            });
    }

    allInputs.forEach(input => {
        if (input.type === 'text') {
            input.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(performSearch, 200);
            });
        } else if(input.tagName === 'SELECT') {
            input.addEventListener('change', performSearch);
        }
    });
    
    const modal = document.getElementById('advanced-filter-modal');
    const openBtn = document.getElementById('open-advanced-filter');
    const closeBtn = document.getElementById('close-advanced-filter');
    const applyBtn = document.getElementById('apply-advanced-filter');

    if(modal && openBtn && closeBtn && applyBtn) {
        openBtn.addEventListener('click', function() { modal.classList.add('active'); });
        closeBtn.addEventListener('click', function() { modal.classList.remove('active'); });
        applyBtn.addEventListener('click', function() { modal.classList.remove('active'); });
        modal.addEventListener('click', function(event) {
            if (event.target === modal) { modal.classList.remove('active'); }
        });
    }
    
    performSearch();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php'; // 共通フッターを読み込む
?>
<?php
// ========== ページ設定 ==========
$page_title = 'サポートカード一覧';
$current_page = 'support_card';
$base_path = '../';

// ========== データベース接続設定 ==========
$db_host = 'localhost'; 
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'umamusume_db';

// ========== データベース接続 ==========
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { 
    die("DB接続失敗: " . $conn->connect_error); 
}
$conn->set_charset("utf8mb4");

// --- フォーム表示用の選択肢を定義 ---
$sort_options = [
    'id_desc' => '新着順', 'name_asc' => 'あいうえお順',
    'friendship_bonus_desc' => '友情ボーナスが高い順', 'race_bonus_desc' => 'レースボーナスが高い順',
    'training_effect_up_desc' => 'トレーニング効果が高い順', 'specialty_rate_up_desc' => '得意率が高い順',
    'initial_bond_desc' => '初期絆ゲージが高い順', 'skill_point_bonus_desc' => 'スキルPtボーナスが高い順',
    'speed_bonus_desc' => 'スピードボーナスが高い順', 'stamina_bonus_desc' => 'スタミナボーナスが高い順',
    'power_bonus_desc' => 'パワーボーナスが高い順', 'guts_bonus_desc' => '根性ボーナスが高い順',
    'wisdom_bonus_desc' => '賢さボーナスが高い順', 'initial_skill_point_bonus_desc' => '初期スキルPtが高い順'
];
$rarity_options = ['SSR', 'SR', 'R'];
$type_options = ['スピード', 'スタミナ', 'パワー', '根性', '賢さ', '友人', 'グループ'];

// 全スキルリストを取得（モーダルの選択肢用）
$all_skills = [];
$skills_result = $conn->query("SELECT id, skill_name, skill_type FROM skills ORDER BY skill_name ASC");
while ($row = $skills_result->fetch_assoc()) { 
    $all_skills[] = $row; 
}

// --- GETパラメータの受け取り（フォームの初期値設定にのみ使用） ---
$sort_key = $_GET['sort'] ?? 'id_desc';
$filter_rarity = $_GET['rarity'] ?? '';
$filter_type = $_GET['type'] ?? '';
$search_keyword = $_GET['search'] ?? '';
$filter_skill_ids = $_GET['skill_ids'] ?? [];
$filter_friendship_min = (int)($_GET['friendship_min'] ?? 0);
$filter_race_bonus_min = (int)($_GET['race_bonus_min'] ?? 0);
$filter_training_effect_min = (int)($_GET['training_effect_min'] ?? 0);
$filter_specialty_rate_min = (int)($_GET['specialty_rate_min'] ?? 0);
$filter_speed_bonus_min = (int)($_GET['speed_bonus_min'] ?? 0);
$filter_stamina_bonus_min = (int)($_GET['stamina_bonus_min'] ?? 0);
$filter_power_bonus_min = (int)($_GET['power_bonus_min'] ?? 0);
$filter_guts_bonus_min = (int)($_GET['guts_bonus_min'] ?? 0);
$filter_wisdom_bonus_min = (int)($_GET['wisdom_bonus_min'] ?? 0);
$filter_initial_skill_point_bonus_min = (int)($_GET['initial_skill_point_bonus_min'] ?? 0); 

// --- 適用中フィルターの表示ロジック（初回読み込み用） ---
$active_filters = [];
if (!empty($filter_skill_ids) && is_array($filter_skill_ids)) {
    $skill_names = [];
    foreach ($all_skills as $skill) {
        if (in_array($skill['id'], $filter_skill_ids)) {
            $skill_names[] = $skill['skill_name'];
        }
    }
    if(!empty($skill_names)){
        $active_filters[] = "スキル: " . implode(', ', $skill_names);
    }
}
if ($filter_friendship_min > 0) { $active_filters[] = "友情ボーナス: {$filter_friendship_min}以上"; }
if ($filter_race_bonus_min > 0) { $active_filters[] = "レースボーナス: {$filter_race_bonus_min}以上"; }
if ($filter_training_effect_min > 0) { $active_filters[] = "トレーニング効果: {$filter_training_effect_min}以上"; }
if ($filter_specialty_rate_min > 0) { $active_filters[] = "得意率: {$filter_specialty_rate_min}以上"; }
if ($filter_speed_bonus_min > 0) { $active_filters[] = "スピードボーナス: {$filter_speed_bonus_min}以上"; }
if ($filter_stamina_bonus_min > 0) { $active_filters[] = "スタミナボーナス: {$filter_stamina_bonus_min}以上"; }
if ($filter_power_bonus_min > 0) { $active_filters[] = "パワーボーナス: {$filter_power_bonus_min}以上"; }
if ($filter_guts_bonus_min > 0) { $active_filters[] = "根性ボーナス: {$filter_guts_bonus_min}以上"; }
if ($filter_wisdom_bonus_min > 0) { $active_filters[] = "賢さボーナス: {$filter_wisdom_bonus_min}以上"; }
if ($filter_initial_skill_point_bonus_min > 0) { $active_filters[] = "初期スキルPt: {$filter_initial_skill_point_bonus_min}以上"; }
if (!isset($sort_options[$sort_key])) { $sort_key = 'id_desc'; }

ob_start();
if (!empty($active_filters)):
    echo '<span>適用中の条件:</span>';
    foreach ($active_filters as $filter_text) {
        echo '<span class="filter-badge">' . htmlspecialchars($filter_text) . '</span>';
    }
endif;
$initial_badge_html = ob_get_clean();
$conn->close();
?>
<?php include '../templates/header.php'; ?>

<style>
    .filter-container { background-color: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .filter-group label { font-weight: bold; font-size: 0.9em; }
    .filter-group select, .filter-group input { padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 16px; }
    .controls-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 24px; }
    .page-actions { display: flex; gap: 15px; margin-right: auto; }
    .card-grid.loading { opacity: 0.5; transition: opacity 0.3s; }
</style>

<div class="container full-width">
    <h1>サポートカード一覧</h1>
    
    <form id="filterForm">
        <div class="controls-container">
            <div class="top-controls">
                <div class="page-actions">
                    <a href="add_card.php" class="add-link">新しいカードを追加する</a>
                </div>
                <div class="view-options">
                    <button type="button" id="sc-view-mode-default" class="view-mode-btn active">名前あり</button>
                    <button type="button" id="sc-view-mode-simple" class="view-mode-btn">画像のみ</button>
                </div>
            </div>

            <div class="bottom-controls">
                <button type="button" id="open-advanced-filter" class="action-button button-edit">詳細絞り込み</button>
                <div class="active-filters-container" id="active-filters-container">
                    <?php echo $initial_badge_html; ?>
                </div>
            </div>
        </div>
        
        <div class="filter-container">
            <div class="filter-group">
                <label for="search">カード名:</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_keyword); ?>">
            </div>
            <div class="filter-group">
                <label for="rarity">レアリティ:</label>
                <select name="rarity" id="rarity">
                    <option value="">すべて</option>
                    <?php foreach ($rarity_options as $option): ?><option value="<?php echo $option; ?>" <?php if ($option === $filter_rarity) echo 'selected'; ?>><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="type">タイプ:</label>
                <select name="type" id="type">
                    <option value="">すべて</option>
                    <?php foreach ($type_options as $option): ?><option value="<?php echo $option; ?>" <?php if ($option === $filter_type) echo 'selected'; ?>><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="sort">並べ替え:</label>
                <select name="sort" id="sort">
                    <?php foreach ($sort_options as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($key === $sort_key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="back-link">リセット</a>
        </div>

        <div class="card-grid" id="card-grid-container">
            <p>読み込み中...</p>
        </div>

        <div id="advanced-filter-modal" class="modal-overlay">
            <div class="modal-content">
                <button type="button" id="close-advanced-filter" class="modal-close-button">&times;</button>
                <h2>詳細絞り込み</h2>
                <div class="form-group">
                    <label>所有スキル (AND検索):</label>
                    <input type="text" id="skill-search-in-modal" placeholder="スキル名でリストを絞り込み..." style="margin-bottom: 10px;">
                    <div class="skill-selection-area" style="margin-top: 0; max-height: 250px; overflow-y: auto;">
                        <ul class="skill-list grid-layout" id="modal-skill-list">
                            <?php foreach($all_skills as $skill): ?>
                                <?php $is_checked = in_array($skill['id'], $filter_skill_ids); ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" <?php if($is_checked) echo 'checked'; ?>>
                                        <div class="skill-name-container">
                                            <?php
                                                $text_class = '';
                                                if ($skill['skill_type'] == 'レアスキル') { $text_class = 'text-rare'; } 
                                                elseif ($skill['skill_type'] == '進化スキル') { $text_class = 'text-evolution'; }
                                                elseif ($skill['skill_type'] == '固有スキル') { $text_class = 'text-rainbow'; }
                                            ?>
                                            <span class="<?php echo $text_class; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                        </div>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="modal-grid-2col">
                    <div class="form-group">
                        <label>友情ボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="fb_none" name="friendship_min" value="" <?php if(empty($filter_friendship_min)) echo 'checked'; ?>><label for="fb_none">指定なし</label>
                            <input type="radio" id="fb_25" name="friendship_min" value="25" <?php if($filter_friendship_min == 25) echo 'checked'; ?>><label for="fb_25">25以上</label>
                            <input type="radio" id="fb_30" name="friendship_min" value="30" <?php if($filter_friendship_min == 30) echo 'checked'; ?>><label for="fb_30">30以上</label>
                            <input type="radio" id="fb_35" name="friendship_min" value="35" <?php if($filter_friendship_min == 35) echo 'checked'; ?>><label for="fb_35">35以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>レースボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="rb_none" name="race_bonus_min" value="" <?php if(empty($filter_race_bonus_min)) echo 'checked'; ?>><label for="rb_none">指定なし</label>
                            <input type="radio" id="rb_5" name="race_bonus_min" value="5" <?php if($filter_race_bonus_min == 5) echo 'checked'; ?>><label for="rb_5">5以上</label>
                            <input type="radio" id="rb_10" name="race_bonus_min" value="10" <?php if($filter_race_bonus_min == 10) echo 'checked'; ?>><label for="rb_10">10以上</label>
                            <input type="radio" id="rb_15" name="race_bonus_min" value="15" <?php if($filter_race_bonus_min == 15) echo 'checked'; ?>><label for="rb_15">15以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>トレーニング効果UP:</label>
                        <div class="radio-group">
                            <input type="radio" id="te_none" name="training_effect_min" value="" <?php if(empty($filter_training_effect_min)) echo 'checked'; ?>><label for="te_none">指定なし</label>
                            <input type="radio" id="te_5" name="training_effect_min" value="5" <?php if($filter_training_effect_min == 5) echo 'checked'; ?>><label for="te_5">5以上</label>
                            <input type="radio" id="te_10" name="training_effect_min" value="10" <?php if($filter_training_effect_min == 10) echo 'checked'; ?>><label for="te_10">10以上</label>
                            <input type="radio" id="te_15" name="training_effect_min" value="15" <?php if($filter_training_effect_min == 15) echo 'checked'; ?>><label for="te_15">15以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>得意率UP:</label>
                        <div class="radio-group">
                            <input type="radio" id="sr_none" name="specialty_rate_min" value="" <?php if(empty($filter_specialty_rate_min)) echo 'checked'; ?>><label for="sr_none">指定なし</label>
                            <input type="radio" id="sr_35" name="specialty_rate_min" value="35" <?php if($filter_specialty_rate_min == 35) echo 'checked'; ?>><label for="sr_35">35以上</label>
                            <input type="radio" id="sr_50" name="specialty_rate_min" value="50" <?php if($filter_specialty_rate_min == 50) echo 'checked'; ?>><label for="sr_50">50以上</label>
                            <input type="radio" id="sr_65" name="specialty_rate_min" value="65" <?php if($filter_specialty_rate_min == 65) echo 'checked'; ?>><label for="sr_65">65以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>スピードボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="sp_none" name="speed_bonus_min" value="" <?php if(empty($filter_speed_bonus_min)) echo 'checked'; ?>><label for="sp_none">指定なし</label>
                            <input type="radio" id="sp_1" name="speed_bonus_min" value="1" <?php if($filter_speed_bonus_min == 1) echo 'checked'; ?>><label for="sp_1">1以上</label>
                            <input type="radio" id="sp_2" name="speed_bonus_min" value="2" <?php if($filter_speed_bonus_min == 2) echo 'checked'; ?>><label for="sp_2">2以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>スタミナボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="st_none" name="stamina_bonus_min" value="" <?php if(empty($filter_stamina_bonus_min)) echo 'checked'; ?>><label for="st_none">指定なし</label>
                            <input type="radio" id="st_1" name="stamina_bonus_min" value="1" <?php if($filter_stamina_bonus_min == 1) echo 'checked'; ?>><label for="st_1">1以上</label>
                            <input type="radio" id="st_2" name="stamina_bonus_min" value="2" <?php if($filter_stamina_bonus_min == 2) echo 'checked'; ?>><label for="st_2">2以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>パワーボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="pw_none" name="power_bonus_min" value="" <?php if(empty($filter_power_bonus_min)) echo 'checked'; ?>><label for="pw_none">指定なし</label>
                            <input type="radio" id="pw_1" name="power_bonus_min" value="1" <?php if($filter_power_bonus_min == 1) echo 'checked'; ?>><label for="pw_1">1以上</label>
                            <input type="radio" id="pw_2" name="power_bonus_min" value="2" <?php if($filter_power_bonus_min == 2) echo 'checked'; ?>><label for="pw_2">2以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>根性ボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="gt_none" name="guts_bonus_min" value="" <?php if(empty($filter_guts_bonus_min)) echo 'checked'; ?>><label for="gt_none">指定なし</label>
                            <input type="radio" id="gt_1" name="guts_bonus_min" value="1" <?php if($filter_guts_bonus_min == 1) echo 'checked'; ?>><label for="gt_1">1以上</label>
                            <input type="radio" id="gt_2" name="guts_bonus_min" value="2" <?php if($filter_guts_bonus_min == 2) echo 'checked'; ?>><label for="gt_2">2以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>賢さボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="ws_none" name="wisdom_bonus_min" value="" <?php if(empty($filter_wisdom_bonus_min)) echo 'checked'; ?>><label for="ws_none">指定なし</label>
                            <input type="radio" id="ws_1" name="wisdom_bonus_min" value="1" <?php if($filter_wisdom_bonus_min == 1) echo 'checked'; ?>><label for="ws_1">1以上</label>
                            <input type="radio" id="ws_2" name="wisdom_bonus_min" value="2" <?php if($filter_wisdom_bonus_min == 2) echo 'checked'; ?>><label for="ws_2">2以上</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>初期スキルPtボーナス:</label>
                        <div class="radio-group">
                            <input type="radio" id="isp_none" name="initial_skill_point_bonus_min" value="" <?php if(empty($filter_initial_skill_point_bonus_min)) echo 'checked'; ?>><label for="isp_none">指定なし</label>
                            <input type="radio" id="isp_10" name="initial_skill_point_bonus_min" value="10" <?php if($filter_initial_skill_point_bonus_min == 10) echo 'checked'; ?>><label for="isp_10">10以上</label>
                            <input type="radio" id="isp_20" name="initial_skill_point_bonus_min" value="20" <?php if($filter_initial_skill_point_bonus_min == 20) echo 'checked'; ?>><label for="isp_20">20以上</label>
                        </div>
                    </div>
                </div>
                <button type="button" id="apply-advanced-filter" class="back-link" style="width: 100%; margin-top: 15px;">閉じる</button>
            </div>
        </div>
    </form>
</div>
    
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- 変数定義 ---
        const filterForm = document.getElementById('filterForm');
        const cardGrid = document.getElementById('card-grid-container');
        const activeFiltersContainer = document.getElementById('active-filters-container');
        let searchTimer;

        // --- 検索を実行する関数 (AJAX) ---
        function performSearch() {
            cardGrid.classList.add('loading');
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData).toString();
            
            // ページURLを書き換えて、リロードしても状態が維持されるようにする
            const newUrl = window.location.pathname + '?' + params;
            history.pushState(null, '', newUrl);

            fetch('search_cards.php?' + params)
                .then(response => {
                    if (!response.ok) { throw new Error('Network response was not ok'); }
                    return response.json();
                })
                .then(data => {
                    cardGrid.innerHTML = data.card_html;
                    activeFiltersContainer.innerHTML = data.badge_html;
                    cardGrid.classList.remove('loading');
                })
                .catch(error => {
                    cardGrid.innerHTML = '<p>検索結果の読み込みに失敗しました。もう一度お試しください。</p>';
                    activeFiltersContainer.innerHTML = '';
                    cardGrid.classList.remove('loading');
                    console.error('Fetch error:', error);
                });
        }

        // --- イベントリスナー ---
        filterForm.addEventListener('input', function(event) {
            // テキスト入力のイベント
            if (event.target.name === 'search') {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(performSearch, 500);
            }
        });
        filterForm.addEventListener('change', function(event) {
            // セレクトメニューとラジオボタンとチェックボックスのイベント
            if (event.target.tagName === 'SELECT' || event.target.type === 'radio' || event.target.type === 'checkbox') {
                performSearch();
            }
        });
        
        // --- モーダル関連のスクリプト ---
        const modal = document.getElementById('advanced-filter-modal');
        const openBtn = document.getElementById('open-advanced-filter');
        const closeBtn = document.getElementById('close-advanced-filter');
        const applyBtn = document.getElementById('apply-advanced-filter');

        if(modal && openBtn && closeBtn && applyBtn) {
            openBtn.addEventListener('click', function() { modal.classList.add('active'); });
            closeBtn.addEventListener('click', function() { modal.classList.remove('active'); });
            applyBtn.addEventListener('click', function() { modal.classList.remove('active'); });
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
            
            // モーダル内のスキルリスト検索
            const skillSearchInput = document.getElementById('skill-search-in-modal');
            const modalSkillList = document.getElementById('modal-skill-list');
            const allSkillItems = modalSkillList.querySelectorAll('li');

            skillSearchInput.addEventListener('input', function() {
                const keyword = this.value.toLowerCase().trim();
                allSkillItems.forEach(function(item) {
                    const skillName = item.querySelector('.skill-name-container span').textContent.toLowerCase();
                    if (skillName.includes(keyword)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // --- モーダル内のスキル選択時の背景色を変更する機能 ---
        const modalSkillList = document.getElementById('modal-skill-list');
        const modalCheckboxes = modalSkillList.querySelectorAll('input[type="checkbox"]');

        // チェックボックスの状態が変更されたときに背景色をトグルする
        modalCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const li = this.closest('li');
                if (li) {
                    li.classList.toggle('selected', this.checked);
                }
            });
        });

        // モーダルを開いたときに、既にチェックされている項目の背景色を適用する
        openBtn.addEventListener('click', function() {
             modalCheckboxes.forEach(checkbox => {
                const li = checkbox.closest('li');
                if (li) {
                    li.classList.toggle('selected', checkbox.checked);
                }
            });
        });

        // --- サポートカード管理 表示モード切り替え ---
        const supportCardGrid = document.getElementById('card-grid-container');
        const scViewModeDefaultBtn = document.getElementById('sc-view-mode-default');
        const scViewModeSimpleBtn = document.getElementById('sc-view-mode-simple');

        // 対象の要素が存在するページでのみ実行
        if (supportCardGrid && scViewModeDefaultBtn && scViewModeSimpleBtn) {
            
            // モードを適用する関数
            const applyScViewMode = (mode) => {
                if (mode === 'simple') {
                    supportCardGrid.classList.add('image-only-view');
                    scViewModeSimpleBtn.classList.add('active');
                    scViewModeDefaultBtn.classList.remove('active');
                } else {
                    supportCardGrid.classList.remove('image-only-view');
                    scViewModeDefaultBtn.classList.add('active');
                    scViewModeSimpleBtn.classList.remove('active');
                }
            };

            // 「名前あり」ボタンのクリックイベント
            scViewModeDefaultBtn.addEventListener('click', () => {
                localStorage.setItem('supportCardViewMode', 'default');
                applyScViewMode('default');
            });

            // 「画像のみ」ボタンのクリックイベント
            scViewModeSimpleBtn.addEventListener('click', () => {
                localStorage.setItem('supportCardViewMode', 'simple');
                applyScViewMode('simple');
            });

            // ページ読み込み時に保存された設定を適用
            const savedScMode = localStorage.getItem('supportCardViewMode') || 'default';
            applyScViewMode(savedScMode);
        }
        // --- ページの初回読み込み時に一度検索を実行 ---
        performSearch();
    });

    
</script>

<?php include '../templates/footer.php'; ?>
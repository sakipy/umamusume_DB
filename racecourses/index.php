<?php
$page_title = '競馬場一覧';
$current_page = 'racecourses';
$base_path = '../';

// --- フォーム表示用の選択肢を定義 ---
$sort_options = ['id_desc' => '新着順', 'name_asc' => 'あいうえお順'];
$direction_options = ['右回り', '左回り'];
$course_type_options = ['外回り', '内回り'];
$surface_options = ['芝', 'ダート'];

// --- GETパラメータの受け取り（フォームの初期値設定にのみ使用） ---
$search_name = $_GET['search_name'] ?? '';
$search_location = $_GET['search_location'] ?? '';
$filter_directions = $_GET['direction'] ?? [];
$filter_courses = $_GET['course_type'] ?? [];
$filter_surfaces = $_GET['surface'] ?? [];
$sort_key = $_GET['sort'] ?? 'id_desc';
?>
<?php include '../templates/header.php'; ?>

<div class="container full-width">
    <h1>競馬場管理</h1>
    
    <form id="filterForm">
        <div class="controls-container">
            <div class="page-actions">
                <a href="add.php" class="add-link">新しい競馬場を追加する</a>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button type="button" id="open-advanced-filter" class="action-button button-edit">詳細絞り込み</button>
                <div class="active-filters-container" id="active-filters-container"></div>
            </div>
        </div>
        
        <div class="filter-container">
            <div class="filter-group">
                <label for="search_name">競馬場名:</label>
                <input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
            <div class="filter-group">
                <label for="search_location">所在地:</label>
                <input type="text" name="search_location" id="search_location" value="<?php echo htmlspecialchars($search_location); ?>">
            </div>
            <div class="filter-group">
                <label for="sort">並べ替え:</label>
                <select name="sort" id="sort">
                    <?php foreach ($sort_options as $key => $text): ?><option value="<?php echo $key; ?>" <?php if ($key === $sort_key) echo 'selected'; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="back-link">リセット</a>
        </div>

        <div class="racecourse-grid" id="racecourse-grid-container">
            <p>読み込み中...</p>
        </div>

        <div id="advanced-filter-modal" class="modal-overlay">
            <div class="modal-content">
                <button type="button" id="close-advanced-filter" class="modal-close-button">&times;</button>
                <h2>詳細絞り込み</h2>
                <p style="text-align: center; margin-top: -10px; margin-bottom: 20px; font-size: 0.9em; color: #777;">条件はAND検索（すべてを満たす）になります。</p>
                <div class="modal-grid-2col">
                    <div class="form-group">
                        <label>回り方向:</label>
                        <div class="toggle-checkbox-group">
                            <?php foreach($direction_options as $option): ?>
                                <input type="checkbox" name="direction[]" value="<?php echo $option; ?>" id="dir_<?php echo $option; ?>" <?php if(in_array($option, $filter_directions)) echo 'checked'; ?>>
                                <label for="dir_<?php echo $option; ?>"><?php echo $option; ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>コース種別:</label>
                        <div class="toggle-checkbox-group">
                             <?php foreach($course_type_options as $option): ?>
                                <input type="checkbox" name="course_type[]" value="<?php echo $option; ?>" id="course_<?php echo $option; ?>" <?php if(in_array($option, $filter_courses)) echo 'checked'; ?>>
                                <label for="course_<?php echo $option; ?>"><?php echo $option; ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                     <div class="form-group">
                        <label>馬場種別:</label>
                        <div class="toggle-checkbox-group">
                             <?php foreach($surface_options as $option): ?>
                                <input type="checkbox" name="surface[]" value="<?php echo $option; ?>" id="surface_<?php echo $option; ?>" <?php if(in_array($option, $filter_surfaces)) echo 'checked'; ?>>
                                <label for="surface_<?php echo $option; ?>"><?php echo $option; ?></label>
                            <?php endforeach; ?>
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
    const cardGrid = document.getElementById('racecourse-grid-container');
    const activeFiltersContainer = document.getElementById('active-filters-container');
    const allInputs = filterForm.querySelectorAll('input, select');
    let searchTimer;

    function performSearch() {
        cardGrid.classList.add('loading');
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        
        fetch('search_racecourses.php?' + params)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                // search_racecourses.phpがJSONを返すように変更したため、.json()で受け取る
                return response.json(); 
            })
            .then(data => {
                // 受け取ったHTMLを描画
                cardGrid.innerHTML = data.card_html; 
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
                searchTimer = setTimeout(performSearch, 500);
            });
        } else if (input.tagName === 'SELECT' || input.type === 'checkbox') {
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
        applyBtn.addEventListener('click', function() { 
            modal.classList.remove('active'); 
            performSearch(); // モーダルを閉じるときに検索を実行
        });
        modal.addEventListener('click', function(event) { if (event.target === modal) { modal.classList.remove('active'); } });
    }
    
    performSearch(); // 初回読み込み
});
</script>

<?php include '../templates/footer.php'; ?>
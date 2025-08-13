<?php
// ========== ページ設定 ==========
$page_title = 'レース一覧';
$current_page = 'races';
$base_path = '../';

// ========== DB接続とフォーム用データ取得 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("DB接続失敗: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$racecourses = [];
$result = $conn->query("SELECT id, name FROM racecourses ORDER BY name ASC");
while ($row = $result->fetch_assoc()) { $racecourses[] = $row; }
$conn->close();

$grade_options = ['G1', 'G2', 'G3', 'OP', 'Pre-OP', 'L', 'Jpn1', 'Jpn2', 'Jpn3'];
$surface_options = ['芝', 'ダート'];
?>
<?php include '../templates/header.php'; ?>
<div class="container full-width">
    <h1>レース管理</h1>
    <form id="filterForm">
        <div class="controls-container">
            <div class="page-actions">
                <a href="add.php" class="add-link">新しいレースを追加する</a>
            </div>
        </div>
        <div class="filter-container">
            <div class="filter-group">
                <label for="search_name">レース名:</label>
                <input type="text" name="search_name" id="search_name" placeholder="キーワードで検索...">
            </div>
            <div class="filter-group">
                <label for="racecourse_id">競馬場:</label>
                <select name="racecourse_id" id="racecourse_id">
                    <option value="">すべて</option>
                    <?php foreach ($racecourses as $course): ?><option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="grade">グレード:</label>
                <select name="grade" id="grade">
                    <option value="">すべて</option>
                    <?php foreach ($grade_options as $grade): ?><option value="<?php echo $grade; ?>"><?php echo $grade; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="surface">バ場:</label>
                <select name="surface" id="surface">
                    <option value="">すべて</option>
                    <?php foreach ($surface_options as $surface): ?><option value="<?php echo $surface; ?>"><?php echo $surface; ?></option><?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="back-link">リセット</a>
        </div>
        
        <div class="race-grid-container" id="race-grid-container"><p>読み込み中...</p></div>

    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const gridContainer = document.getElementById('race-grid-container');
    let searchTimer;

    function performSearch() {
        gridContainer.classList.add('loading');
        const params = new URLSearchParams(new FormData(filterForm)).toString();
        
        fetch('search_races.php?' + params)
            .then(response => response.json())
            .then(data => {
                gridContainer.innerHTML = data.list_html;
            })
            .catch(error => {
                gridContainer.innerHTML = '<p>検索結果の読み込みに失敗しました。</p>';
                console.error('Fetch error:', error);
            })
            .finally(() => {
                gridContainer.classList.remove('loading');
            });
    }
    filterForm.addEventListener('input', function(e) {
        if (e.target.name === 'search_name') {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(performSearch, 500);
        } else {
            performSearch();
        }
    });
    filterForm.addEventListener('change', performSearch);
    performSearch();
});
</script>
<?php include '../templates/footer.php'; ?>
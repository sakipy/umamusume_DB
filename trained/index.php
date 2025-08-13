<?php
// ========== ページ設定 ==========
$page_title = '育成ウマ娘一覧';
$current_page = 'trained_umamusume';
$base_path = '../';
?>
<?php include '../templates/header.php'; ?>
<div class="container full-width">
    <h1>育成ウマ娘</h1>
    <div class="controls-container">
        <div class="page-actions">
            <a href="add.php" class="add-link">新しく育成したウマ娘を登録する</a>
        </div>
    </div>
    
    <div class="trained-card-grid" id="trained-card-container">
        <p>読み込み中...</p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardContainer = document.getElementById('trained-card-container');

    function loadTrainedList() {
        cardContainer.classList.add('loading');
        fetch('search_trained.php')
            .then(response => response.json())
            .then(data => {
                cardContainer.innerHTML = data.list_html;
            })
            .catch(error => {
                cardContainer.innerHTML = '<p>一覧の読み込みに失敗しました。</p>';
                console.error('Fetch error:', error);
            })
            .finally(() => {
                cardContainer.classList.remove('loading');
            });
    }
    loadTrainedList();
});
</script>
<?php include '../templates/footer.php'; ?>
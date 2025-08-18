<?php
// ========== ページ設定 ==========
$page_title = 'GameWithから育成ウマ娘データを一括インポート';
$current_page = 'characters';
$base_path = '../';

include '../templates/header.php';
?>

<div class="container">
    <h1>GameWithから育成ウマ娘データを一括インポート</h1>

    <div class="instruction-box" style="background: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 20px; color: #713f12;">
        <h3>実行前の注意</h3>
        <ul>
            <li>下の「インポート実行」ボタンを押すと、**新しいタブが開き処理が開始されます。**</li>
            <li>処理には数分〜十分程度かかる場合があります。**新しいタブに完了メッセージが表示されるまで、ページを絶対に閉じないでください。**</li>
            <li>サイトの利用規約によっては、このような自動アクセスが禁止されている場合があります。**自己責任**で実行してください。</li>
            <li>既にデータベースに登録されているウマ娘は、名前が完全に一致する場合、自動的にスキップされます。</li>
            <li>`pokedex`テーブルに登録済みのウマ娘は、名前で照合して自動的に図鑑IDが紐付けられます。</li>
        </ul>
    </div>

    <form action="run_scrape.php" method="POST" target="_blank" id="import-form" style="margin-top: 30px;">
        <button type="submit" id="submit-button" style="width: 100%;">インポート実行</button>
    </form>
    
    <a href="index.php" class="back-link" style="margin-top: 24px;">&laquo; ウマ娘一覧に戻る</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('import-form');
    const button = document.getElementById('submit-button');

    if (form) {
        form.addEventListener('submit', function(e) {
            if (button) {
                button.innerHTML = '処理中...<br><small>（新しいタブでログを確認してください）</small>';
                button.disabled = true;
            }
        });
    }
});
</script>

<?php include '../templates/footer.php'; ?>
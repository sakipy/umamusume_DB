<?php
// ========== ページ設定 ==========
$page_title = 'GameWithからサポートカードを一括インポート';
$current_page = 'support_card';
$base_path = '../';

include '../templates/header.php';
?>

<div class="container">
    <h1 class="page-title"><?php echo $page_title; ?></h1>

    <div class="instruction-box" style="background: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 20px; color: #713f12; margin-bottom: 30px;">
        <h3><i class="fas fa-exclamation-triangle"></i> 実行前の注意</h3>
        <ul>
            <li>下の「インポート実行」ボタンを押すと、<strong>新しいタブが開き処理が開始されます。</strong></li>
            <li>処理には数分かかる場合があります。<strong>新しいタブに完了メッセージが表示されるまで、ページを絶対に閉じないでください。</strong></li>
            <li>サイトの利用規約によっては、このような自動アクセスが禁止されている場合があります。<strong>自己責任</strong>で実行してください。</li>
            <li>既にデータベースに登録されているサポートカードは、名前が完全に一致する場合、自動的にスキップされます。</li>
        </ul>
        
        <div style="background: #e0f2fe; border: 1px solid #0288d1; border-radius: 6px; padding: 16px; margin-top: 16px;">
            <h4 style="color: #01579b; margin-bottom: 12px;"><i class="fas fa-info-circle"></i> 取得する詳細情報</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <div>
                    <strong>基本情報:</strong>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>カード名</li>
                        <li>レアリティ</li>
                        <li>サポートタイプ</li>
                        <li>画像</li>
                    </ul>
                </div>
                <div>
                    <strong>能力値:</strong>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>最大スピード</li>
                        <li>最大スタミナ</li>
                        <li>最大パワー</li>
                        <li>最大根性</li>
                        <li>最大賢さ</li>
                    </ul>
                </div>
                <div>
                    <strong>関連情報:</strong>
                    <ul style="margin: 8px 0; padding-left: 20px;">
                        <li>ウマ娘図鑑との紐づけ</li>
                        <li>所持スキル情報</li>
                        <li>スキルデータベース連携</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <form action="run_scrape.php" method="POST" target="_blank" id="scrape-form">
        <button type="submit" id="submit-button" class="action-button" style="width: 100%; background-color: #e67e22; border-color: #d35400;">
            <i class="fas fa-download"></i> インポート実行
        </button>
    </form>
    
    <a href="index.php" class="back-link" style="margin-top: 24px;">&laquo; サポートカード一覧に戻る</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('scrape-form');
    const button = document.getElementById('submit-button');

    form.addEventListener('submit', function() {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 処理を実行中...';
        
        // 処理が終わった後もボタンを無効のままにしないように、ページが離れる際に元に戻す
        window.addEventListener('beforeunload', function() {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-download"></i> インポート実行';
        });
    });
});
</script>

<?php
include '../templates/footer.php';
?>
<div id="confirm-modal" class="modal-overlay">
        <div class="modal-content confirm-dialog">
            <h3 id="confirm-modal-title">削除の確認</h3>
            <p id="confirm-modal-text"></p>
            <div class="confirm-modal-actions">
                <button type="button" id="confirm-modal-no" class="action-button back-link">いいえ</button>
                <a href="#" id="confirm-modal-yes" class="action-button button-delete">はい、削除します</a>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // --- 画面遷移アニメーション (イベントデリゲーション方式) ---
    document.body.addEventListener('click', function(event) {
        // クリックされた要素、またはその親要素をたどって遷移先を探す
        const linkElement = event.target.closest('a[href]');
        const clickableElement = event.target.closest('[data-href]');

        let targetUrl = null;

        if (clickableElement) {
            // data-href属性を持つ要素を優先
            targetUrl = clickableElement.dataset.href;
        } else if (linkElement) {
            // aタグのリンク先を取得
            const href = linkElement.getAttribute('href');
            // ページ遷移と関係ないリンク（外部リンク、ページ内リンク、削除ボタンなど）は除外
            if (href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('javascript:') && !linkElement.hasAttribute('target') && !linkElement.classList.contains('delete-link')) {
                targetUrl = href;
            }
        }

        // 遷移先のURLが見つかった場合のみアニメーションを実行
        if (targetUrl) {
            event.preventDefault(); // デフォルトのページ遷移をキャンセル
            document.body.classList.add('body-fade-out');
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 300); // CSSのアニメーション時間(0.3秒)と合わせる
        }
    });

    // --- 削除確認モーダル ---
    const modal = document.getElementById('confirm-modal');
    if (modal) {
        const modalText = document.getElementById('confirm-modal-text');
        const yesButton = document.getElementById('confirm-modal-yes');
        const noButton = document.getElementById('confirm-modal-no');

        document.body.addEventListener('click', function(event) {
            const deleteLink = event.target.closest('.delete-link');
            if (deleteLink) {
                event.preventDefault();
                const itemName = deleteLink.dataset.itemName || '';
                const url = deleteLink.href;
                modalText.textContent = '本当に「' + itemName + '」を削除しますか？';
                yesButton.href = url;
                modal.classList.add('active');
            }
        });

        noButton.addEventListener('click', function() { modal.classList.remove('active'); });
        modal.addEventListener('click', function(event) { if (event.target === modal) { modal.classList.remove('active'); } });
    }

    // --- スクロールヘッダーの表示/非表示 ---
    const mainHeader = document.querySelector('.global-header');
    const scrollHeader = document.querySelector('.scrolling-header');

    if (mainHeader && scrollHeader) {
        const triggerHeight = mainHeader.offsetHeight + 200;
        window.addEventListener('scroll', function() {
            if (window.scrollY > triggerHeight) {
                scrollHeader.classList.add('visible');
            } else {
                scrollHeader.classList.remove('visible');
            }
        });
    }
});

</script>
</body>
</html>
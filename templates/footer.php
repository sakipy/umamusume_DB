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
        // --- 画面遷移アニメーション ---
        const links = document.querySelectorAll('a');

        links.forEach(function(link) {
            link.addEventListener('click', function(event) {
                const url = this.href;
                const linkElement = event.target.closest('a');

                // 対象外のリンク（外部リンク、ページ内リンク、削除ボタンなど）は何もしない
                if (!linkElement || !url || url.includes('#') || url.startsWith('mailto:') || url.startsWith('javascript:') || linkElement.target === '_blank' || linkElement.classList.contains('delete-link')) {
                    return;
                }

                // 通常のページ遷移を一旦キャンセル
                event.preventDefault();

                // フェードアウト用のクラスをbodyに追加
                document.body.classList.add('body-fade-out');

                // アニメーションの時間(0.3秒)待ってから、ページを移動
                setTimeout(function() {
                    window.location.href = url;
                }, 300);
            });
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
    /* ========== ここから下を追記 ========== */

        // --- スクロールヘッダーの表示/非表示 ---
        const mainHeader = document.querySelector('.global-header');
        const scrollHeader = document.querySelector('.scrolling-header');

        if (mainHeader && scrollHeader) {
            // メインヘッダーの高さを取得
            const triggerHeight = mainHeader.offsetHeight + 200;

            // スクロールイベントを監視
            window.addEventListener('scroll', function() {
                // 現在のスクロール量を取得
                const scrollY = window.scrollY;

                // スクロール量がメインヘッダーの高さを超えたら
                if (scrollY > triggerHeight) {
                    // スクロールヘッダーに 'visible' クラスを付けて表示
                    scrollHeader.classList.add('visible');
                } else {
                    // そうでなければ 'visible' クラスを外して非表示
                    scrollHeader.classList.remove('visible');
                }
            });
        }
    });

    </script>
</body>
</html>
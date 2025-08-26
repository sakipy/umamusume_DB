<?php
// ========== ページ設定 ==========
$page_title = 'スキル相性診断';
$current_page = 'skill_analyzer'; // 新しいページID
$base_path = '../';

// ========== データベース接続 ==========
$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'umamusume_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { exit; }
$conn->set_charset("utf8mb4");

// ========== データの取得 ==========
// ウマ娘一覧を取得
$characters_result = $conn->query("SELECT id, character_name FROM characters ORDER BY character_name ASC");
$characters = $characters_result->fetch_all(MYSQLI_ASSOC);

// フォームの選択肢を定義
$distance_options = ['短距離', 'マイル', '中距離', '長距離'];
$strategy_options = ['逃げ', '先行', '差し', '追込'];
$surface_options = ['芝', 'ダート'];
$phase_options = ['序盤', '中盤', '終盤', '最終コーナー', 'ラストスパート'];
$position_options = ['上位', '中位', '下位'];


// ========== ヘッダーを読み込む ==========
include '../templates/header.php';
?>

<style>
.analyzer-form-container {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
    color: #4a2e19;
}

.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group select:focus {
    border-color: var(--gold-color, #ffd700);
    outline: none;
}

.form-actions {
    text-align: center;
}

.action-button {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #4a2e19;
    padding: 15px 40px;
    border: none;
    border-radius: 25px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(255,215,0,0.3);
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(255,215,0,0.4);
}

#results-container {
    margin-top: 30px;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #666;
    font-size: 16px;
}

.loading-spinner::before {
    content: "🏇";
    font-size: 2em;
    animation: bounce 1s infinite;
    display: block;
    margin-bottom: 10px;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.error-message {
    color: #d32f2f;
    text-align: center;
    padding: 20px;
    background: #ffebee;
    border: 1px solid #ffcdd2;
    border-radius: 8px;
    margin: 20px 0;
}

.error-message h3 {
    margin-top: 0;
    color: #c62828;
}

.error-message p {
    margin: 10px 0;
}

.skill-results-list {
    display: grid;
    gap: 15px;
}

.skill-item {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: transform 0.2s, box-shadow 0.2s;
}

.skill-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.skill-icon-wrapper {
    width: 64px;
    height: 64px;
    margin-right: 20px;
    flex-shrink: 0;
}

.skill-icon {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 8px;
}

.skill-icon-placeholder {
    width: 100%;
    height: 100%;
    background: #f0f0f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
}

.skill-icon-placeholder::before {
    content: "🎯";
    font-size: 24px;
}

.skill-details {
    flex: 1;
}

.skill-name {
    font-size: 18px;
    font-weight: 700;
    color: #4a2e19;
    margin-bottom: 8px;
}

.skill-description {
    color: #666;
    margin-bottom: 12px;
    line-height: 1.5;
}

.skill-meta {
    display: flex;
    gap: 10px;
}

.skill-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
}

.score-badge {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #4a2e19;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 700;
}

.error-message {
    color: #d32f2f;
    text-align: center;
    padding: 20px;
    background: #ffebee;
    border-radius: 8px;
}
</style>

<div class="container">
    <h1 class="page-title"><?php echo $page_title; ?></h1>

    <div class="analyzer-form-container">
        <form id="skill-analyzer-form" class="analyzer-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="character_id">ウマ娘</label>
                    <select id="character_id" name="character_id">
                        <option value="">指定しない</option>
                        <?php foreach ($characters as $char): ?>
                            <option value="<?php echo $char['id']; ?>"><?php echo htmlspecialchars($char['character_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="distance_type">距離</label>
                    <select id="distance_type" name="distance_type">
                        <option value="">すべて</option>
                        <?php foreach ($distance_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="strategy_type">脚質</label>
                    <select id="strategy_type" name="strategy_type">
                        <option value="">すべて</option>
                        <?php foreach ($strategy_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="surface_type">バ場</label>
                    <select id="surface_type" name="surface_type">
                        <option value="">すべて</option>
                        <?php foreach ($surface_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="condition_phase">レースフェーズ</label>
                    <select id="condition_phase" name="condition_phase">
                        <option value="">すべて</option>
                        <?php foreach ($phase_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="condition_position">順位</label>
                    <select id="condition_position" name="condition_position">
                        <option value="">すべて</option>
                        <?php foreach ($position_options as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="action-button">診断する</button>
            </div>
        </form>
    </div>

    <div id="results-container">
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('skill-analyzer-form');
    const resultsContainer = document.getElementById('results-container');
    const characterSelect = document.getElementById('character_id');
    const distanceSelect = document.getElementById('distance_type');
    const strategySelect = document.getElementById('strategy_type');

    // フォームの送信（検索実行）を行う関数
    function performSearch() {
        resultsContainer.innerHTML = '<div class="loading-spinner">検索中...</div>';
        resultsContainer.style.display = 'block';

        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();

        console.log('Searching with params:', params);

        fetch('search_skill_affinity.php?' + params, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data && data.html) {
                resultsContainer.innerHTML = data.html;
            } else {
                throw new Error('Invalid response format');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            resultsContainer.innerHTML = `
                <div class="error-message">
                    <h3>結果の取得に失敗しました</h3>
                    <p>エラー: ${error.message}</p>
                    <p>ブラウザの開発者ツール（F12）でコンソールを確認してください。</p>
                </div>
            `;
        });
    }

    // フォームが送信されたときのイベント
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    // ウマ娘が選択されたときのイベント
    characterSelect.addEventListener('change', function() {
        const characterId = this.value;
        if (characterId) {
            console.log('Character selected:', characterId);
            
            // キャラクターの詳細情報をAPIから取得
            fetch(`get_character_details.php?id=${characterId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                console.log('Character details response:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Character data received:', data);
                
                if (data.success) {
                    // 取得した最適条件をセレクトボックスに設定
                    distanceSelect.value = data.best_distance;
                    strategySelect.value = data.best_strategy;
                    
                    console.log('Set distance:', data.best_distance);
                    console.log('Set strategy:', data.best_strategy);
                    
                    // 設定後、自動的に診断を実行
                    performSearch();
                } else {
                    console.error('Character details error:', data.error);
                }
            })
            .catch(error => {
                console.error('キャラクター情報の取得に失敗:', error);
                resultsContainer.innerHTML = `
                    <div class="error-message">
                        キャラクター情報の取得に失敗しました: ${error.message}
                    </div>
                `;
            });
        } else {
            // ウマ娘が選択されていない場合は結果をクリア
            resultsContainer.innerHTML = '';
        }
    });
});
</script>

<?php
// ========== フッターを読み込む ==========
include '../templates/footer.php';
?>
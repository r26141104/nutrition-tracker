<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/" class="back">← Dashboard</RouterLink>
      <h1>關於 Nutrition Tracker</h1>
      <p class="subtitle">減重 / 增肌 / 維持身材的個人化營養追蹤系統</p>
    </header>

    <section class="hero card">
      <h2>🎯 這個網站想解決什麼問題</h2>
      <p>
        市面上的營養 app 多半有兩個極端：
        <strong>過度承諾準確度</strong>（給一個誤差很大的數字假裝很準），
        或<strong>太陽春</strong>（只能記錄不能分析）。
      </p>
      <p>
        本系統的核心理念是 <strong>「資料來源透明、可信度分級」</strong>——
        我們誠實告訴使用者每筆資料是怎麼來的（官方標示、AI 估算、使用者輸入），
        以及它的可信度（高 / 中 / 低），讓使用者自己決定要不要相信，
        而不是把任意數字包裝得跟科學一樣。
      </p>
    </section>

    <section class="card">
      <h2>✨ 功能總覽</h2>
      <div class="feature-grid">
        <div class="feature">
          <span class="icon">📝</span>
          <h3>飲食紀錄</h3>
          <p>四餐分桶（早午晚點心）+ 即時熱量 / PFC 計算</p>
        </div>
        <div class="feature">
          <span class="icon">⚖️</span>
          <h3>體重 + 體圍 + 體脂</h3>
          <p>不只記體重，還能追腰圍、體脂率、肌肉量</p>
        </div>
        <div class="feature">
          <span class="icon">🤖</span>
          <h3>AI 估算營養</h3>
          <p>用 Gemini 估算任何食物的熱量與 PFC（誤差 ±20%）</p>
        </div>
        <div class="feature">
          <span class="icon">📷</span>
          <h3>拍照辨識食物</h3>
          <p>Google Cloud Vision 辨識照片中的食物，對應到資料庫</p>
        </div>
        <div class="feature">
          <span class="icon">📍</span>
          <h3>附近餐廳地圖</h3>
          <p>用 GPS 或地址搜尋，OSM 地圖顯示周圍餐廳</p>
        </div>
        <div class="feature">
          <span class="icon">🏪</span>
          <h3>20 家連鎖店菜單</h3>
          <p>麥當勞、星巴克、八方雲集等 260+ 真實品項</p>
        </div>
        <div class="feature">
          <span class="icon">🍽️</span>
          <h3>AI 推測陌生店菜單</h3>
          <p>對非連鎖店，AI 上網搜尋並列出可能品項</p>
        </div>
        <div class="feature">
          <span class="icon">💧</span>
          <h3>水分追蹤 + 連續徽章</h3>
          <p>每日喝水量 + 連續達標天數獎勵</p>
        </div>
        <div class="feature">
          <span class="icon">📊</span>
          <h3>每週報告</h3>
          <p>飲食 + 體重變化整合分析，附保守化建議</p>
        </div>
        <div class="feature">
          <span class="icon">💡</span>
          <h3>6 種個人化分析</h3>
          <p>卡路里調整、營養缺口、蛋白分布、品質評分等</p>
        </div>
        <div class="feature">
          <span class="icon">💪</span>
          <h3>運動 + 餐點建議</h3>
          <p>根據目標自動推薦運動強度與健康餐</p>
        </div>
        <div class="feature">
          <span class="icon">📥</span>
          <h3>CSV / JSON 批次匯入</h3>
          <p>大量自製食物可一次匯入，附驗證</p>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>🛠 技術棧</h2>
      <div class="tech-stack">
        <div class="tech-row">
          <strong>後端</strong>
          <span>Laravel 13.6 / PHP 8.3 / Sanctum 認證 / PostgreSQL（雲端）</span>
        </div>
        <div class="tech-row">
          <strong>前端</strong>
          <span>Vue 3 / TypeScript / Vite / Pinia / Element Plus / Tailwind</span>
        </div>
        <div class="tech-row">
          <strong>地圖</strong>
          <span>Leaflet.js + OpenStreetMap（Overpass + Nominatim）</span>
        </div>
        <div class="tech-row">
          <strong>AI</strong>
          <span>Google Gemini（食物估算 + Search grounding）+ Google Cloud Vision（拍照）</span>
        </div>
        <div class="tech-row">
          <strong>部署</strong>
          <span>Render（Docker + Apache）+ Supabase（PostgreSQL）+ GitHub</span>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>📖 使用流程</h2>
      <ol class="steps">
        <li>
          <strong>1. 註冊帳號</strong>
          <p>用任意 email 與密碼註冊（不需要驗證信，快速上手）</p>
        </li>
        <li>
          <strong>2. 填寫個人資料</strong>
          <p>身高、體重、年齡、性別、活動量、目標（減重/增肌/維持）</p>
        </li>
        <li>
          <strong>3. 系統自動計算每日目標</strong>
          <p>BMR + TDEE + 蛋白質目標，依目標調整熱量</p>
        </li>
        <li>
          <strong>4. 開始記錄</strong>
          <p>每餐選食物、輸入份量；定期記體重；隨時喝水按 +250ml</p>
        </li>
        <li>
          <strong>5. 看分析</strong>
          <p>Dashboard 看今日總覽；每週報告看趨勢；個人化分析看修正建議</p>
        </li>
      </ol>
    </section>

    <section class="card disclaimer">
      <h2>⚠️ 重要免責聲明</h2>
      <ul>
        <li>本系統的營養數據多為估算值，誤差約 ±10-25%，不適合作為醫療或營養諮詢依據</li>
        <li>BMI / TDEE / 卡路里建議都是公式估算，不考慮個人差異（基因、疾病、用藥）</li>
        <li>有特殊飲食需求（糖尿病、腎臟疾病、孕期等）請諮詢醫師或註冊營養師</li>
        <li>本系統由學生開發為大學期末報告作品，非商業產品</li>
      </ul>
    </section>

    <section class="card">
      <h2>👨‍💻 開發者</h2>
      <p>NCKU 學生作品，源碼公開於 GitHub。</p>
      <p>
        <a href="https://github.com/r26141104/nutrition-tracker" target="_blank" rel="noopener" class="link">
          https://github.com/r26141104/nutrition-tracker
        </a>
      </p>
    </section>
  </div>
</template>

<style scoped>
.page { max-width: 800px; margin: 24px auto 64px; padding: 0 24px; }

.page-header { margin-bottom: 24px; }
.back { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back:hover { color: #6366f1; }
.page-header h1 {
  margin: 8px 0 4px;
  font-size: 1.875rem;
  background: linear-gradient(135deg, #6366f1, #ec4899);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  font-weight: 700;
}
.subtitle { color: #64748b; margin: 0; font-size: 0.9375rem; }

.card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 14px;
  padding: 28px;
  margin-bottom: 16px;
}
.card h2 {
  margin: 0 0 16px;
  font-size: 1.25rem;
  color: #0f172a;
  font-weight: 600;
}
.card p {
  color: #475569;
  line-height: 1.7;
  margin: 0 0 12px;
}
.card p:last-child { margin-bottom: 0; }
.card strong { color: #6366f1; }

.hero p {
  font-size: 0.9375rem;
}

/* 功能 grid */
.feature-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
}
.feature {
  background: linear-gradient(135deg, #f5f3ff, #fae8ff);
  border-radius: 10px;
  padding: 16px;
}
.feature .icon { font-size: 1.5rem; display: block; margin-bottom: 8px; }
.feature h3 {
  margin: 0 0 6px;
  font-size: 1rem;
  color: #1e293b;
  font-weight: 600;
}
.feature p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.5;
  color: #64748b;
}

/* 技術棧 */
.tech-stack { display: flex; flex-direction: column; gap: 10px; }
.tech-row {
  display: grid;
  grid-template-columns: 90px 1fr;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
  font-size: 0.875rem;
}
.tech-row:last-child { border-bottom: 0; }
.tech-row strong { color: #6366f1; font-weight: 600; }
.tech-row span { color: #475569; line-height: 1.5; }

/* 步驟 */
.steps {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.steps li {
  background: linear-gradient(135deg, #ecfeff, #f0fdfa);
  border-radius: 10px;
  padding: 14px 18px;
}
.steps strong {
  display: block;
  margin-bottom: 4px;
  color: #0891b2;
  font-size: 1rem;
}
.steps p {
  margin: 0;
  font-size: 0.875rem;
  color: #475569;
}

/* 免責聲明 */
.disclaimer {
  background: #fffbeb;
  border-color: #fef3c7;
}
.disclaimer h2 { color: #92400e; }
.disclaimer ul {
  margin: 0;
  padding-left: 20px;
  color: #78350f;
  font-size: 0.875rem;
  line-height: 1.7;
}
.disclaimer li { margin-bottom: 6px; }

.link {
  color: #6366f1;
  text-decoration: none;
  word-break: break-all;
}
.link:hover { text-decoration: underline; }

@media (max-width: 480px) {
  .tech-row { grid-template-columns: 1fr; gap: 4px; }
}
</style>

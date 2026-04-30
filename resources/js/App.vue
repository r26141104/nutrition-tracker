<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-bg">
    <RouterView />
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
  margin: 0;
  font-family: 'Inter', 'Noto Sans TC', -apple-system, 'Segoe UI', system-ui, sans-serif;
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  letter-spacing: -0.01em;
  min-height: 100vh;
  background-color: #f5f7fb;
}

/* 專業 SaaS 背景：頂部強烈品牌色 band + 對角線細紋 + 多層 radial 光暈 */
.app-bg {
  min-height: 100vh;
  position: relative;
  background:
    /* 頂部強烈品牌色 band（紫到透明） */
    linear-gradient(180deg, rgba(67, 56, 202, 0.07) 0%, transparent 280px),
    /* 大塊光暈 */
    radial-gradient(ellipse 1200px 500px at 0% 0%, rgba(99, 102, 241, 0.18) 0%, transparent 50%),
    radial-gradient(ellipse 900px 500px at 100% 0%, rgba(168, 85, 247, 0.14) 0%, transparent 50%),
    radial-gradient(ellipse 700px 400px at 50% 100%, rgba(56, 189, 248, 0.07) 0%, transparent 60%),
    /* 對角細紋網格（45 度線條，更精緻） */
    repeating-linear-gradient(45deg,
      transparent 0px,
      transparent 38px,
      rgba(99, 102, 241, 0.025) 38px,
      rgba(99, 102, 241, 0.025) 39px
    ),
    repeating-linear-gradient(-45deg,
      transparent 0px,
      transparent 38px,
      rgba(99, 102, 241, 0.025) 38px,
      rgba(99, 102, 241, 0.025) 39px
    );
  background-attachment: fixed;
}

/* 頂部 1px 品牌色高光線 */
.app-bg::before {
  content: '';
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg,
    transparent 0%,
    rgba(99, 102, 241, 0.6) 30%,
    rgba(168, 85, 247, 0.6) 70%,
    transparent 100%
  );
  z-index: 1000;
  box-shadow: 0 0 12px rgba(99, 102, 241, 0.4);
}

.app-bg > * { position: relative; z-index: 1; }

/* === 滾動條 === */
::-webkit-scrollbar { width: 12px; height: 12px; }
::-webkit-scrollbar-track { background: rgba(241, 245, 249, 0.3); }
::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #818cf8, #6366f1);
  border-radius: 12px;
  border: 3px solid rgba(241, 245, 249, 0.3);
  background-clip: padding-box;
}
::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #6366f1, #4f46e5);
  background-clip: padding-box;
  border: 3px solid rgba(241, 245, 249, 0.3);
}

/* === 選取 / 焦點 === */
::selection { background: rgba(79, 70, 229, 0.2); color: #1e293b; }
:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; border-radius: 4px; }

/* === 標題字級提升（letter-spacing + weight） === */
h1, h2, h3, h4, h5, h6 { letter-spacing: -0.025em; font-weight: 700; }

/* === 卡片：更銳利的多層陰影 + 頂部 accent 漸層線 === */
.card {
  background: #ffffff !important;
  position: relative;
  border-radius: 14px !important;
  border: 1px solid rgba(226, 232, 240, 0.8) !important;
  box-shadow:
    0 0 0 1px rgba(15, 23, 42, 0.04),
    0 2px 4px rgba(15, 23, 42, 0.04),
    0 8px 24px rgba(15, 23, 42, 0.06) !important;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}
/* 卡片頂部 1px 漸層線（會在 hover 時更亮） */
.card::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg,
    transparent 0%,
    rgba(99, 102, 241, 0.3) 50%,
    transparent 100%
  );
  opacity: 0.7;
  transition: opacity 0.25s;
  pointer-events: none;
}
.card:hover {
  border-color: rgba(165, 180, 252, 0.6) !important;
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.08),
    0 2px 4px rgba(15, 23, 42, 0.04),
    0 16px 40px rgba(99, 102, 241, 0.12) !important;
  transform: translateY(-2px);
}
.card:hover::after { opacity: 1; }

/* === 按鈕互動 === */
button:not(:disabled), a.btn-primary, a.btn-secondary {
  transition: all 0.18s ease;
}
button:not(:disabled):hover, a.btn-primary:hover, a.btn-secondary:hover {
  transform: translateY(-1px);
}

/* === Element Plus === */
.el-button { font-family: inherit !important; border-radius: 8px !important; font-weight: 500 !important; }
.el-card {
  border-radius: 14px !important;
  border: 1px solid rgba(226, 232, 240, 0.8) !important;
  box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.04), 0 2px 4px rgba(15, 23, 42, 0.04), 0 8px 24px rgba(15, 23, 42, 0.06) !important;
}

/* === 響應式 === */
@media (max-width: 480px) {
  body { letter-spacing: 0; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>

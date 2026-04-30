<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-bg">
    <!-- 背景裝飾：柔和的色塊 blur，營造深度感 -->
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>
    <RouterView />
  </div>
</template>

<style>
/* ==================== 全域字型 ==================== */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
  margin: 0;
  font-family: 'Inter', 'Noto Sans TC', -apple-system, 'Segoe UI', system-ui, sans-serif;
  color: #1e293b;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
  /* 漸層背景：薄荷藍 → 淡紫 → 蜜桃 */
  background:
    linear-gradient(135deg,
      #f0fdfa 0%,
      #ecfeff 25%,
      #faf5ff 50%,
      #fef3f2 75%,
      #fff7ed 100%
    );
  background-attachment: fixed;
  min-height: 100vh;
}

/* ==================== 背景：app-bg + 飄浮色塊 ==================== */
.app-bg {
  min-height: 100vh;
  position: relative;
  overflow: hidden;
}

.bg-orb {
  position: fixed;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.5;
  pointer-events: none;
  z-index: 0;
  animation: float 20s ease-in-out infinite;
}
.bg-orb-1 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, #a7f3d0 0%, transparent 70%);
  top: -100px; left: -100px;
  animation-delay: 0s;
}
.bg-orb-2 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, #c4b5fd 0%, transparent 70%);
  top: 30%; right: -150px;
  animation-delay: -7s;
}
.bg-orb-3 {
  width: 350px; height: 350px;
  background: radial-gradient(circle, #fcd5ce 0%, transparent 70%);
  bottom: -100px; left: 20%;
  animation-delay: -14s;
}

@keyframes float {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33%      { transform: translate(40px, -30px) scale(1.05); }
  66%      { transform: translate(-30px, 30px) scale(0.95); }
}

/* 確保內容在 orb 之上 */
.app-bg > :not(.bg-orb) {
  position: relative;
  z-index: 1;
}

/* ==================== 全域 scrollbar 美化 ==================== */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
::-webkit-scrollbar-track {
  background: rgba(241, 245, 249, 0.5);
}
::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #a5b4fc, #818cf8);
  border-radius: 10px;
  border: 2px solid rgba(241, 245, 249, 0.5);
}
::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #818cf8, #6366f1);
}

/* ==================== 全域：選取文字顏色 ==================== */
::selection {
  background: rgba(99, 102, 241, 0.2);
  color: #1e1b4b;
}

/* ==================== 全域焦點 outline 美化 ==================== */
:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 2px;
  border-radius: 4px;
}

/* ==================== 全域：所有 card / 內容區塊統一升級 ==================== */
/* 各頁面內現有的 .card 類別會自動繼承這些樣式 */
.card {
  background: rgba(255, 255, 255, 0.85) !important;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(226, 232, 240, 0.7) !important;
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.04),
    0 4px 12px rgba(0, 0, 0, 0.04),
    0 12px 24px rgba(99, 102, 241, 0.04) !important;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.card:hover {
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.04),
    0 8px 20px rgba(0, 0, 0, 0.06),
    0 20px 40px rgba(99, 102, 241, 0.08) !important;
}

/* ==================== 按鈕 hover 效果統一 ==================== */
button:not(:disabled),
a.btn-primary, a.btn-secondary {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
button:not(:disabled):hover,
a.btn-primary:hover, a.btn-secondary:hover {
  transform: translateY(-1px);
}

/* ==================== Element Plus 微調 ==================== */
.el-button {
  font-family: inherit !important;
  border-radius: 8px !important;
}
.el-card {
  border-radius: 12px !important;
  border: 1px solid rgba(226, 232, 240, 0.7) !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.04) !important;
}

/* ==================== 響應式：手機關掉 orb（耗效能） ==================== */
@media (max-width: 480px) {
  .bg-orb {
    display: none;
  }
}

/* ==================== 減動效偏好 ==================== */
@media (prefers-reduced-motion: reduce) {
  .bg-orb { animation: none; }
  * { transition: none !important; }
}

/* =====
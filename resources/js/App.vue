<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-bg">
    <RouterView />
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
  margin: 0;
  font-family: 'Inter', 'Noto Sans TC', -apple-system, 'Segoe UI', system-ui, sans-serif;
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  background-color: #f8fafc;
  min-height: 100vh;
}

/* 專業背景：純淨白底 + 極淡點陣網格（科技感、低調） */
.app-bg {
  min-height: 100vh;
  position: relative;
  background-image:
    radial-gradient(circle at 1px 1px, rgba(99, 102, 241, 0.08) 1px, transparent 0);
  background-size: 24px 24px;
  background-attachment: fixed;
}

/* 上方淡淡的漸層帶（讓頁面有層次但不花俏） */
.app-bg::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 320px;
  background: linear-gradient(180deg,
    rgba(238, 242, 255, 0.6) 0%,
    rgba(238, 242, 255, 0) 100%
  );
  pointer-events: none;
  z-index: 0;
}

.app-bg > * {
  position: relative;
  z-index: 1;
}

/* 滾動條（細緻不搶戲） */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* 選取文字 */
::selection { background: rgba(79, 70, 229, 0.18); color: #1e293b; }

/* 焦點 outline */
:focus-visible {
  outline: 2px solid #4f46e5;
  outline-offset: 2px;
  border-radius: 4px;
}

/* 卡片：乾淨白底 + 細緻陰影（無毛玻璃，更專業） */
.card {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 4px 12px rgba(15, 23, 42, 0.04) !important;
  transition: box-shadow 0.2s, border-color 0.2s;
}
.card:hover {
  border-color: #cbd5e1 !important;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 8px 24px rgba(15, 23, 42, 0.08) !important;
}

/* 按鈕 hover */
button:not(:disabled),
a.btn-primary, a.btn-secondary {
  transition: all 0.18s ease;
}
button:not(:disabled):hover,
a.btn-primary:hover, a.btn-secondary:hover {
  transform: translateY(-1px);
}

/* Element Plus */
.el-button {
  font-family: inherit !important;
  border-radius: 8px !important;
}
.el-card {
  border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.04) !important;
}

@media (max-width: 480px) {
  .app-bg { background-size: 18px 18px; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}

/* ==================== 暗色主題（深色簡潔，無花俏） ==================== */
[data-theme="dark"] body {
  background-color: #0f172a;
  color: #e2e8f0;
}

[data-theme="dark"] .app-bg {
  background-image:
    radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.10) 1px, transparent 0);
}

[data-theme="dark"] .app-bg::before {
  background: linear-gradient(180deg,
    rgba(30, 41, 59, 0.5) 0%,
    rgba(30, 41, 59, 0) 100%
  );
}

[data-theme="dark"] .card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}
[data-theme="dark"] .card:hover {
  border-color: #475569 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2), 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}

[data-theme="dark"] h1,
[data-theme="dark"] h2,
[data-theme="dark"] h3,
[data-theme="dark"] h4 { color: #f1f5f9; }

[data-theme="dark"] p,
[data-theme="dark"] span,
[data-theme="dark"] li,
[data-theme="dark"] label { color: #cbd5e1; }

[data-theme="dark"] small { color: #94a3b8; }

[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}
[data-theme="dark"] input::placeholder,
[data-theme="dark"] textarea::placeholder { color: #64748b; }

[data-theme="dark"] .nav-pill {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .nav-pill:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #e0e7ff !important;
}

[data-theme="dark"] ::-webkit-scrollbar-thumb { background: #475569; }
[data-theme="dark"] ::-webkit-scrollbar-thumb:hover { background: #64748b; }

[data-theme="dark"] .el-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #e2e8f0;
}
[data-theme="dark"] .el-progress__text { color: #e2e8f0 !important; }
[data-theme="dark"] button:disabled { color: #64748b; }
</style>

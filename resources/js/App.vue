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
  min-height: 100vh;
  background-color: #fafbff;
}

.app-bg {
  min-height: 100vh;
  position: relative;
  background:
    radial-gradient(ellipse 800px 600px at 10% 0%, rgba(99, 102, 241, 0.10) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 95% 25%, rgba(168, 85, 247, 0.08) 0%, transparent 55%),
    radial-gradient(ellipse 600px 600px at 50% 100%, rgba(56, 189, 248, 0.06) 0%, transparent 60%),
    radial-gradient(circle at 1px 1px, rgba(99, 102, 241, 0.10) 1px, transparent 0);
  background-size: 100% 100%, 100% 100%, 100% 100%, 28px 28px;
  background-attachment: fixed;
}

.app-bg::before {
  content: '';
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.4), transparent);
  z-index: 1000;
}

.app-bg > * { position: relative; z-index: 1; }

::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

::selection { background: rgba(79, 70, 229, 0.18); color: #1e293b; }
:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; border-radius: 4px; }

.card {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px rgba(15, 23, 42, 0.06) !important;
  transition: all 0.2s ease;
}
.card:hover {
  border-color: #c7d2fe !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px rgba(99, 102, 241, 0.08) !important;
  transform: translateY(-1px);
}

button:not(:disabled), a.btn-primary, a.btn-secondary {
  transition: all 0.18s ease;
}
button:not(:disabled):hover, a.btn-primary:hover, a.btn-secondary:hover {
  transform: translateY(-1px);
}

.el-button { font-family: inherit !important; border-radius: 8px !important; }
.el-card {
  border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px rgba(15, 23, 42, 0.06) !important;
}

@media (max-width: 480px) {
  .app-bg { background-size: 100% 100%, 100% 100%, 100% 100%, 20px 20px; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}

[data-theme="dark"] body {
  background-color: #0a0e1a;
  color: #e2e8f0;
}

[data-theme="dark"] .app-bg {
  background:
    radial-gradient(ellipse 800px 600px at 10% 0%, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 95% 25%, rgba(168, 85, 247, 0.15) 0%, transparent 55%),
    radial-gradient(ellipse 600px 600px at 50% 100%, rgba(34, 211, 238, 0.10) 0%, transparent 60%),
    radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.08) 1px, transparent 0);
}

[data-theme="dark"] .card {
  background: #111827 !important;
  border-color: #1f2937 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 4px 16px rgba(0, 0, 0, 0.3) !important;
}
[data-theme="dark"] .card:hover {
  border-color: #4338ca !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 12px 32px rgba(99, 102, 241, 0.25) !important;
}

[data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4 { color: #f1f5f9; }
[data-theme="dark"] p, [data-theme="dark"] span, [data-theme="dark"] li, [data-theme="dark"] label { color: #cbd5e1; }
[data-theme="dark"] small { color: #94a3b8; }

[data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea {
  background: #0a0e1a !important;
  border-color: #1f2937 !important;
  color: #f1f5f9 !important;
}
[data-theme="dark"] input::placeholder, [data-theme="dark"] textarea::placeholder { color: #64748b; }

[data-theme="dark"] .nav-pill {
  background: #111827 !important;
  border-color: #1f2937 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .nav-pill:hover {
  background: #1f2937 !important;
  border-color: #4338ca !important;
  color: #c7d2fe !important;
}

[data-theme="dark"] ::-webkit-scrollbar-thumb { background: #334155; }
[data-theme="dark"] ::-webkit-scrollbar-thumb:hover { background: #475569; }

[data-theme="dark"] .el-card {
  background: #111827 !important;
  border-color: #1f2937 !important;
  color: #e2e8f0;
}
[data-theme="dark"] .el-progress__text { color: #e2e8f0 !important; }
[data-theme="dark"] button:disabled { color: #64748b; }
</style>

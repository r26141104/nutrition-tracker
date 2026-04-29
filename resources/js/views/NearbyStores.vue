<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, shallowRef } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { storeService } from '@/services/storeService';
import {
  nearbyStoreService,
  getCurrentPosition,
  AMENITY_LABEL,
  type NearbyStore,
} from '@/services/nearbyStoreService';
import { geocodingService } from '@/services/geocodingService';

// Leaflet 從 CDN 動態載入（避免 npm install 額外步驟）
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const L: any;

const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

// === 狀態 ===
const status = ref<'init' | 'locating' | 'querying' | 'ok' | 'error'>('init');
const errorMsg = ref('');
const myLat = ref<number | null>(null);
const myLon = ref<number | null>(null);
const stores = ref<NearbyStore[]>([]);
const meta = ref<{ count: number; radius: number; note: string } | null>(null);
const mapRef = ref<HTMLDivElement | null>(null);

// 使用 shallowRef 避免 Vue 把 Leaflet 內部物件深度 reactive（會炸）
const mapInstance = shallowRef<unknown>(null);

// 半徑選擇
const radius = ref<number>(1000);

// === 地址搜尋狀態 ===
const addressInput = ref<string>('');
const geocoding = ref<boolean>(false);
// 目前定位來源（給使用者看的標籤）
const locationLabel = ref<string>('我的位置');
const locationMode = ref<'gps' | 'address'>('gps');

const router = useRouter();
// 哪個店家正在生成菜單（給按鈕顯示 loading）
const generatingForOsmId = ref<number | null>(null);

onMounted(async () => {
  await loadLeaflet();
  await runFlow();
});

onBeforeUnmount(() => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const m = mapInstance.value as any;
  if (m && typeof m.remove === 'function') m.remove();
});

async function runFlow(): Promise<void> {
  errorMsg.value = '';
  status.value = 'locating';

  try {
    const pos = await getCurrentPosition();
    myLat.value = pos.lat;
    myLon.value = pos.lon;
  } catch (e) {
    status.value = 'error';
    errorMsg.value = e instanceof Error ? e.message : '無法取得位置';
    return;
  }

  status.value = 'querying';
  try {
    const res = await nearbyStoreService.find(myLat.value!, myLon.value!, radius.value);
    stores.value = res.data;
    meta.value = res.meta;
    status.value = 'ok';
    // 等 DOM 更新完再畫地圖
    setTimeout(initMap, 50);
  } catch (e) {
    status.value = 'error';
    errorMsg.value = e instanceof Error ? e.message : '查詢附近店家失敗';
  }
}

/**
 * 使用者輸入地址 → 搜尋
 * 流程：geocode → 取得 lat/lon → 找附近店家 → 重畫地圖
 */
async function onSearchAddress(): Promise<void> {
  const q = addressInput.value.trim();
  if (q === '') {
    errorMsg.value = '請輸入地址或地名';
    return;
  }
  errorMsg.value = '';
  geocoding.value = true;

  try {
    const geo = await geocodingService.search(q);
    myLat.value = geo.lat;
    myLon.value = geo.lon;
    locationMode.value = 'address';
    // 顯示乾淨一點：只取前 40 個字
    const dispName = geo.display_name.length > 40
      ? geo.display_name.slice(0, 40) + '…'
      : geo.display_name;
    locationLabel.value = dispName;
  } catch (e) {
    geocoding.value = false;
    // 422 包裝過的錯誤訊息會在 axios error.response.data.message
    // 為了簡化，這裡 fallback 到 instanceof Error
    const errStr = e && typeof e === 'object' && 'response' in e
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ? ((e as any).response?.data?.message ?? '地址查詢失敗')
      : e instanceof Error ? e.message : '地址查詢失敗';
    errorMsg.value = errStr;
    return;
  }

  // 用新位置查附近
  status.value = 'querying';
  try {
    const res = await nearbyStoreService.find(myLat.value!, myLon.value!, radius.value);
    stores.value = res.data;
    meta.value = res.meta;
    status.value = 'ok';
    setTimeout(initMap, 50);
  } catch (e) {
    status.value = 'error';
    errorMsg.value = e instanceof Error ? e.message : '查詢附近店家失敗';
  } finally {
    geocoding.value = false;
  }
}

/**
 * 點非連鎖店的「推測菜單」→ 呼叫 AI 生成菜單，跳轉到 /stores/{id}
 */
async function onGenerateMenu(s: NearbyStore): Promise<void> {
  if (generatingForOsmId.value !== null) return; // 防止連點
  generatingForOsmId.value = s.osm_id;
  errorMsg.value = '';
  try {
    const meta = await storeService.generateMenu(s.name);
    // 跳到既有的菜單頁面
    await router.push({ name: 'store-detail', params: { id: meta.store_id } });
  } catch (e) {
    const errStr = e && typeof e === 'object' && 'response' in e
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ? ((e as any).response?.data?.message ?? 'AI 推測菜單失敗')
      : e instanceof Error ? e.message : 'AI 推測菜單失敗';
    errorMsg.value = errStr;
  } finally {
    generatingForOsmId.value = null;
  }
}

/** 回到 GPS 我的位置 */
async function onBackToGps(): Promise<void> {
  addressInput.value = '';
  errorMsg.value = '';
  locationMode.value = 'gps';
  locationLabel.value = '我的位置';
  await runFlow();
}

async function changeRadius(newRadius: number): Promise<void> {
  if (myLat.value === null || myLon.value === null) return;
  radius.value = newRadius;
  status.value = 'querying';
  try {
    const res = await nearbyStoreService.find(myLat.value, myLon.value, newRadius);
    stores.value = res.data;
    meta.value = res.meta;
    status.value = 'ok';
    setTimeout(initMap, 50);
  } catch (e) {
    status.value = 'error';
    errorMsg.value = e instanceof Error ? e.message : '查詢失敗';
  }
}

function loadLeaflet(): Promise<void> {
  return new Promise((resolve) => {
    if (typeof L !== 'undefined') {
      resolve();
      return;
    }
    // CSS
    if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = LEAFLET_CSS;
      document.head.appendChild(link);
    }
    // JS
    const existing = document.querySelector(`script[src="${LEAFLET_JS}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve());
      return;
    }
    const script = document.createElement('script');
    script.src = LEAFLET_JS;
    script.onload = () => resolve();
    script.onerror = () => resolve(); // 失敗也繼續，改成只顯示列表
    document.head.appendChild(script);
  });
}

function initMap(): void {
  if (!mapRef.value || typeof L === 'undefined') return;
  if (myLat.value === null || myLon.value === null) return;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const m = mapInstance.value as any;
  if (m && typeof m.remove === 'function') m.remove();

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const map = (L as any).map(mapRef.value).setView([myLat.value, myLon.value], 16);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (L as any).tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map);

  // 中心位置：藍點（GPS 或 搜尋的地址）
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (L as any).circleMarker([myLat.value, myLon.value], {
    radius: 8,
    color: '#0ea5e9',
    fillColor: '#0ea5e9',
    fillOpacity: 0.9,
  }).addTo(map).bindPopup(locationLabel.value);

  // 所有店家
  for (const s of stores.value) {
    const isMatched = s.matched_store !== null;
    const color = isMatched ? '#7c3aed' : '#64748b';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (L as any).circleMarker([s.lat, s.lon], {
      radius: 7,
      color,
      fillColor: color,
      fillOpacity: 0.7,
      weight: 2,
    })
      .addTo(map)
      .bindPopup(
        `<b>${s.name}</b><br>${AMENITY_LABEL[s.amenity] ?? s.amenity}・${s.distance_m} m`
        + (isMatched ? `<br>✨ 已收錄菜單` : ''),
      );
  }

  mapInstance.value = map;
}

function retryLocation(): void {
  void runFlow();
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/" class="back">← Dashboard</RouterLink>
      <h1>📍 附近餐廳</h1>
      <p class="subtitle">用 GPS 或搜尋任意地址，找出附近的餐廳與菜單。</p>
    </header>

    <!-- 地址搜尋欄 -->
    <div class="address-search">
      <input
        v-model="addressInput"
        type="text"
        class="address-input"
        placeholder="輸入地址、地名（如：台北車站、信義誠品、成大、中正路100號）"
        @keyup.enter="onSearchAddress"
      />
      <button
        type="button"
        class="btn-search"
        :disabled="geocoding || addressInput.trim() === ''"
        @click="onSearchAddress"
      >{{ geocoding ? '搜尋中…' : '🔍 搜尋此地址' }}</button>
      <button
        v-if="locationMode === 'address'"
        type="button"
        class="btn-gps"
        @click="onBackToGps"
        title="回到我的 GPS 位置"
      >📍 我的位置</button>
    </div>
    <p v-if="locationMode === 'address' && status === 'ok'" class="location-label">
      正在顯示：<strong>{{ locationLabel }}</strong> 周圍的店家
    </p>

    <!-- 載入中/錯誤狀態 -->
    <div v-if="status === 'init' || status === 'locating'" class="state-card">
      <span class="spinner"></span>
      <span>正在取得您的位置（請允許瀏覽器存取定位權限）…</span>
    </div>

    <div v-else-if="status === 'querying'" class="state-card">
      <span class="spinner"></span>
      <span>正在查詢附近的餐廳…</span>
    </div>

    <div v-else-if="status === 'error'" class="state-card error">
      <p>{{ errorMsg }}</p>
      <button class="btn-primary" @click="retryLocation">再試一次</button>
    </div>

    <!-- 主畫面 -->
    <template v-else>
      <!-- 控制列 -->
      <div class="controls">
        <span class="label">搜尋半徑：</span>
        <button
          v-for="r in [500, 1000, 2000, 3000]"
          :key="r"
          class="radius-btn"
          :class="{ active: radius === r }"
          @click="changeRadius(r)"
        >
          {{ r >= 1000 ? `${r / 1000} km` : `${r} m` }}
        </button>
      </div>

      <!-- 地圖 -->
      <div class="map-wrap">
        <div ref="mapRef" class="map"></div>
        <p class="map-legend">
          <span class="dot dot-self"></span>{{ locationMode === 'address' ? '搜尋位置' : '我的位置' }}
          <span class="dot dot-matched"></span>有完整菜單
          <span class="dot dot-other"></span>其他餐廳
        </p>
      </div>

      <!-- 結果列表 -->
      <div class="results-header">
        <strong>{{ stores.length }} 間餐廳</strong>
        <small v-if="meta">{{ meta.note }}</small>
      </div>

      <ul v-if="stores.length > 0" class="store-list">
        <li v-for="s in stores" :key="s.osm_id" class="store-row">
          <div class="store-main">
            <div class="store-name-line">
              <span v-if="s.matched_store?.logo_emoji" class="store-emoji">{{ s.matched_store.logo_emoji }}</span>
              <strong>{{ s.name }}</strong>
              <span v-if="s.matched_store" class="badge badge-matched">✨ 已收錄菜單</span>
            </div>
            <div class="store-meta">
              <span>{{ AMENITY_LABEL[s.amenity] ?? s.amenity }}</span>
              <span>·</span>
              <span>{{ s.distance_m }} m</span>
              <span v-if="s.nearby_branch_count > 1" class="branch-note">
                · 附近還有 {{ s.nearby_branch_count - 1 }} 家分店
              </span>
            </div>
          </div>
          <div class="store-action">
            <RouterLink
              v-if="s.matched_store"
              :to="`/stores/${s.matched_store.id}`"
              class="btn-primary"
            >查看菜單</RouterLink>
            <button
              v-else
              type="button"
              class="btn-ai"
              :disabled="generatingForOsmId !== null"
              @click="onGenerateMenu(s)"
            >
              {{ generatingForOsmId === s.osm_id
                ? 'AI 推測中…'
                : '🤖 推測菜單' }}
            </button>
          </div>
        </li>
      </ul>

      <p v-else class="empty">這個範圍內沒有找到餐廳，試試擴大半徑。</p>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 920px; margin: 24px auto 64px; padding: 0 24px; }
.page-header { margin-bottom: 20px; }
.back { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back:hover { color: #0ea5e9; }
.page-header h1 { margin: 8px 0 4px; font-size: 1.75rem; color: #0f172a; }
.subtitle { color: #64748b; margin: 0; font-size: 0.9375rem; }

/* 地址搜尋欄 */
.address-search {
  display: flex; gap: 8px;
  margin: 16px 0 8px;
  flex-wrap: wrap;
}
.address-input {
  flex: 1; min-width: 240px;
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9375rem;
  background: white;
}
.address-input:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}
.btn-search {
  background: #0ea5e9; color: white; border: 0;
  padding: 0 18px; border-radius: 8px;
  font-size: 0.9375rem; cursor: pointer;
  white-space: nowrap;
}
.btn-search:hover:not(:disabled) { background: #0284c7; }
.btn-search:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-gps {
  background: white; color: #475569;
  border: 1px solid #cbd5e1;
  padding: 0 14px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  white-space: nowrap;
}
.btn-gps:hover { background: #f1f5f9; }
.location-label {
  margin: 0 0 12px;
  font-size: 0.8125rem;
  color: #6d28d9;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  padding: 6px 12px;
  border-radius: 6px;
}
.location-label strong { color: #6d28d9; }

.state-card {
  display: flex; align-items: center; gap: 12px;
  padding: 24px; background: white;
  border: 1px solid #e2e8f0; border-radius: 12px;
  color: #475569; margin-top: 24px;
}
.state-card.error { flex-direction: column; align-items: flex-start; gap: 12px; color: #b91c1c; }
.state-card.error p { margin: 0; }

.spinner {
  width: 18px; height: 18px;
  border: 3px solid #cbd5e1;
  border-top-color: #0ea5e9;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.controls { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.controls .label { font-size: 0.875rem; color: #475569; }
.radius-btn {
  border: 1px solid #cbd5e1; background: white;
  padding: 6px 12px; border-radius: 999px;
  font-size: 0.8125rem; color: #475569;
  cursor: pointer;
}
.radius-btn:hover { background: #f1f5f9; }
.radius-btn.active { background: #0ea5e9; color: white; border-color: #0ea5e9; }

.map-wrap {
  border-radius: 12px; overflow: hidden;
  border: 1px solid #e2e8f0;
  margin-bottom: 12px;
}
.map { height: 380px; width: 100%; }
.map-legend {
  display: flex; gap: 12px; flex-wrap: wrap;
  margin: 8px 0 0; padding: 8px 12px;
  font-size: 0.75rem; color: #64748b;
}
.map-legend .dot {
  width: 10px; height: 10px;
  border-radius: 50%; display: inline-block;
  margin-right: 4px; vertical-align: middle;
}
.dot-self    { background: #0ea5e9; }
.dot-matched { background: #7c3aed; }
.dot-other   { background: #64748b; }

.results-header {
  display: flex; align-items: baseline; justify-content: space-between;
  gap: 12px; flex-wrap: wrap;
  margin: 24px 0 8px;
}
.results-header small { color: #94a3b8; font-size: 0.75rem; }

.store-list { list-style: none; margin: 0; padding: 0; }
.store-row {
  display: flex; gap: 12px; align-items: center;
  padding: 14px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 8px;
}
.store-main { flex: 1; min-width: 0; }
.store-name-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.store-emoji { font-size: 1.25rem; }
.store-name-line strong { color: #0f172a; font-size: 1rem; }
.store-meta { display: flex; gap: 6px; align-items: center; font-size: 0.8125rem; color: #64748b; margin-top: 2px; flex-wrap: wrap; }
.branch-note { color: #94a3b8; font-size: 0.75rem; }

.badge {
  font-size: 0.6875rem; padding: 2px 8px; border-radius: 999px;
  background: #ede9fe; color: #6d28d9; font-weight: 500;
}

.btn-primary {
  background: #0ea5e9; color: white; border: 0;
  padding: 8px 14px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  text-decoration: none;
}
.btn-primary:hover { background: #0284c7; }

.btn-ai {
  background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd;
  padding: 7px 12px; border-radius: 8px;
  font-size: 0.8125rem; cursor: pointer;
  text-decoration: none; font-weight: 500;
  white-space: nowrap;
}
.btn-ai:hover:not(:disabled) { background: #ddd6fe; }
.btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }

.empty {
  padding: 32px; text-align: center; color: #94a3b8;
  background: white; border: 1px dashed #cbd5e1; border-radius: 10px;
}
</style>

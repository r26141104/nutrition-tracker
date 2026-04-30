import http from './http';

/** 從 OSM 來的附近店家（可能 cross-ref 到我們的 Store） */
export interface NearbyStore {
  osm_id: number;
  name: string;
  /** OSM amenity 類型 */
  amenity: 'restaurant' | 'cafe' | 'fast_food' | 'bar' | 'pub' | 'food_court' | string;
  lat: number;
  lon: number;
  /** 距離使用者多少公尺 */
  distance_m: number;
  /** 若 OSM 名字有對到我們資料庫的連鎖店，這欄不為 null */
  matched_store: {
    id: number;
    name: string;
    slug: string;
    logo_emoji: string | null;
  } | null;
  /** 附近還有幾家同品牌分店（含當前這家）。例：1 = 只有這家、2 = 還有 1 家 */
  nearby_branch_count: number;
  /** 電話（OSM 上有的話），可能為空字串 */
  phone: string;
  /** 營業時間（OSM 格式，例：Mo-Fr 09:00-18:00; Sa 10:00-15:00），可能為空字串 */
  opening_hours: string;
  /** 網址，可能為空字串 */
  website: string;
}

interface NearbyWrap {
  data: NearbyStore[];
  meta: {
    count: number;
    radius: number;
    note: string;
  };
}

/**
 * 附近店家（呼叫後端，後端去打 OSM Overpass）
 */
export const nearbyStoreService = {
  /** GET /api/nearby-stores?lat=&lon=&radius= */
  find(lat: number, lon: number, radius?: number): Promise<NearbyWrap> {
    return http
      .get<NearbyWrap>('/nearby-stores', {
        params: {
          lat,
          lon,
          ...(radius ? { radius } : {}),
        },
      })
      .then((r) => r.data);
  },
};

/** UI 用：amenity / shop 中文標籤 */
export const AMENITY_LABEL: Record<string, string> = {
  // amenity 類
  restaurant:    '餐廳',
  cafe:          '咖啡店',
  fast_food:     '速食',
  bar:           '酒吧',
  pub:           '酒吧',
  food_court:    '美食街',
  ice_cream:     '冰品',
  biergarten:    '啤酒花園',
  // shop 類
  bakery:        '麵包店',
  confectionery: '甜點/糖果',
  convenience:   '便利商店',
  supermarket:   '超市',
  deli:          '熟食店',
  pastry:        '糕點店',
  butcher:       '肉舖',
};

/** 取得使用者目前位置（用瀏覽器內建 Geolocation API） */
export function getCurrentPosition(): Promise<{ lat: number; lon: number }> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('您的瀏覽器不支援定位功能'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lon: pos.coords.longitude }),
      (err) => {
        const map: Record<number, string> = {
          1: '您拒絕了定位權限。請到瀏覽器設定允許這個網站讀取位置。',
          2: '無法取得您的位置（GPS / 網路定位失敗）',
          3: '定位逾時，請再試一次',
        };
        reject(new Error(map[err.code] ?? '定位失敗'));
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 },
    );
  });
}

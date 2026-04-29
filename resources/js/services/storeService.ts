import http from './http';
import type { Food } from './foodService';

export type StoreCategory =
  | 'fast_food'
  | 'drink'
  | 'convenience'
  | 'rice_box'
  | 'noodle'
  | 'snack'
  | 'other';

export interface Store {
  id: number;
  name: string;
  slug: string;
  category: StoreCategory;
  logo_emoji: string | null;
  description: string | null;
  confidence_level: 'high' | 'medium' | 'low';
  menu_items_count: number;
  /** 列表頁不會帶；詳情頁會帶 */
  menu_items?: Food[];
}

interface ListWrap { data: Store[] }
interface ItemWrap { data: Store }

export interface GeneratedMenuMeta {
  store_id: number;
  store_slug: string;
  store_name: string;
  menu_items_count: number;
}
interface GenWrap { data: GeneratedMenuMeta }

/**
 * 連鎖店瀏覽 + AI 菜單推測
 */
export const storeService = {
  /** GET /api/stores — 列出所有連鎖店（不含菜單） */
  list(): Promise<Store[]> {
    return http.get<ListWrap>('/stores').then((r) => r.data.data);
  },

  /** GET /api/stores/{id} — 連鎖店詳情 + 菜單 */
  show(id: number): Promise<Store> {
    return http.get<ItemWrap>(`/stores/${id}`).then((r) => r.data.data);
  },

  /**
   * POST /api/stores/generate-menu — 用 AI 為任何店名生成推測菜單
   * 重複呼叫同名店家會直接回 cache，不會重新跑 AI
   */
  generateMenu(name: string): Promise<GeneratedMenuMeta> {
    return http
      .post<GenWrap>('/stores/generate-menu', { name })
      .then((r) => r.data.data);
  },
};

/** UI 標籤 */
export const STORE_CATEGORY_LABEL: Record<StoreCategory, string> = {
  fast_food:   '速食',
  drink:       '飲料',
  convenience: '便利商店',
  rice_box:    '便當',
  noodle:      '麵食',
  snack:       '點心',
  other:       '其他',
};

import http from './http';

export interface GeocodeResult {
  lat: number;
  lon: number;
  display_name: string;
  type: string;
  importance: number;
}

interface GeocodeWrap {
  data: GeocodeResult;
  meta: { note: string };
}

/**
 * 地址 / 地名 → 經緯度（用 OSM Nominatim）
 * 範例查詢字串：
 *   - 台北車站
 *   - 信義誠品
 *   - 台北市信義區市府路45號
 *   - 國立成功大學
 */
export const geocodingService = {
  /** GET /api/geocode?q=... */
  search(q: string): Promise<GeocodeResult> {
    return http
      .get<GeocodeWrap>('/geocode', { params: { q } })
      .then((r) => r.data.data);
  },
};

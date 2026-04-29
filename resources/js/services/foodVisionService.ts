import http from './http';

export interface VisionLabel {
  name: string;   // 英文標籤（label）或多語言（web entity）
  score: number;  // 0~1
}

export interface VisionCandidate {
  id: number;
  name: string;
  brand: string | null;
  category: string;
  serving_unit: string;
  serving_size: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  source_type: 'system_estimate' | 'user_custom' | 'imported' | 'official';
  confidence_level: 'high' | 'medium' | 'low';
  match_score: number; // 後端算的比對分數，越高越像
}

export interface VisionAnalyzeResult {
  labels: VisionLabel[];      // Cloud Vision 給的英文標籤
  entities: VisionLabel[];    // Cloud Vision web detection（含中文 entity）
  candidates: VisionCandidate[]; // 已比對 foods 資料庫的候選清單
  notes: string[];
}

interface VisionAnalyzeResponse {
  data: VisionAnalyzeResult;
}

/**
 * 食物拍照辨識 API。
 * 透過 multipart/form-data 上傳照片。
 */
export const foodVisionService = {
  /** POST /api/foods/vision/analyze */
  analyzeFoodPhoto(file: File): Promise<VisionAnalyzeResult> {
    const fd = new FormData();
    fd.append('image', file);
    return http
      .post<VisionAnalyzeResponse>('/foods/vision/analyze', fd)
      .then((r) => r.data.data);
  },
};

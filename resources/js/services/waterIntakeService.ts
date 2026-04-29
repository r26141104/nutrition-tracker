import http from './http';

export interface WaterStatus {
  date: string;
  total_ml: number;
  target_ml: number;
  progress_percent: number;
  reached_target: boolean;
}

export interface WaterHistoryPoint {
  record_date: string;
  amount_ml: number;
  target_ml: number;
}

export interface WaterHistory {
  days: number;
  records: WaterHistoryPoint[];
}

interface DataWrap<T> { data: T }
interface MessageWrap<T> { data: T; message: string }

export const waterIntakeService = {
  /** GET /api/water-intake/today */
  fetchToday(): Promise<WaterStatus> {
    return http.get<DataWrap<WaterStatus>>('/water-intake/today').then(r => r.data.data);
  },

  /** POST /api/water-intake/add — 加 N ml 到今日 */
  addIntake(amountMl: number): Promise<{ status: WaterStatus; message: string }> {
    return http
      .post<MessageWrap<WaterStatus>>('/water-intake/add', { amount_ml: amountMl })
      .then(r => ({ status: r.data.data, message: r.data.message }));
  },

  /** DELETE /api/water-intake/today — 重設今日 */
  resetToday(): Promise<WaterStatus> {
    return http.delete<MessageWrap<WaterStatus>>('/water-intake/today').then(r => r.data.data);
  },

  /** GET /api/water-intake/history?days=7 */
  fetchHistory(days: 7 | 14 | 30 = 7): Promise<WaterHistory> {
    return http
      .get<DataWrap<WaterHistory>>('/water-intake/history', { params: { days } })
      .then(r => r.data.data);
  },
};

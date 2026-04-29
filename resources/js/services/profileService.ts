import http from './http';

export type ActivityLevel = 'sedentary' | 'light' | 'moderate' | 'active';
export type GoalType = 'lose_fat' | 'gain_muscle' | 'maintain';
export type Sex = 'male' | 'female';

export interface UserProfile {
  id: number;
  user_id: number;
  birthdate: string | null;       // 'YYYY-MM-DD'
  sex: Sex | null;
  age: number | null;
  height_cm: number;
  weight_kg: number;
  target_bmi: number;
  activity_level: ActivityLevel;
  goal_type: GoalType;
  created_at: string | null;
  updated_at: string | null;
}

export interface UpdateProfilePayload {
  birthdate: string;              // 'YYYY-MM-DD'
  sex: Sex;
  height_cm: number;
  weight_kg: number;
  target_bmi: number;
  activity_level: ActivityLevel;
  goal_type: GoalType;
}

export const profileService = {
  getProfile(): Promise<UserProfile | null> {
    return http
      .get<{ profile: UserProfile | null }>('/profile')
      .then((r) => r.data.profile);
  },

  updateProfile(payload: UpdateProfilePayload): Promise<UserProfile> {
    return http
      .put<{ profile: UserProfile }>('/profile', payload)
      .then((r) => r.data.profile);
  },
};

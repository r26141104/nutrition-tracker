import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: { name: 'dashboard' },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/Login.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/Register.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/profile',
    name: 'profile-setup',
    component: () => import('@/views/ProfileSetup.vue'),
    meta: { requiresAuth: true },
  },

  // 食物資料庫
  {
    path: '/foods',
    name: 'foods',
    component: () => import('@/views/FoodList.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/foods/new',
    name: 'food-new',
    component: () => import('@/views/FoodEdit.vue'),
    meta: { requiresAuth: true },
  },
  // 注意：import 必須在 /:id/edit 之前，避免 /foods/import/edit 之類的奇怪比對
  {
    path: '/foods/import',
    name: 'food-import',
    component: () => import('@/views/FoodImport.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/foods/:id/edit',
    name: 'food-edit',
    component: () => import('@/views/FoodEdit.vue'),
    props: true,
    meta: { requiresAuth: true },
  },

  // 飲食紀錄
  {
    path: '/meals',
    name: 'meals',
    component: () => import('@/views/MealList.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/meals/new',
    name: 'meal-new',
    component: () => import('@/views/MealEdit.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/meals/:id/edit',
    name: 'meal-edit',
    component: () => import('@/views/MealEdit.vue'),
    props: true,
    meta: { requiresAuth: true },
  },

  // 體重紀錄
  {
    path: '/body-records',
    name: 'body-records',
    component: () => import('@/views/BodyRecord.vue'),
    meta: { requiresAuth: true },
  },

  // 每週報告
  {
    path: '/weekly-report',
    name: 'weekly-report',
    component: () => import('@/views/WeeklyReport.vue'),
    meta: { requiresAuth: true },
  },

  // 運動建議
  {
    path: '/exercise-recommendations',
    name: 'exercise-recommendations',
    component: () => import('@/views/ExerciseRecommendation.vue'),
    meta: { requiresAuth: true },
  },

  // 餐點建議
  {
    path: '/food-recommendations',
    name: 'food-recommendations',
    component: () => import('@/views/FoodRecommendation.vue'),
    meta: { requiresAuth: true },
  },

  // 拍照辨識
  {
    path: '/foods/vision',
    name: 'food-vision',
    component: () => import('@/views/FoodVision.vue'),
    meta: { requiresAuth: true },
  },

  // 個人化分析（階段 F）
  {
    path: '/analysis',
    name: 'analysis',
    component: () => import('@/views/AnalysisOverview.vue'),
    meta: { requiresAuth: true },
  },

  // 階段 I：附近店家 + 連鎖店菜單
  {
    path: '/nearby-stores',
    name: 'nearby-stores',
    component: () => import('@/views/NearbyStores.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/stores/:id',
    name: 'store-detail',
    component: () => import('@/views/StoreDetail.vue'),
    props: true,
    meta: { requiresAuth: true },
  },

  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'login' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Router guard：每次切頁前檢查登入狀態
router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.bootstrapped) {
    await auth.bootstrap();
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' };
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }
});

export default router;

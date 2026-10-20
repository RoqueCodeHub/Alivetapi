export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T | null;
  errors?: Record<string, string[]>;
}

export interface HealthData {
  status: string;
  app: string;
  env: string;
  database: string;
  time: string;
}

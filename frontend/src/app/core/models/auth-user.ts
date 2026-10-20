export interface AuthUser {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  cliente_id?: number | null;
  roles: string[];
  permissions: string[];
}

export interface LoginData {
  token: string;
  token_type: string;
  user: AuthUser;
}

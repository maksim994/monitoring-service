import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, type AuthResponse } from '../api/client';

type AuthState = {
  token: string | null;
  user: AuthResponse['user'] | null;
  organization: AuthResponse['organization'];
  initializing: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (payload: { email: string; password: string; name: string; organizationName?: string }) => Promise<void>;
  logout: () => void;
};

const AuthContext = createContext<AuthState | null>(null);
const TOKEN_KEY = 'monitoring_token';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(TOKEN_KEY));
  const [user, setUser] = useState<AuthResponse['user'] | null>(null);
  const [organization, setOrganization] = useState<AuthResponse['organization']>(null);
  const [initializing, setInitializing] = useState(() => Boolean(localStorage.getItem(TOKEN_KEY)));

  useEffect(() => {
    if (!token) {
      setUser(null);
      setOrganization(null);
      setInitializing(false);
      return;
    }

    setInitializing(true);
    api.me(token)
      .then((response) => {
        setUser(response.user);
        setOrganization(response.organization);
      })
      .catch(() => {
        localStorage.removeItem(TOKEN_KEY);
        setToken(null);
        setUser(null);
        setOrganization(null);
      })
      .finally(() => {
        setInitializing(false);
      });
  }, [token]);

  const value = useMemo<AuthState>(() => ({
    token,
    user,
    organization,
    initializing,
    login: async (email, password) => {
      const response = await api.login({ email, password });
      localStorage.setItem(TOKEN_KEY, response.token);
      setToken(response.token);
      setUser(response.user);
      setOrganization(response.organization);
      setInitializing(false);
    },
    register: async (payload) => {
      const response = await api.register(payload);
      localStorage.setItem(TOKEN_KEY, response.token);
      setToken(response.token);
      setUser(response.user);
      setOrganization(response.organization);
      setInitializing(false);
    },
    logout: () => {
      localStorage.removeItem(TOKEN_KEY);
      setToken(null);
      setUser(null);
      setOrganization(null);
      setInitializing(false);
    },
  }), [token, user, organization, initializing]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}

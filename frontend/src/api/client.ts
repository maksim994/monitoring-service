const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8080';

export type ApiError = {
  error: {
    code: string;
    message: string;
  };
};

async function request<T>(path: string, options: RequestInit = {}, token?: string | null): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Content-Type', 'application/json');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  const data = await response.json();
  if (!response.ok) {
    throw data as ApiError;
  }

  return data as T;
}

export type AuthResponse = {
  token: string;
  user: { id: string; email: string; name: string; isPlatformAdmin?: boolean };
  organization: { id: string; name: string; planCode: string; role?: string } | null;
};

export type SiteSummary = {
  id: string;
  domain: string;
  status: string;
  lastHeartbeatAt: string | null;
  openIncidents: number;
};

export type SiteDetails = SiteSummary & {
  siteUrl: string;
  moduleVersion: string | null;
  bitrixVersion: string | null;
  phpVersion: string | null;
  configVersion: number;
};

export type IncidentSummary = {
  id: string;
  siteId: string;
  siteDomain: string;
  checkType: string;
  severity: string;
  status: string;
  title: string;
  openedAt: string;
  acknowledgedAt: string | null;
  resolvedAt: string | null;
};

export type IncidentDetails = IncidentSummary & {
  evidence: Record<string, unknown>;
  updatedAt: string;
};

export type NotificationChannel = {
  id: string;
  type: string;
  name: string;
  settings: {
    email?: string;
    chatId?: string;
    botTokenConfigured?: boolean;
    url?: string;
    secret?: string;
  };
  enabled: boolean;
  createdAt: string;
};

export type PlanUsage = {
  planCode: string;
  planLabel: string;
  sites: { used: number; limit: number };
  users: { used: number; limit: number };
  uptimeIntervalSeconds: number;
  webhooksEnabled: boolean;
  webhooks: { used: number };
  availablePlans?: PlanOption[];
};

export type PlanOption = {
  code: string;
  label: string;
  maxSites: number;
  maxUsers: number;
  uptimeIntervalSeconds: number;
  webhooksEnabled: boolean;
};

export type OrganizationMember = {
  userId: string;
  email: string;
  name: string;
  role: string;
  joinedAt: string;
};

export type AuditLogEntry = {
  id: string;
  action: string;
  targetType: string;
  targetId: string | null;
  message: string;
  actorUserId: string | null;
  createdAt: string;
};

export type MaintenanceWindow = {
  id: string;
  siteId: string;
  title: string;
  checkType: string | null;
  startsAt: string;
  endsAt: string;
  cancelledAt: string | null;
  active: boolean;
  scheduled: boolean;
};

export type NotificationDeliveryEntry = {
  id: string;
  channelId: string;
  channelName: string;
  channelType: string;
  incidentId: string | null;
  status: string;
  error: string | null;
  sentAt: string | null;
  createdAt: string;
};

export const api = {
  register: (payload: { email: string; password: string; name: string; organizationName?: string }) =>
    request<AuthResponse>('/api/v1/auth/register', { method: 'POST', body: JSON.stringify(payload) }),
  login: (payload: { email: string; password: string }) =>
    request<AuthResponse>('/api/v1/auth/login', { method: 'POST', body: JSON.stringify(payload) }),
  me: (token: string) => request<{ user: AuthResponse['user']; organization: AuthResponse['organization'] & { role?: string } }>(
    '/api/v1/auth/me',
    {},
    token,
  ),
  listSites: (token: string) => request<{ items: SiteSummary[] }>('/api/v1/sites', {}, token),
  getSite: (token: string, siteId: string) =>
    request<
      SiteDetails & {
        checks: Array<{
          id: string;
          type: string;
          enabled: boolean;
          intervalSeconds: number;
          settings: Record<string, unknown>;
          snapshot?: {
            status: string;
            value: Record<string, unknown>;
            collectedAt: string;
          } | null;
        }>;
      }
    >(`/api/v1/sites/${siteId}`, {}, token),
  updateCheck: (
    token: string,
    siteId: string,
    checkId: string,
    payload: { settings?: Record<string, number>; enabled?: boolean },
  ) =>
    request<{
      id: string;
      type: string;
      enabled: boolean;
      intervalSeconds: number;
      settings: Record<string, unknown>;
      snapshot?: {
        status: string;
        value: Record<string, unknown>;
        collectedAt: string;
      } | null;
    }>(
      `/api/v1/sites/${siteId}/checks/${checkId}`,
      { method: 'PATCH', body: JSON.stringify(payload) },
      token,
    ),
  createSite: (token: string, payload: { domain: string; siteUrl: string }) =>
    request<{ siteId: string; apiSecret: string; site: SiteDetails }>(
      '/api/v1/sites',
      { method: 'POST', body: JSON.stringify(payload) },
      token,
    ),
  rotateSiteKey: (token: string, siteId: string) =>
    request<{ siteId: string; apiSecret: string }>(`/api/v1/sites/${siteId}/rotate-key`, { method: 'POST' }, token),
  disableSite: (token: string, siteId: string) =>
    request<SiteDetails>(`/api/v1/sites/${siteId}/disable`, { method: 'POST' }, token),
  enableSite: (token: string, siteId: string) =>
    request<SiteDetails>(`/api/v1/sites/${siteId}/enable`, { method: 'POST' }, token),
  listMaintenanceWindows: (token: string, siteId: string) =>
    request<{ items: MaintenanceWindow[] }>(`/api/v1/sites/${siteId}/maintenance-windows`, {}, token),
  createMaintenanceWindow: (
    token: string,
    siteId: string,
    payload: { title?: string; durationHours?: number; checkType?: string; startsAt?: string; endsAt?: string },
  ) =>
    request<MaintenanceWindow>(`/api/v1/sites/${siteId}/maintenance-windows`, { method: 'POST', body: JSON.stringify(payload) }, token),
  cancelMaintenanceWindow: (token: string, siteId: string, windowId: string) =>
    request<MaintenanceWindow>(`/api/v1/sites/${siteId}/maintenance-windows/${windowId}/cancel`, { method: 'POST' }, token),
  listIncidents: (token: string, status?: string) => {
    const query = status ? `?status=${encodeURIComponent(status)}` : '';
    return request<{ items: IncidentSummary[] }>(`/api/v1/incidents${query}`, {}, token);
  },
  acknowledgeIncident: (token: string, incidentId: string) =>
    request<IncidentDetails>(`/api/v1/incidents/${incidentId}/acknowledge`, { method: 'POST' }, token),
  resolveIncident: (token: string, incidentId: string) =>
    request<IncidentDetails>(`/api/v1/incidents/${incidentId}/resolve`, { method: 'POST' }, token),
  listNotificationChannels: (token: string) =>
    request<{ items: NotificationChannel[] }>('/api/v1/notification-channels', {}, token),
  createNotificationChannel: (
    token: string,
    payload: { type: string; name: string; settings: Record<string, string> },
  ) => request<NotificationChannel>('/api/v1/notification-channels', { method: 'POST', body: JSON.stringify(payload) }, token),
  testNotificationChannel: (token: string, channelId: string) =>
    request<{ status: string }>(`/api/v1/notification-channels/${channelId}/test`, { method: 'POST' }, token),
  getPlanUsage: (token: string) => request<PlanUsage>('/api/v1/organization/plan', {}, token),
  changePlan: (token: string, planCode: string) =>
    request<PlanUsage>('/api/v1/organization/plan/change', { method: 'POST', body: JSON.stringify({ planCode }) }, token),
  listOrganizationUsers: (token: string) =>
    request<{ items: OrganizationMember[] }>('/api/v1/organization/users', {}, token),
  inviteOrganizationUser: (
    token: string,
    payload: { email: string; name: string; password?: string; role: string },
  ) => request<OrganizationMember>('/api/v1/organization/users', { method: 'POST', body: JSON.stringify(payload) }, token),
  updateOrganizationUserRole: (token: string, userId: string, role: string) =>
    request<OrganizationMember>(`/api/v1/organization/users/${userId}`, { method: 'PATCH', body: JSON.stringify({ role }) }, token),
  removeOrganizationUser: (token: string, userId: string) =>
    request<{ status: string }>(`/api/v1/organization/users/${userId}`, { method: 'DELETE' }, token),
  listAuditLogs: (token: string) =>
    request<{ items: AuditLogEntry[] }>('/api/v1/organization/audit-logs', {}, token),
  listNotificationDeliveries: (token: string) =>
    request<{ items: NotificationDeliveryEntry[] }>('/api/v1/organization/notification-deliveries', {}, token),
};

export type AdminDashboard = {
  organizations: number;
  sites: number;
  activeSites: number;
  users: number;
};

export type AdminOrganizationSummary = {
  id: string;
  name: string;
  planCode: string;
  planLabel: string;
  status: string;
  sitesCount: number;
  activeSitesCount: number;
  usersCount: number;
  openIncidents: number;
  createdAt: string;
};

export type AdminOrganizationDetails = AdminOrganizationSummary & {
  usage: PlanUsage;
  sites: Array<{
    id: string;
    domain: string;
    siteUrl: string;
    status: string;
    lastHeartbeatAt: string | null;
    openIncidents: number;
  }>;
};

export type AdminSiteSummary = {
  id: string;
  organizationId: string;
  organizationName: string;
  domain: string;
  siteUrl: string;
  status: string;
  lastHeartbeatAt: string | null;
  openIncidents: number;
  createdAt: string;
};

export type AdminPlan = PlanOption & { active: boolean; sortOrder: number };

export const adminApi = {
  dashboard: (token: string) => request<AdminDashboard>('/api/v1/admin/dashboard', {}, token),
  listOrganizations: (token: string) =>
    request<{ items: AdminOrganizationSummary[] }>('/api/v1/admin/organizations', {}, token),
  getOrganization: (token: string, organizationId: string) =>
    request<AdminOrganizationDetails>(`/api/v1/admin/organizations/${organizationId}`, {}, token),
  updateOrganization: (
    token: string,
    organizationId: string,
    payload: { name?: string; planCode?: string; status?: string },
  ) => request<AdminOrganizationSummary>(`/api/v1/admin/organizations/${organizationId}`, { method: 'PATCH', body: JSON.stringify(payload) }, token),
  listSites: (token: string) => request<{ items: AdminSiteSummary[] }>('/api/v1/admin/sites', {}, token),
  updateSiteStatus: (token: string, siteId: string, status: string) =>
    request<AdminSiteSummary>(`/api/v1/admin/sites/${siteId}`, { method: 'PATCH', body: JSON.stringify({ status }) }, token),
  listPlans: (token: string) => request<{ items: AdminPlan[] }>('/api/v1/admin/plans', {}, token),
  createPlan: (
    token: string,
    payload: {
      code: string;
      label: string;
      maxSites: number;
      maxUsers: number;
      uptimeIntervalSeconds: number;
      webhooksEnabled: boolean;
      sortOrder?: number;
    },
  ) => request<AdminPlan>('/api/v1/admin/plans', { method: 'POST', body: JSON.stringify(payload) }, token),
  updatePlan: (
    token: string,
    code: string,
    payload: Partial<{
      label: string;
      maxSites: number;
      maxUsers: number;
      uptimeIntervalSeconds: number;
      webhooksEnabled: boolean;
      active: boolean;
      sortOrder: number;
    }>,
  ) => request<AdminPlan>(`/api/v1/admin/plans/${code}`, { method: 'PATCH', body: JSON.stringify(payload) }, token),
  deletePlan: (token: string, code: string) =>
    request<{ status: string }>(`/api/v1/admin/plans/${code}`, { method: 'DELETE' }, token),
};

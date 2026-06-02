const MANAGE_SITES = new Set(['owner', 'admin', 'integrator']);
const MANAGE_CHANNELS = new Set(['owner', 'admin']);
const MANAGE_INCIDENTS = new Set(['owner', 'admin', 'operator']);
const MANAGE_USERS = new Set(['owner', 'admin']);
const MANAGE_PLAN = new Set(['owner']);
const VIEW_AUDIT = new Set(['owner', 'admin']);

export function canManageSites(role?: string) {
  if (!role) {
    return false;
  }

  return MANAGE_SITES.has(role);
}

/** Show create-site UI when role is missing (legacy API) but organization exists. */
export function canManageSitesInUi(role: string | undefined, hasOrganization: boolean) {
  if (!hasOrganization) {
    return false;
  }

  if (!role) {
    return true;
  }

  return canManageSites(role);
}

export function canManageChannels(role?: string) {
  return role ? MANAGE_CHANNELS.has(role) : false;
}

export function canManageIncidents(role?: string) {
  return role ? MANAGE_INCIDENTS.has(role) : false;
}

export function canManageUsers(role?: string) {
  return role ? MANAGE_USERS.has(role) : false;
}

export function canManagePlan(role?: string) {
  return role ? MANAGE_PLAN.has(role) : false;
}

export function canViewAudit(role?: string) {
  return role ? VIEW_AUDIT.has(role) : false;
}

export function roleLabel(role?: string) {
  const labels: Record<string, string> = {
    owner: 'Owner',
    admin: 'Admin',
    integrator: 'Integrator',
    operator: 'Operator',
    viewer: 'Viewer',
  };

  return role ? labels[role] ?? role : '—';
}

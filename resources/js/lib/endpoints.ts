import { api } from './api'
import type {
  ProjectDetail, TrackedProject, TrackerDashboard, Activity, Meeting, ChangeRequest, ClientDependency, ProjectSummary, Client, ClientMessage, ChatEvaluation,
  MessageChannel, MessageReason, MessageStatus, OnboardingAnalysis, WorkspaceSettings, ProjectDocument, ChatMessage, ProjectPlan, WhatsAppStatus, WhatsAppConversation, WhatsAppThread,
} from '@/types/tracker'
import type { PortalNotification, PortalProject, PortalProjectDetail } from '@/types/portal'
import type { Country, Currency, Paginated, Plan, PlatformStats, Pricing, PublicConfig, Session, Tenant, TenantDetail, User } from '@/types'

type Query = Record<string, string | number | boolean | undefined | null>
type Body = Record<string, unknown>

export interface RegistrationOrder { key: string; order_id: string; amount: number; currency: string; plan: string; name: string; email: string; contact: string | null }
export interface RazorpayResult { razorpay_order_id: string; razorpay_payment_id: string; razorpay_signature: string }

const clean = (params: Query = {}) => Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))

export const authApi = {
  workspace: (slug: string) => api.get<{ name: string; slug: string }>(`/public/workspaces/${slug}`).then((r) => r.data),
  requestOtp: (body: { workspace?: string; email: string }) => api.post<{ message: string }>('/auth/otp/request', body).then((r) => r.data),
  verifyOtp: (body: { workspace?: string; email: string; code: string }) => api.post<Session>('/auth/otp/verify', body).then((r) => r.data),
  registerOrder: (body: Body) => api.post<RegistrationOrder>('/auth/register/order', body).then((r) => r.data),
  registerVerify: (body: RazorpayResult) => api.post<Session>('/auth/register/verify', body).then((r) => r.data),
  me: () => api.get<Omit<Session, 'token'>>('/auth/me').then((r) => r.data),
  logout: () => api.post('/auth/logout'),
  signOutOtherDevices: () => api.post<{ revoked: number }>('/auth/sessions/revoke-others').then((r) => r.data.revoked),
  updateProfile: (body: Body) => api.patch<{ user: User }>('/auth/profile', body).then((r) => r.data.user),
}

export const publicApi = {
  config: () => api.get<PublicConfig>('/public/config').then((r) => r.data),
  pricing: (country?: string | null) => api.get<Pricing>('/public/pricing', { params: clean({ country }) }).then((r) => r.data),
}

export const plansApi = { list: () => api.get<{ data: Plan[] }>('/plans').then((r) => r.data.data) }
export const adminApi = {
  stats: () => api.get<PlatformStats>('/admin/stats').then((r) => r.data),
  tenants: (params: Query = {}) => api.get<Paginated<Tenant>>('/admin/tenants', { params: clean(params) }).then((r) => r.data),
  tenant: (id: number | string) => api.get<TenantDetail>(`/admin/tenants/${id}`).then((r) => r.data),
  createTenant: (body: Body) =>
    api.post<{ data: Tenant; admin: { name: string; email: string }; temporary_password: string | null }>('/admin/tenants', body).then((r) => r.data),
  updateTenant: (id: number, body: Body) => api.patch<{ data: Tenant }>(`/admin/tenants/${id}`, body).then((r) => r.data.data),
  deleteTenant: (id: number) => api.delete(`/admin/tenants/${id}`),
  countries: () => api.get<{ data: Country[] }>('/admin/countries').then((r) => r.data.data),
  createCountry: (body: Body) => api.post<{ data: Country }>('/admin/countries', body).then((r) => r.data.data),
  updateCountry: (id: number, body: Body) => api.patch<{ data: Country }>(`/admin/countries/${id}`, body).then((r) => r.data.data),
  deleteCountry: (id: number) => api.delete(`/admin/countries/${id}`),
  currencies: () => api.get<{ data: Currency[] }>('/admin/currencies').then((r) => r.data.data),
  createCurrency: (body: Body) => api.post<{ data: Currency }>('/admin/currencies', body).then((r) => r.data.data),
  updateCurrency: (id: number, body: Body) => api.patch<{ data: Currency }>(`/admin/currencies/${id}`, body).then((r) => r.data.data),
  deleteCurrency: (id: number) => api.delete(`/admin/currencies/${id}`),
  plans: () => api.get<{ data: Plan[] }>('/admin/plans').then((r) => r.data.data),
  updatePlan: (id: number, body: Body) => api.patch<{ data: Plan }>(`/admin/plans/${id}`, body).then((r) => r.data.data),
  createPlan: (body: Body) => api.post<{ data: Plan }>('/admin/plans', body).then((r) => r.data.data),
  /** Saves the whole country-price grid for every plan at once. Rows left out lose their fixed price. */
  savePrices: (prices: { plan_id: number; country_code: string; price_monthly: number; price_yearly: number }[]) =>
    api.put<{ data: Plan[] }>('/admin/plans/prices', { prices }).then((r) => r.data.data),
  deletePlan: (id: number) => api.delete(`/admin/plans/${id}`).then((r) => r.data),
  settings: () => api.get<{ data: Record<string, unknown> }>('/admin/settings').then((r) => r.data.data),
  updateSettings: (body: Body) => api.put<{ data: Record<string, unknown> }>('/admin/settings', body).then((r) => r.data.data),
}

type Mutated<T> = { data: T; summary: ProjectSummary }

export const trackerApi = {
  dashboard: () => api.get<TrackerDashboard>('/tracker/dashboard').then((r) => r.data),
  projects: (params: { search?: string; status?: string } = {}) => api.get<{ data: TrackedProject[] }>('/tracker/projects', { params: clean(params) }).then((r) => r.data.data),
  project: (id: number | string) => api.get<ProjectDetail>(`/tracker/projects/${id}`).then((r) => r.data),
  /** Reads a requirement document (PDF, DOCX, TXT, MD) and proposes the project details, working days and steps. Creates nothing. */
  analyseDocument: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return api.post<{ data: OnboardingAnalysis }>('/tracker/onboarding/analyse', form, { timeout: 120_000 }).then((r) => r.data.data)
  },
  createProject: (body: Body) => api.post<{ data: TrackedProject }>('/tracker/projects', body).then((r) => r.data.data),
  updateProject: (id: number, body: Body) => api.patch<{ data: TrackedProject }>(`/tracker/projects/${id}`, body).then((r) => r.data.data),
  deleteProject: (id: number) => api.delete(`/tracker/projects/${id}`),
  analyse: (id: number) => api.post<{ data: TrackedProject; summary: ProjectSummary }>(`/tracker/projects/${id}/analyse`).then((r) => r.data),

  addActivity: (projectId: number, body: Body) => api.post<Mutated<Activity>>(`/tracker/projects/${projectId}/activities`, body).then((r) => r.data),
  updateActivity: (id: number, body: Body) => api.patch<Mutated<Activity>>(`/tracker/activities/${id}`, body).then((r) => r.data),
  deleteActivity: (id: number) => api.delete(`/tracker/activities/${id}`),

  /** Meetings are sent as multipart; updates use POST because PHP does not parse multipart PATCH. */
  addMeeting: (projectId: number, form: FormData) => api.post<{ data: Meeting }>(`/tracker/projects/${projectId}/meetings`, form).then((r) => r.data.data),
  updateMeeting: (id: number, form: FormData) => api.post<{ data: Meeting }>(`/tracker/meetings/${id}`, form).then((r) => r.data.data),
  deleteMeeting: (id: number) => api.delete(`/tracker/meetings/${id}`),
  downloadAttachment: (id: number) => api.get<Blob>(`/tracker/attachments/${id}/download`, { responseType: 'blob' }).then((r) => r.data),
  deleteAttachment: (id: number) => api.delete(`/tracker/attachments/${id}`),

  addDocument: (projectId: number, form: FormData) => api.post<{ data: ProjectDocument }>(`/tracker/projects/${projectId}/documents`, form).then((r) => r.data.data),
  downloadDocument: (id: number) => api.get<Blob>(`/tracker/documents/${id}/download`, { responseType: 'blob' }).then((r) => r.data),
  deleteDocument: (id: number) => api.delete(`/tracker/documents/${id}`),

  addChange: (projectId: number, body: Body) => api.post<Mutated<ChangeRequest>>(`/tracker/projects/${projectId}/change-requests`, body).then((r) => r.data),
  updateChange: (id: number, body: Body) => api.patch<Mutated<ChangeRequest>>(`/tracker/change-requests/${id}`, body).then((r) => r.data),
  deleteChange: (id: number) => api.delete(`/tracker/change-requests/${id}`),

  addDependency: (projectId: number, body: Body) => api.post<Mutated<ClientDependency>>(`/tracker/projects/${projectId}/client-dependencies`, body).then((r) => r.data),
  updateDependency: (id: number, body: Body) => api.patch<Mutated<ClientDependency>>(`/tracker/client-dependencies/${id}`, body).then((r) => r.data),
  deleteDependency: (id: number) => api.delete(`/tracker/client-dependencies/${id}`),

  addMilestone: (projectId: number, body: Body) => api.post<ProjectPlan>(`/tracker/projects/${projectId}/milestones`, body).then((r) => r.data),
  updateMilestone: (id: number, body: Body) => api.patch<ProjectPlan>(`/tracker/milestones/${id}`, body).then((r) => r.data),
  deleteMilestone: (id: number) => api.delete<ProjectPlan>(`/tracker/milestones/${id}`).then((r) => r.data),
  generateMilestones: (projectId: number) => api.post<ProjectPlan>(`/tracker/projects/${projectId}/milestones/generate`).then((r) => r.data),

  thread: (projectId: number) => api.get<{ data: ChatMessage[] }>(`/tracker/projects/${projectId}/thread`).then((r) => r.data.data),
  sendThreadMessage: (projectId: number, body: string) => api.post<{ data: ChatMessage }>(`/tracker/projects/${projectId}/thread`, { body }).then((r) => r.data.data),

  report: (projectId: number, type: 'progress' | 'performance', format: 'pdf' | 'xlsx') =>
    api.get<Blob>(`/tracker/projects/${projectId}/reports/${type}`, { params: { format }, responseType: 'blob' }).then((r) => r.data),
}

export const clientsApi = {
  list: (search?: string) => api.get<{ data: Client[] }>('/clients', { params: clean({ search }) }).then((r) => r.data.data),
  create: (body: Body) => api.post<{ data: Client }>('/clients', body).then((r) => r.data.data),
  update: (id: number, body: Body) => api.patch<{ data: Client }>(`/clients/${id}`, body).then((r) => r.data.data),
  remove: (id: number) => api.delete(`/clients/${id}`),
  grantPortalAccess: (id: number) => api.post<{ data: Client }>(`/clients/${id}/portal-access`).then((r) => r.data.data),
  revokePortalAccess: (id: number) => api.delete<{ data: Client }>(`/clients/${id}/portal-access`).then((r) => r.data.data),
}

export const portalApi = {
  projects: () => api.get<{ data: PortalProject[] }>('/portal/projects').then((r) => r.data.data),
  project: (id: number | string) => api.get<PortalProjectDetail>(`/portal/projects/${id}`).then((r) => r.data),
  downloadDocument: (id: number) => api.get<Blob>(`/portal/documents/${id}/download`, { responseType: 'blob' }).then((r) => r.data),
  uploadDocument: (projectId: number, form: FormData) => api.post<{ data: ProjectDocument }>(`/portal/projects/${projectId}/documents`, form).then((r) => r.data.data),
  chat: (projectId: number) => api.get<{ data: ChatMessage[] }>(`/portal/projects/${projectId}/chat`).then((r) => r.data.data),
  sendChatMessage: (projectId: number, body: string) => api.post<{ data: ChatMessage }>(`/portal/projects/${projectId}/chat`, { body }).then((r) => r.data.data),
  sendUpdate: (projectId: number, body: { message: string; request_id?: number }) => api.post<{ message: string }>(`/portal/projects/${projectId}/updates`, body).then((r) => r.data),
  raiseRequirement: (projectId: number, body: { title: string; description?: string }) => api.post(`/portal/projects/${projectId}/requirements`, body).then((r) => r.data),
}

/** Web notifications for everyone who signs in to a workspace, team and clients alike. */
export const notificationsApi = {
  list: () => api.get<{ unread: number; data: PortalNotification[] }>('/notifications').then((r) => r.data),
  markRead: (id?: string) => api.post<{ unread: number }>(id ? `/notifications/${id}/read` : '/notifications/read').then((r) => r.data.unread),
}

export const messagesApi = {
  list: (params: { status?: MessageStatus; channel?: MessageChannel; project_id?: number } = {}) =>
    api.get<{ data: ClientMessage[] }>('/tracker/messages', { params: clean(params) }).then((r) => r.data.data),
  draft: (projectId: number, body: { channel: MessageChannel; reason: MessageReason; instruction?: string; body?: string }) =>
    api.post<{ data: ClientMessage }>(`/tracker/projects/${projectId}/messages/draft`, body).then((r) => r.data.data),
  update: (id: number, body: Body) => api.patch<{ data: ClientMessage }>(`/tracker/messages/${id}`, body).then((r) => r.data.data),
  send: (id: number) => api.post<{ data: ClientMessage }>(`/tracker/messages/${id}/send`).then((r) => r.data.data),
  remove: (id: number) => api.delete(`/tracker/messages/${id}`),
  chat: (projectId: number) =>
    api.get<{ configured: boolean; messages: ClientMessage[]; evaluation: ChatEvaluation | null }>(`/tracker/projects/${projectId}/chat`).then((r) => r.data),
  evaluateChat: (projectId: number) => api.post<{ evaluation: ChatEvaluation }>(`/tracker/projects/${projectId}/chat/evaluate`).then((r) => r.data.evaluation),
}

export const workspaceApi = {
  settings: () => api.get<WorkspaceSettings>('/workspace/settings').then((r) => r.data),
  updateSettings: (body: Body) => api.put<WorkspaceSettings>('/workspace/settings', body).then((r) => r.data),
}

/** The workspace WhatsApp inbox (Enterprise plan): link a phone with a QR code, then chat with clients. */
export const whatsappApi = {
  status: () => api.get<WhatsAppStatus>('/whatsapp/status').then((r) => r.data),
  connect: () => api.post<{ state: WhatsAppStatus['state']; qr: string | null }>('/whatsapp/connect').then((r) => r.data),
  disconnect: () => api.post('/whatsapp/disconnect'),
  conversations: (search?: string) =>
    api.get<{ data: WhatsAppConversation[]; unread: number }>('/whatsapp/conversations', { params: clean({ search }) }).then((r) => r.data),
  thread: (clientId: number) => api.get<WhatsAppThread>(`/whatsapp/conversations/${clientId}`).then((r) => r.data),
  send: (clientId: number, body: string) => api.post<{ data: ClientMessage }>(`/whatsapp/conversations/${clientId}/messages`, { body }).then((r) => r.data.data),
}

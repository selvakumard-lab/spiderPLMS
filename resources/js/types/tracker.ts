export type TrackingStatus = 'on_time' | 'slightly_delayed' | 'delayed' | 'significantly_delayed' | 'completed'
export type ActivityStatus = 'pending' | 'in_progress' | 'completed'
export type ChangeStatus = 'pending' | 'approved' | 'rejected'

export interface TrackedProject {
  id: number
  code: string
  name: string
  description: string | null
  client_id: number | null
  client_name: string | null
  status: 'active' | 'on_hold' | 'completed' | 'cancelled'
  stage: string
  stage_label: string
  tracking_status: TrackingStatus
  progress: number
  start_date: string | null
  due_date: string | null
  revised_due_date: string | null
  total_working_days: number | null
  status_updated_at: string | null
  ai_summary: string | null
  meta: Record<string, string> | null
}

export interface Activity {
  id: number
  activity_date: string
  title: string
  description: string | null
  hours_spent: number
  days_spent: number
  estimated_hours: number | null
  next_step_estimate_hours: number | null
  status: ActivityStatus
  progress_percent: number
  ai_analysis: string | null
}

export interface MeetingAttachment {
  id: number
  name: string
  mime_type: string | null
  size: number
}

export interface Meeting {
  id: number
  meeting_date: string
  title: string
  details: string | null
  discussion_points: string | null
  links: string[]
  attachments: MeetingAttachment[]
}

export interface ChangeRequest {
  id: number
  title: string
  description: string | null
  submitted_on: string
  status: ChangeStatus
  decided_on: string | null
  additional_days: number
  additional_hours: number
}

export interface ClientDependency {
  id: number
  title: string
  description: string | null
  requested_on: string
  expected_on: string | null
  received_on: string | null
  days_delayed: number
  is_pending: boolean
}

export interface ProjectSummary {
  status: TrackingStatus
  progress_percent: number
  original_start_date: string | null
  original_due_date: string | null
  revised_due_date: string | null
  total_working_days: number | null
  additional_days_approved: number
  days_spent: number
  hours_spent: number
  remaining_working_days: number
  overdue_working_days: number
  completed_activities: number
  pending_activities: number
  client_pending_items: number
  pending_requirements: number
  estimated_hours: number
  actual_hours_on_estimated: number
  client_delay_days: number
  internal_delay_days: number
  additional_work_hours: number
  status_updated_at: string | null
}

export type MilestoneDisplayStatus = 'pending' | 'in_progress' | 'completed' | 'delayed'

export interface Milestone {
  id: number
  number: number
  stage: string | null
  stage_label: string | null
  title: string
  description: string | null
  sort_order: number
  status: 'pending' | 'in_progress' | 'completed'
  /** What to show: a step past its deadline that is not complete reads as "delayed". */
  display_status: MilestoneDisplayStatus
  timing: 'completed_on_time' | 'completed_late' | 'overdue' | 'in_progress' | 'upcoming'
  planned_start: string | null
  due_date: string | null
  started_on: string | null
  completed_on: string | null
  planned_days: number | null
  days_taken: number | null
  days_late: number
  days_until_due: number | null
}

export interface ProjectPlan {
  milestones: Milestone[]
  summary: {
    total: number
    completed: number
    in_progress: number
    delayed: number
    overdue: number
    percent: number
    next: { id: number; title: string; due_date: string | null; display_status: MilestoneDisplayStatus } | null
  }
}

export interface ProjectDetail {
  data: TrackedProject
  summary: ProjectSummary
  activities: Activity[]
  meetings: Meeting[]
  change_requests: ChangeRequest[]
  client_dependencies: ClientDependency[]
  documents: ProjectDocument[]
  events: ProjectEvent[]
  plan: ProjectPlan
  stages: { value: string; label: string }[]
}

export interface ChatMessage {
  id: number
  sender: 'team' | 'client'
  sender_name: string | null
  user_id: number | null
  body: string
  created_at: string
}

export interface ProjectEvent {
  id: number
  actor: 'team' | 'client'
  actor_name: string | null
  kind: 'step' | 'status' | 'stage' | 'document' | 'request' | 'requirement' | 'meeting' | 'update' | 'message'
  title: string
  body: string | null
  created_at: string
}

export interface ProjectDocument {
  id: number
  title: string
  note: string | null
  name: string
  mime_type: string | null
  size: number
  visible_to_client: boolean
  from_client: boolean
  created_at: string | null
}

export interface TrackerDashboard {
  total: number
  by_status: Record<TrackingStatus, number>
  average_progress: number
  projects: TrackedProject[]
}

export interface Client {
  id: number
  name: string
  contact_person: string | null
  email: string | null
  phone: string | null
  whatsapp_number: string | null
  notes: string | null
  projects_count: number | null
  has_portal_access: boolean
}

export type MessageChannel = 'email' | 'whatsapp'
export type MessageStatus = 'draft' | 'sent' | 'failed' | 'received'
export type MessageReason = 'dependency_overdue' | 'status_alert' | 'progress_update' | 'project_update' | 'custom'

export interface ClientMessage {
  id: number
  project_id: number | null
  project_name: string | null
  client_name: string | null
  channel: MessageChannel
  direction: 'in' | 'out'
  reason: MessageReason | null
  subject: string | null
  body: string
  to_address: string | null
  status: MessageStatus
  ai_generated: boolean
  error: string | null
  sent_at: string | null
  created_at: string
}

export interface ChatEvaluation {
  id: number
  summary: string
  sentiment: 'positive' | 'neutral' | 'negative' | 'urgent'
  pending_items: string[]
  suggested_reply: string | null
  messages_considered: number
  source: 'deepseek' | 'fallback'
  created_at: string
}

export interface WorkspaceSettings {
  data: { knowledge_base: string; tone: 'professional' | 'friendly' | 'formal'; sender_name: string; email_signature: string; auto_draft: boolean; email_updates: boolean }
  can_edit: boolean
  ai_configured: boolean
  whatsapp: { configured: boolean; state: string; instance: string }
}

import type { ActivityStatus, ChangeRequest, ClientDependency, ProjectDocument, ProjectEvent, ProjectPlan, TrackingStatus } from './tracker'

/** What a client sees of one project. */
export interface PortalProject {
  id: number
  code: string
  name: string
  description: string | null
  status: 'active' | 'on_hold' | 'completed' | 'cancelled'
  stage: string
  stage_label: string
  tracking_status: TrackingStatus
  progress: number
  start_date: string | null
  due_date: string | null
  revised_due_date: string | null
  status_updated_at: string | null
}

export interface PortalStep {
  number: number
  id: number
  date: string
  title: string
  description: string | null
  status: ActivityStatus
  hours_spent: number
  days_spent: number
  estimated_hours: number | null
  progress_percent: number
}

export interface PortalProjectDetail {
  data: PortalProject
  stages: { value: string; label: string }[]
  events: ProjectEvent[]
  summary: {
    status: TrackingStatus
    progress_percent: number
    hours_spent: number
    days_spent: number
    estimated_hours: number
    additional_work_hours: number
    total_working_days: number | null
    remaining_working_days: number
    overdue_working_days: number
    completed_activities: number
    pending_activities: number
    additional_days_approved: number
  }
  steps: PortalStep[]
  plan: ProjectPlan
  client_requests: ClientDependency[]
  requirements: ChangeRequest[]
  meetings: { id: number; date: string; title: string; discussion_points: string | null }[]
  documents: ProjectDocument[]
}

export interface PortalNotification {
  id: string
  kind: ProjectEvent['kind']
  title: string
  body: string | null
  project_id: number | null
  project_name: string | null
  read: boolean
  created_at: string
}

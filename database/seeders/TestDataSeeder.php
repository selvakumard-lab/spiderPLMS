<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\ProjectChatMessage;
use App\Models\ProjectEvent;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientProjectUpdate;
use App\Services\MilestonePlanner;
use App\Services\ProjectTrackerService;
use App\Services\TenantRegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A complete, believable test workspace that walks the whole product from start to end:
 * platform admin, two workspaces, a team, three clients (two with portal access) and five projects
 * that sit at different points of the journey, each with plan steps, logged work, meetings, documents,
 * change requests, client dependencies, a chat thread and a dated timeline of both sides.
 *
 * Every date is relative to today, so the data always looks live. Run after DatabaseSeeder's platform seeders:
 *   php artisan db:seed --class=TestDataSeeder
 *
 * Every account signs in with an emailed one-time code; with PLMS_STATIC_OTP set (local) the code is that value.
 */
class TestDataSeeder extends Seeder
{
    public const WORKSPACE = 'spiderIndia';

    private Tenant $tenant;

    /** @var array<string, User> */
    private array $team = [];

    /** @var array<string, User> */
    private array $portal = [];

    public function run(): void
    {
        if (Tenant::where('slug', self::WORKSPACE)->exists()) {
            $this->command?->warn('Test data already exists (workspace '.self::WORKSPACE.'). Run migrate:fresh --seed to rebuild it.');

            return;
        }

        $context = app(TenantContext::class);
        $registration = app(TenantRegistrationService::class);

        // A second workspace on a trial, so the Super Admin lists differently-staged tenants.
        $registration->register([
            'organization' => 'Acme Digital', 'slug' => 'acmeDigital', 'plan' => 'starter', 'status' => 'trial', 'country_code' => 'US',
            'name' => 'Morgan Blake', 'email' => 'owner@acmedigital.test',
        ]);

        $admin = $registration->register([
            'organization' => 'Spider India', 'slug' => self::WORKSPACE, 'plan' => 'growth', 'status' => 'active', 'country_code' => 'IN',
            'name' => 'Arun Kumar', 'email' => 'admin@spiderindia.test', 'phone' => '+91 98765 43210',
        ]);
        $this->tenant = $admin['tenant'];
        $this->team['admin'] = $admin['user'];

        $context->runAs($this->tenant, function (): void {
            $this->createTeam();
            $clients = $this->createClients();

            foreach ($this->projectSpecs($clients) as $spec) {
                $this->buildProject($spec);
            }

            $this->createEmailDraft($clients);
        });

        $context->set(null);
        $this->command?->info('Test data ready. Workspace /'.self::WORKSPACE.' (one-time code: '.config('plms.static_otp', 'emailed').').');
    }

    private function createTeam(): void
    {
        $this->team['admin']->forceFill(['job_title' => 'Organization Admin'])->save();

        foreach ([
            'pm' => ['Priya Nair', 'pm@spiderindia.test', 'Project Manager', Role::ProjectManager],
            'lead' => ['Karthik Rao', 'lead@spiderindia.test', 'Team Leader', Role::TeamLeader],
            'dev' => ['Ravi Menon', 'dev@spiderindia.test', 'Senior Developer', Role::Developer],
        ] as $key => [$name, $email, $title, $role]) {
            $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'job_title' => $title, 'password' => Str::random(40), 'status' => 'active']);
            $user->assignRole($role->value);
            $this->team[$key] = $user;
        }
    }

    /** @return array<string, Client> */
    private function createClients(): array
    {
        $clients = [
            'northwind' => Client::create(['name' => 'Northwind Traders', 'contact_person' => 'Casey Morgan', 'email' => 'casey@northwind.test', 'phone' => '+91 90000 11111', 'whatsapp_number' => '919000011111', 'notes' => 'Prefers email updates every Friday.']),
            'globex' => Client::create(['name' => 'Globex Bank', 'contact_person' => 'Dana Whitfield', 'email' => 'dana@globex.test', 'phone' => '+91 90000 22222', 'whatsapp_number' => '919000022222', 'notes' => 'Compliance review needed before any release.']),
            'initech' => Client::create(['name' => 'Initech Ltd', 'contact_person' => 'Peter Gibbons', 'email' => 'peter@initech.test', 'phone' => '+91 90000 33333', 'notes' => 'No portal access yet. Updates go by WhatsApp.']),
        ];

        // Two clients can sign in to the portal; Initech cannot, so the "give portal access" step can be tried.
        foreach (['northwind', 'globex'] as $key) {
            $this->portal[$key] = User::create([
                'tenant_id' => $this->tenant->id, 'client_id' => $clients[$key]->id, 'name' => $clients[$key]->contact_person,
                'email' => $clients[$key]->email, 'password' => Str::random(40), 'status' => 'active',
            ]);
        }

        return $clients;
    }

    /**
     * Each project sits at a different point of the journey. Plan codes per step (Requirements to Handover):
     * done = finished on time, late:N = finished N working days late, doing = in progress, todo = not started.
     *
     * @param  array<string, Client>  $clients
     * @return list<array<string, mixed>>
     */
    private function projectSpecs(array $clients): array
    {
        return [
            // 1. Healthy, mid-way: design is signed off and development is under way.
            [
                'key' => 'northwind', 'client' => $clients['northwind'], 'name' => 'Customer Portal v2',
                'description' => 'A self-service portal where Northwind customers view orders, download invoices and raise support requests.',
                'start' => -35, 'due' => 40, 'stage' => 'development', 'status' => 'active',
                'plan' => ['done', 'done', 'doing', 'todo', 'todo', 'todo'],
                'activities' => [
                    [-34, 'Kickoff and requirement workshop', 'Met the Northwind team and listed the must-have features.', 6, 6, 'completed', 5],
                    [-30, 'Requirement specification drafted', 'Wrote user stories for orders, invoices and support.', 14, 12, 'completed', 12],
                    [-24, 'Wireframes for dashboard and login', 'Low-fidelity screens shared for feedback.', 16, 14, 'completed', 18],
                    [-17, 'UI design: dashboard and invoices', 'High-fidelity designs in the client brand colours.', 22, 20, 'completed', 28],
                    [-10, 'Design sign-off meeting and revisions', 'Client asked for a larger invoice table; updated.', 8, 6, 'completed', 34],
                    [-6, 'Authentication and user roles API', 'Email one-time-code login and role checks.', 18, 16, 'completed', 42],
                    [-2, 'Invoice listing screens', 'List, filter and download invoices. Pagination pending.', 12, 14, 'in_progress', 50],
                ],
                'meetings' => [
                    [-34, 'Kickoff call', 'Scope, timeline and who approves what.', "Goals: reduce support calls.\nApprover: Casey Morgan.\nWeekly demo every Thursday."],
                    [-10, 'Design review', 'Walkthrough of the dashboard and invoice designs.', "Approved the dashboard.\nInvoice table needs a bigger font.\nAdd an export button later."],
                    [-3, 'Weekly demo', 'Showed login and the first invoice screens.', "Login flow approved.\nAsk finance for test invoice data."],
                ],
                'documents' => [
                    ['Requirements specification', 'requirements-specification.txt', "Customer Portal v2 - requirements\n1. Orders list\n2. Invoice download\n3. Support requests\n", 'Version 1.2, approved by Casey.', true, false, -29],
                    ['Dashboard wireframes', 'dashboard-wireframes.txt', "Wireframe notes: dashboard, orders, invoices.\n", 'Shared for review.', true, false, -23],
                    ['Internal costing sheet', 'internal-costing.csv', "item,hours\ndesign,60\nbuild,220\n", 'Team only.', false, false, -33],
                    ['Brand guidelines', 'northwind-brand-guidelines.txt', "Primary colour #0B5FA5. Font: Inter.\n", 'Our logo and colours.', true, true, -32],
                ],
                'changes' => [
                    ['Export invoices to Excel', 'Finance wants to download invoices as a spreadsheet.', -20, 'approved', -18, 3, 20, 'client'],
                    ['Add a dark mode', 'Would be nice for the evening shift.', -3, 'pending', null, 0, 0, 'client'],
                ],
                'dependencies' => [
                    ['Logo and brand assets', 'Vector logo and colour codes.', -33, -28, -27],
                    ['Test invoice data from finance', 'Ten anonymised invoices for testing.', -4, 3, null],
                ],
                'history' => [
                    [-35, '09:05', 'team', 'Priya Nair', 'stage', 'Project moved to Requirements', 'Project created and plan set up.'],
                    [-33, '11:40', 'client', 'Casey Morgan', 'document', 'Casey Morgan uploaded a document: Brand guidelines', 'Our logo and colours.'],
                    [-29, '16:20', 'team', 'Priya Nair', 'document', 'New document: Requirements specification', 'Version 1.2, approved by Casey.'],
                    [-26, '10:10', 'team', 'Priya Nair', 'step', 'Step completed: Requirements', 'Finished on time in 8 working days.'],
                    [-26, '10:12', 'team', 'Priya Nair', 'stage', 'Project moved to Design', 'Previous stage: Requirements.'],
                    [-20, '14:35', 'client', 'Casey Morgan', 'requirement', 'Casey Morgan raised a new requirement: Export invoices to Excel', 'Finance wants to download invoices as a spreadsheet.'],
                    [-18, '09:50', 'team', 'Priya Nair', 'requirement', 'Requirement approved: Export invoices to Excel', 'Adds 3 working days and 20 hours.'],
                    [-12, '17:00', 'team', 'Karthik Rao', 'step', 'Step completed: Design', 'Finished on time in 9 working days.'],
                    [-12, '17:02', 'team', 'Karthik Rao', 'stage', 'Project moved to Development', 'Previous stage: Design.'],
                    [-4, '12:15', 'team', 'Priya Nair', 'request', 'Waiting on you: Test invoice data from finance', 'Ten anonymised invoices for testing.'],
                    [-3, '15:45', 'client', 'Casey Morgan', 'requirement', 'Casey Morgan raised a new requirement: Add a dark mode', 'Would be nice for the evening shift.'],
                ],
                'chat' => [
                    [-34, '09:30', 'team', 'Welcome to the portal, Casey. Ask anything here and we will answer within a working day.'],
                    [-33, '11:42', 'client', 'Thanks! I have uploaded our brand guidelines. Is a Thursday demo OK?'],
                    [-33, '12:10', 'team', 'Thursday works. I have sent a calendar invite for 11:00.'],
                    [-5, '10:20', 'client', 'Can finance get an Excel export of invoices? It would save them a lot of time.'],
                    [-5, '11:05', 'team', 'Yes, I have logged it as a requirement. It adds 3 working days, and the new delivery date is shown on the plan.'],
                    [-1, '16:40', 'client', 'Great. We will send the test invoices by Thursday.'],
                ],
            ],
            // 2. Delayed: design finished late, development is overdue, and the client has not sent API keys.
            [
                'key' => 'globex', 'client' => $clients['globex'], 'name' => 'Mobile Banking App',
                'description' => 'iOS and Android app for retail customers: balances, statements, transfers and bill payments.',
                'start' => -45, 'due' => 25, 'stage' => 'development', 'status' => 'active',
                'plan' => ['done', 'late:3', 'doing', 'todo', 'todo', 'todo'],
                'activities' => [
                    [-43, 'Discovery with the product team', 'Mapped the customer journeys.', 8, 8, 'completed', 4],
                    [-38, 'Security and compliance requirements', 'Listed controls the bank requires.', 12, 10, 'completed', 9],
                    [-30, 'App navigation and design system', 'Core components and navigation.', 20, 16, 'completed', 16],
                    [-21, 'Design approval delayed: revisions', 'Compliance asked for changes to the transfer screens.', 10, 6, 'completed', 22],
                    [-14, 'Account and statement screens', 'Balances and PDF statements.', 18, 16, 'completed', 30],
                    [-7, 'Payments flow (blocked: sandbox keys)', 'Cannot test bill payments without the gateway sandbox.', 6, 18, 'in_progress', 38],
                    [-3, 'Biometric login spike', 'Proof of concept for Face ID and fingerprint.', 10, 8, 'in_progress', 40],
                ],
                'meetings' => [
                    [-43, 'Discovery workshop', 'Journeys, users and risks.', "Top journeys: balance, transfer, bill pay.\nBank compliance must approve designs."],
                    [-21, 'Compliance review', 'Design review with the bank compliance team.', "Transfer confirmation screen must show the full beneficiary.\nRe-submit designs by Friday."],
                ],
                'documents' => [
                    ['Security checklist', 'security-checklist.txt', "Encryption at rest, session timeout 5 minutes, device binding.\n", 'From our compliance team.', true, true, -37],
                    ['Revised transfer designs', 'revised-transfer-designs.txt', "Transfer confirmation: show beneficiary name and account.\n", 'Updated after the compliance review.', true, false, -19],
                ],
                'changes' => [
                    ['Add Hindi language support', 'Rural branches need the app in Hindi.', -25, 'approved', -23, 5, 30, 'client'],
                    ['Add a crypto wallet', 'Customers have asked about crypto.', -15, 'rejected', -14, 0, 0, 'client'],
                ],
                'dependencies' => [
                    ['NDA signed by the bank', 'Signed NDA before we receive customer data.', -45, -43, -41],
                    ['Payment gateway sandbox keys', 'Needed to build and test bill payments.', -20, -13, null],
                ],
                'history' => [
                    [-45, '09:00', 'team', 'Priya Nair', 'stage', 'Project moved to Requirements', 'Project created and plan set up.'],
                    [-41, '15:30', 'client', 'Dana Whitfield', 'document', 'Dana Whitfield uploaded a document: Security checklist', 'From our compliance team.'],
                    [-35, '17:00', 'team', 'Priya Nair', 'step', 'Step completed: Requirements', 'Finished on time in 8 working days.'],
                    [-25, '11:00', 'client', 'Dana Whitfield', 'requirement', 'Dana Whitfield raised a new requirement: Add Hindi language support', 'Rural branches need the app in Hindi.'],
                    [-23, '10:30', 'team', 'Priya Nair', 'requirement', 'Requirement approved: Add Hindi language support', 'Adds 5 working days and 30 hours.'],
                    [-20, '12:00', 'team', 'Priya Nair', 'request', 'Waiting on you: Payment gateway sandbox keys', 'Needed to build and test bill payments.'],
                    [-18, '16:00', 'team', 'Karthik Rao', 'step', 'Step completed: Design', 'Finished 3 working days after the deadline.'],
                    [-14, '10:10', 'team', 'Priya Nair', 'requirement', 'Requirement rejected: Add a crypto wallet', 'Out of scope for this release.'],
                    [-8, '09:45', 'team', 'Priya Nair', 'status', 'Project status: Delayed', 'Mobile Banking App is now 38% complete.'],
                    [-6, '11:20', 'team', 'Priya Nair', 'update', 'Reminder sent: Payment gateway sandbox keys', 'Second reminder by email.'],
                ],
                'chat' => [
                    [-44, '09:15', 'team', 'Welcome Dana. This thread is the quickest way to reach the delivery team.'],
                    [-20, '12:05', 'team', 'We need the payment gateway sandbox keys to continue the bill payments work. Can your IT team send them?'],
                    [-19, '09:40', 'client', 'I have asked IT. They need a security approval first.'],
                    [-8, '10:00', 'team', 'Quick flag: without the keys, development is now about 8 working days behind. We can still deliver if we get them this week.'],
                    [-2, '14:30', 'client', 'Understood. I will chase IT today and update you here.'],
                ],
            ],
            // 3. Delivered: every step is complete and the project is handed over.
            [
                'key' => 'northwind', 'client' => $clients['northwind'], 'name' => 'Marketing Website',
                'description' => 'A fast, editable marketing website with a blog and case studies.',
                'start' => -80, 'due' => -12, 'stage' => 'handover', 'status' => 'completed',
                'plan' => ['done', 'done', 'late:2', 'done', 'done', 'done'],
                'activities' => [
                    [-79, 'Requirements and sitemap', 'Pages, blog and case studies agreed.', 8, 8, 'completed', 8],
                    [-70, 'Design: home, product and blog', 'Three page designs approved.', 24, 22, 'completed', 25],
                    [-58, 'Build: pages and blog', 'Responsive build with the editor.', 40, 36, 'completed', 55],
                    [-45, 'Case study templates', 'Reusable templates for case studies.', 16, 14, 'completed', 70],
                    [-32, 'Testing and fixes', 'Cross-browser testing, speed fixes.', 18, 16, 'completed', 85],
                    [-18, 'Launch to production', 'DNS switch and monitoring set up.', 10, 8, 'completed', 95],
                    [-13, 'Handover and training', 'Training session and handover notes.', 6, 6, 'completed', 100],
                ],
                'meetings' => [
                    [-79, 'Kickoff', 'Sitemap and timeline.', "12 pages.\nLaunch before the trade fair."],
                    [-13, 'Handover and training', 'Editor training for the marketing team.', "Recording shared.\nSupport for 30 days."],
                ],
                'documents' => [
                    ['Handover notes', 'handover-notes.txt', "Hosting, logins and how to publish a blog post.\n", 'Everything the marketing team needs.', true, false, -13],
                ],
                'changes' => [
                    ['Add a case study filter', 'Filter case studies by industry.', -50, 'approved', -49, 2, 10, 'client'],
                ],
                'dependencies' => [
                    ['Final copy for the home page', 'Approved text for all home page sections.', -75, -68, -66],
                ],
                'history' => [
                    [-80, '09:30', 'team', 'Priya Nair', 'stage', 'Project moved to Requirements', 'Project created and plan set up.'],
                    [-49, '10:00', 'team', 'Priya Nair', 'requirement', 'Requirement approved: Add a case study filter', 'Adds 2 working days and 10 hours.'],
                    [-30, '15:15', 'team', 'Karthik Rao', 'step', 'Step completed: Development', 'Finished 2 working days after the deadline.'],
                    [-18, '18:00', 'team', 'Ravi Menon', 'step', 'Step completed: Deployment', 'Finished on time in 4 working days.'],
                    [-13, '13:00', 'team', 'Priya Nair', 'document', 'New document: Handover notes', 'Everything the marketing team needs.'],
                    [-12, '12:00', 'team', 'Priya Nair', 'status', 'Project marked as completed', null],
                ],
                'chat' => [
                    [-13, '13:05', 'team', 'The site is live and the handover notes are in Documents. Thank you for a smooth project.'],
                    [-13, '13:30', 'client', 'Fantastic work. The team loves the editor.'],
                    [-12, '09:10', 'team', 'Glad to hear it. We are here for 30 days of support if anything comes up.'],
                ],
            ],
            // 4. Seriously late, and the client has no portal access: updates go out by WhatsApp.
            [
                'key' => 'initech', 'client' => $clients['initech'], 'name' => 'ERP Integration',
                'description' => 'Connect the legacy ERP to the new warehouse and invoicing systems.',
                'start' => -60, 'due' => -6, 'stage' => 'testing', 'status' => 'active',
                'plan' => ['done', 'done', 'late:4', 'doing', 'todo', 'todo'],
                'activities' => [
                    [-58, 'Systems discovery', 'Documented the ERP data model.', 14, 12, 'completed', 6],
                    [-48, 'Integration design', 'Message formats and error handling.', 20, 18, 'completed', 18],
                    [-35, 'Warehouse sync build', 'Stock levels sync every five minutes.', 36, 30, 'completed', 36],
                    [-24, 'Invoicing sync build', 'Invoices pushed to the finance system.', 30, 24, 'completed', 50],
                    [-9, 'Integration testing', 'Found mismatches in tax codes; fixing.', 16, 12, 'in_progress', 62],
                ],
                'meetings' => [
                    [-9, 'Testing status call', 'Review of mismatched tax codes.', "Finance must confirm the tax mapping.\nUAT sign-off is the blocker."],
                ],
                'documents' => [
                    ['Integration design', 'integration-design.txt', "Message formats, retries and monitoring.\n", 'Internal design note.', false, false, -47],
                ],
                'changes' => [],
                'dependencies' => [
                    ['UAT sign-off from finance', 'Finance must test and sign off the invoice sync.', -30, -20, null],
                ],
                'history' => [
                    [-60, '09:00', 'team', 'Priya Nair', 'stage', 'Project moved to Requirements', 'Project created and plan set up.'],
                    [-30, '12:00', 'team', 'Priya Nair', 'request', 'Waiting on you: UAT sign-off from finance', 'Finance must test and sign off the invoice sync.'],
                    [-10, '10:00', 'team', 'Priya Nair', 'status', 'Project status: Significantly delayed', 'ERP Integration is now 62% complete.'],
                ],
                'chat' => [],
            ],
            // 5. Just created: the plan exists, nothing has been logged yet.
            [
                'key' => 'globex', 'client' => $clients['globex'], 'name' => 'HR Self-Service',
                'description' => 'Employee self-service for leave, payslips and policies.',
                'start' => 0, 'due' => 60, 'stage' => 'requirements', 'status' => 'active',
                'plan' => null,
                'activities' => [],
                'meetings' => [
                    [0, 'Kickoff call', 'Introductions and plan walk-through.', "Plan shared.\nWeekly demo every Wednesday."],
                ],
                'documents' => [],
                'changes' => [],
                'dependencies' => [
                    ['Current leave policy document', 'We need this to design leave rules.', 0, 5, null],
                ],
                'history' => [
                    [0, '09:30', 'team', 'Priya Nair', 'stage', 'Project moved to Requirements', 'Project created and plan set up.'],
                    [0, '09:32', 'team', 'Priya Nair', 'request', 'Waiting on you: Current leave policy document', 'We need this to design leave rules.'],
                ],
                'chat' => [
                    [0, '09:35', 'team', 'Welcome to your new project, Dana. The plan is on this page; please upload the current leave policy when you can.'],
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $spec */
    private function buildProject(array $spec): void
    {
        $tracker = app(ProjectTrackerService::class);
        $planner = app(MilestonePlanner::class);

        $project = Project::create([
            'name' => $spec['name'],
            'description' => $spec['description'],
            'client_id' => $spec['client']->id,
            'client_name' => $spec['client']->name,
            'start_date' => today()->addDays($spec['start'])->toDateString(),
            'due_date' => today()->addDays($spec['due'])->toDateString(),
            'status' => $spec['status'],
            'stage' => $spec['stage'],
            'status_updated_at' => now(),
        ]);

        $planner->seedDefaults($project);
        if ($spec['plan'] !== null) {
            $this->applyPlan($project, $spec['plan']);
        }

        foreach ($spec['activities'] as [$day, $title, $text, $hours, $estimate, $status, $progress]) {
            $project->activities()->create([
                'activity_date' => today()->addDays($day)->toDateString(), 'title' => $title, 'description' => $text,
                'hours_spent' => $hours, 'days_spent' => round($hours / 8, 2), 'estimated_hours' => $estimate, 'status' => $status,
                'progress_percent' => $progress, 'created_by' => $this->team['dev']->id,
            ]);
        }
        $this->addAnalysis($project);

        foreach ($spec['meetings'] as [$day, $title, $details, $points]) {
            $project->meetings()->create(['meeting_date' => today()->addDays($day)->toDateString(), 'title' => $title, 'details' => $details, 'discussion_points' => $points, 'links' => [], 'created_by' => $this->team['pm']->id]);
        }

        foreach ($spec['documents'] as $doc) {
            $this->addDocument($project, ...$doc);
        }

        $clientUser = $this->portal[$spec['key']] ?? null;

        foreach ($spec['changes'] as [$title, $text, $submitted, $status, $decided, $days, $hours, $by]) {
            $project->changeRequests()->create([
                'title' => $title, 'description' => $text, 'submitted_on' => today()->addDays($submitted)->toDateString(), 'status' => $status,
                'decided_on' => $decided === null ? null : today()->addDays($decided)->toDateString(), 'additional_days' => $days, 'additional_hours' => $hours,
                'created_by' => ($by === 'client' ? $clientUser?->id : null) ?? $this->team['pm']->id,
            ]);
        }

        foreach ($spec['dependencies'] as [$title, $text, $requested, $expected, $received]) {
            $project->clientDependencies()->create([
                'title' => $title, 'description' => $text, 'requested_on' => today()->addDays($requested)->toDateString(), 'expected_on' => today()->addDays($expected)->toDateString(),
                'received_on' => $received === null ? null : today()->addDays($received)->toDateString(), 'created_by' => $this->team['pm']->id,
            ]);
        }

        $tracker->refreshRevisedDeadline($project);
        $project->refresh();
        $summary = $tracker->summary($project);
        $project->forceFill(['progress' => $summary['progress_percent'], 'tracking_status' => $summary['status'], 'status_updated_at' => now()])->saveQuietly();

        $this->addHistory($project, $spec['history']);
        $this->addChat($project, $spec['chat'], $clientUser);
        $this->addNotifications($project, $clientUser, $spec['key']);
    }

    /**
     * Turns the default plan into the project's real history, using each step's planned dates:
     * on-time steps finish on their deadline, late ones a few working days after it.
     *
     * @param  list<string>  $codes
     */
    private function applyPlan(Project $project, array $codes): void
    {
        $steps = $project->milestones()->get()->values();

        foreach ($steps as $i => $step) {
            /** @var ProjectMilestone $step */
            $code = $codes[$i] ?? 'todo';

            if ($code === 'todo') {
                $step->forceFill(['status' => 'pending', 'started_on' => null, 'completed_on' => null])->save();
            } elseif ($code === 'doing') {
                $step->forceFill(['status' => 'in_progress', 'started_on' => $step->planned_start, 'completed_on' => null])->save();
            } else {
                $late = str_starts_with($code, 'late:') ? (int) substr($code, 5) : 0;
                $step->forceFill([
                    'status' => 'completed',
                    'started_on' => $step->planned_start,
                    'completed_on' => $late > 0 ? ProjectTrackerService::addWorkingDays($step->due_date->copy(), $late) : $step->due_date,
                ])->save();
            }
        }
    }

    /** Stores a short AI-style note on the latest activity so the project page shows the analysis panel. */
    private function addAnalysis(Project $project): void
    {
        $latest = $project->activities()->orderByDesc('activity_date')->orderByDesc('id')->first();
        if ($latest === null) {
            return;
        }

        $latest->forceFill([
            'next_step_estimate_hours' => 10,
            'ai_analysis' => "Sample analysis: {$project->name} is {$latest->progress_percent}% complete. The next step is estimated at about 10 hours based on similar steps.",
        ])->save();
        $project->forceFill(['ai_summary' => $latest->ai_analysis])->saveQuietly();
    }

    private function addDocument(Project $project, string $title, string $fileName, string $content, string $note, bool $visible, bool $fromClient, int $day): void
    {
        $path = "tenants/{$this->tenant->uuid}/documents/{$project->id}/".Str::uuid().'.'.pathinfo($fileName, PATHINFO_EXTENSION);
        Storage::disk('local')->put($path, $content);

        $document = $project->documents()->create([
            'uploaded_by' => $fromClient ? ($this->portal[$this->clientKey($project)] ?? $this->team['pm'])->id : $this->team['pm']->id,
            'title' => $title, 'note' => $note, 'name' => $fileName, 'path' => $path,
            'mime_type' => str_ends_with($fileName, '.csv') ? 'text/csv' : 'text/plain', 'size' => strlen($content),
            'visible_to_client' => $visible, 'from_client' => $fromClient,
        ]);
        $document->forceFill(['created_at' => $this->at($day, '10:00'), 'updated_at' => $this->at($day, '10:00')])->save();
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string|null}>  $rows
     */
    private function addHistory(Project $project, array $rows): void
    {
        foreach ($rows as [$day, $time, $actor, $name, $kind, $title, $body]) {
            ProjectEvent::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'user_id' => $this->userNamed($name)?->id,
                'actor' => $actor, 'actor_name' => $name, 'kind' => $kind, 'title' => $title, 'body' => $body,
                'created_at' => $this->at($day, $time), 'updated_at' => $this->at($day, $time),
            ]);
        }
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3: string}>  $rows
     */
    private function addChat(Project $project, array $rows, ?User $clientUser): void
    {
        foreach ($rows as [$day, $time, $sender, $text]) {
            $author = $sender === 'client' ? $clientUser : $this->team['pm'];
            $at = $this->at($day, $time);

            ProjectChatMessage::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'user_id' => $author?->id, 'sender' => $sender,
                'sender_name' => $author?->name, 'body' => $text, 'created_at' => $at, 'updated_at' => $at,
            ]);

            // The conversation is part of the project history as well.
            ProjectEvent::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'user_id' => $author?->id, 'actor' => $sender, 'actor_name' => $author?->name,
                'kind' => 'message', 'title' => ($author?->name ?? 'Someone').' sent a message', 'body' => $text, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    /** A few unread bell notifications on each side so the notification screens have something to show. */
    private function addNotifications(Project $project, ?User $clientUser, string $key): void
    {
        $latest = ProjectEvent::withoutGlobalScopes()->where('project_id', $project->id)->latest('created_at')->limit(2)->get();

        foreach ($latest as $event) {
            $notification = new ClientProjectUpdate($project, $event->kind, $event->title, $event->body);

            if ($event->actor === 'team' && $clientUser !== null) {
                Notification::send($clientUser, $notification);
            } elseif ($event->actor === 'client') {
                Notification::send([$this->team['admin'], $this->team['pm']], $notification);
            }
        }
    }

    /** @param  array<string, Client>  $clients */
    private function createEmailDraft(array $clients): void
    {
        $project = Project::where('name', 'Mobile Banking App')->first();
        if ($project === null) {
            return;
        }

        ClientMessage::create([
            'project_id' => $project->id, 'client_id' => $clients['globex']->id, 'created_by' => $this->team['pm']->id, 'channel' => 'email', 'direction' => 'out',
            'reason' => 'dependency_overdue', 'subject' => 'Mobile Banking App: sandbox keys needed this week',
            'body' => "Hi Dana,\n\nWe are still waiting for the payment gateway sandbox keys. Development of the payments flow is paused until we receive them, and the project is now about 8 working days behind.\n\nCould your IT team send the keys by Thursday so we can protect the delivery date?\n\nThank you,\nPriya Nair",
            'to_address' => $clients['globex']->email, 'status' => 'draft', 'ai_generated' => false,
        ]);
    }

    private function clientKey(Project $project): string
    {
        return Client::find($project->client_id)?->email === 'dana@globex.test' ? 'globex' : 'northwind';
    }

    private function userNamed(string $name): ?User
    {
        return collect($this->team + $this->portal)->first(fn (User $u) => $u->name === $name);
    }

    private function at(int $day, string $time): Carbon
    {
        return today()->addDays($day)->setTimeFromTimeString($time);
    }
}

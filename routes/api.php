<?php

use App\Http\Controllers\Api\V1\Admin\CountryController;
use App\Http\Controllers\Api\V1\Admin\CurrencyController;
use App\Http\Controllers\Api\V1\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\StatsController;
use App\Http\Controllers\Api\V1\Admin\SystemSettingController;
use App\Http\Controllers\Api\V1\Admin\TenantController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtpAuthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\RegistrationPaymentController;
use App\Http\Controllers\Api\V1\Tracker\ActivityController;
use App\Http\Controllers\Api\V1\Tracker\ChangeRequestController;
use App\Http\Controllers\Api\V1\Tracker\ClientDependencyController;
use App\Http\Controllers\Api\V1\Tracker\ClientMessageController;
use App\Http\Controllers\Api\V1\Tracker\MeetingController;
use App\Http\Controllers\Api\V1\Tracker\MilestoneController;
use App\Http\Controllers\Api\V1\Tracker\ProjectDocumentController;
use App\Http\Controllers\Api\V1\Tracker\ProjectOnboardingController;
use App\Http\Controllers\Api\V1\Tracker\ProjectReportController;
use App\Http\Controllers\Api\V1\Tracker\ProjectThreadController;
use App\Http\Controllers\Api\V1\Tracker\ProjectTrackerController;
use App\Http\Controllers\Api\V1\WhatsAppChatController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\WorkspaceSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Spider PLMS
|--------------------------------------------------------------------------
| Public:        config, pricing (per country), plans, auth/register, auth/login
| Signed in:     auth/me, profile, notifications
| Workspace:     auth:sanctum + tenant + tenant.required (project tracker)
| Platform:      /api/v1/admin/* — Super Admins only (no tenant context needed)
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // ---- Public ----
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('public/config', [PublicController::class, 'config'])->name('public.config');
    Route::get('public/workspaces/{slug}', [OtpAuthController::class, 'workspace'])->name('public.workspace');
    Route::get('public/pricing', [PublicController::class, 'pricing'])->name('public.pricing');

    // Evolution API webhook: public, but every delivery must carry the secret token.
    Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhooks.whatsapp.receive');

    Route::middleware('throttle:auth')->prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register/order', [RegistrationPaymentController::class, 'order'])->name('register.order');
        Route::post('register/verify', [RegistrationPaymentController::class, 'verify'])->name('register.verify');
        Route::post('otp/request', [OtpAuthController::class, 'request'])->name('otp.request');
        Route::post('otp/verify', [OtpAuthController::class, 'verify'])->name('otp.verify');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function (): void {

        // ---- Any signed-in user ----
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::patch('auth/profile', [ProfileController::class, 'update'])->name('auth.profile');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('auth/sessions/revoke-others', [ProfileController::class, 'revokeOtherSessions'])->name('auth.sessions.revoke-others');

        // ---- Platform (Super Admin) ----
        Route::middleware('super-admin')->prefix('admin')->name('admin.')->group(function (): void {
            Route::get('stats', StatsController::class)->name('stats');
            Route::apiResource('tenants', TenantController::class)->except([]);
            Route::apiResource('countries', CountryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('currencies', CurrencyController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('settings', [SystemSettingController::class, 'show'])->name('settings.show');
            Route::put('settings', [SystemSettingController::class, 'update'])->name('settings.update');
            Route::get('plans', [AdminPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [AdminPlanController::class, 'store'])->name('plans.store');
            Route::put('plans/prices', [AdminPlanController::class, 'syncAllPrices'])->name('plans.prices');
            Route::patch('plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy'])->name('plans.destroy');
        });

        // ---- Workspace (needs a tenant) ----
        Route::middleware('tenant.required')->group(function (): void {

            // ---- Client portal: a client signs in and sees only their own projects ----
            Route::middleware('client')->prefix('portal')->name('portal.')->group(function (): void {
                Route::get('projects', [PortalController::class, 'projects'])->name('projects.index');
                Route::get('projects/{project}', [PortalController::class, 'project'])->name('projects.show');
                Route::get('documents/{document}/download', [PortalController::class, 'downloadDocument'])->name('documents.download');
                Route::post('projects/{project}/documents', [PortalController::class, 'uploadDocument'])->name('documents.store');
                Route::post('projects/{project}/updates', [PortalController::class, 'postUpdate'])->name('updates.store');
                Route::post('projects/{project}/requirements', [PortalController::class, 'raiseRequirement'])->name('requirements.store');
                Route::get('projects/{project}/chat', [PortalController::class, 'chat'])->name('chat.index');
                Route::post('projects/{project}/chat', [PortalController::class, 'sendChatMessage'])->name('chat.store');
            });
        });

        // ---- Team workspace: closed to client portal accounts ----
        Route::middleware(['tenant.required', 'staff'])->group(function (): void {
            // ---- Client master data and workspace settings ----
            Route::apiResource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('clients/{client}/portal-access', [ClientController::class, 'grantPortalAccess'])->name('clients.portal.grant');
            Route::delete('clients/{client}/portal-access', [ClientController::class, 'revokePortalAccess'])->name('clients.portal.revoke');
            Route::get('workspace/settings', [WorkspaceSettingsController::class, 'show'])->name('workspace.settings.show');
            Route::put('workspace/settings', [WorkspaceSettingsController::class, 'update'])->name('workspace.settings.update');

            // ---- WhatsApp inbox: status is open to every plan, the rest needs the plan's WhatsApp chat ----
            Route::get('whatsapp/status', [WhatsAppChatController::class, 'status'])->name('whatsapp.status');
            Route::middleware('plan.feature:whatsapp_chat')->prefix('whatsapp')->name('whatsapp.')->group(function (): void {
                Route::post('connect', [WhatsAppChatController::class, 'connect'])->name('connect');
                Route::post('disconnect', [WhatsAppChatController::class, 'disconnect'])->name('disconnect');
                Route::get('conversations', [WhatsAppChatController::class, 'conversations'])->name('conversations');
                Route::get('conversations/{client}', [WhatsAppChatController::class, 'thread'])->name('thread');
                Route::post('conversations/{client}/messages', [WhatsAppChatController::class, 'send'])->name('send');
            });

            // ---- Simplified project tracking ----
            Route::get('tracker/dashboard', [ProjectTrackerController::class, 'dashboard'])->name('tracker.dashboard');
            Route::get('tracker/projects', [ProjectTrackerController::class, 'index'])->name('tracker.projects.index');
            Route::post('tracker/onboarding/analyse', [ProjectOnboardingController::class, 'analyse'])->middleware('throttle:10,1')->name('tracker.onboarding.analyse');
            Route::post('tracker/projects', [ProjectTrackerController::class, 'store'])->name('tracker.projects.store');
            Route::get('tracker/projects/{project}', [ProjectTrackerController::class, 'show'])->name('tracker.projects.show');
            Route::patch('tracker/projects/{project}', [ProjectTrackerController::class, 'update'])->name('tracker.projects.update');
            Route::delete('tracker/projects/{project}', [ProjectTrackerController::class, 'destroy'])->name('tracker.projects.destroy');
            Route::post('tracker/projects/{project}/analyse', [ProjectTrackerController::class, 'analyse'])->name('tracker.projects.analyse');
            Route::get('tracker/projects/{project}/reports/{type}', [ProjectReportController::class, 'download'])->name('tracker.reports');

            Route::post('tracker/projects/{project}/milestones/generate', [MilestoneController::class, 'generate'])->name('tracker.milestones.generate');
            Route::post('tracker/projects/{project}/milestones', [MilestoneController::class, 'store'])->name('tracker.milestones.store');
            Route::patch('tracker/milestones/{milestone}', [MilestoneController::class, 'update'])->name('tracker.milestones.update');
            Route::delete('tracker/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('tracker.milestones.destroy');

            Route::post('tracker/projects/{project}/activities', [ActivityController::class, 'store'])->name('tracker.activities.store');
            Route::patch('tracker/activities/{activity}', [ActivityController::class, 'update'])->name('tracker.activities.update');
            Route::delete('tracker/activities/{activity}', [ActivityController::class, 'destroy'])->name('tracker.activities.destroy');

            Route::post('tracker/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('tracker.documents.store');
            Route::get('tracker/documents/{document}/download', [ProjectDocumentController::class, 'download'])->name('tracker.documents.download');
            Route::delete('tracker/documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('tracker.documents.destroy');

            Route::post('tracker/projects/{project}/meetings', [MeetingController::class, 'store'])->name('tracker.meetings.store');
            Route::post('tracker/meetings/{meeting}', [MeetingController::class, 'update'])->name('tracker.meetings.update');
            Route::delete('tracker/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('tracker.meetings.destroy');
            Route::get('tracker/attachments/{attachment}/download', [MeetingController::class, 'download'])->name('tracker.attachments.download');
            Route::delete('tracker/attachments/{attachment}', [MeetingController::class, 'destroyAttachment'])->name('tracker.attachments.destroy');

            // ---- Client messages (AI drafts, reviewed before sending) and WhatsApp chat ----
            Route::get('tracker/messages', [ClientMessageController::class, 'index'])->name('tracker.messages.index');
            Route::post('tracker/projects/{project}/messages/draft', [ClientMessageController::class, 'draft'])->name('tracker.messages.draft');
            Route::patch('tracker/messages/{message}', [ClientMessageController::class, 'update'])->name('tracker.messages.update');
            Route::post('tracker/messages/{message}/send', [ClientMessageController::class, 'send'])->name('tracker.messages.send');
            Route::delete('tracker/messages/{message}', [ClientMessageController::class, 'destroy'])->name('tracker.messages.destroy');
            Route::get('tracker/projects/{project}/thread', [ProjectThreadController::class, 'index'])->name('tracker.thread.index');
            Route::post('tracker/projects/{project}/thread', [ProjectThreadController::class, 'store'])->name('tracker.thread.store');
            Route::get('tracker/projects/{project}/chat', [ClientMessageController::class, 'chat'])->name('tracker.chat.show');
            Route::post('tracker/projects/{project}/chat/evaluate', [ClientMessageController::class, 'evaluateChat'])->name('tracker.chat.evaluate');

            Route::post('tracker/projects/{project}/change-requests', [ChangeRequestController::class, 'store'])->name('tracker.changes.store');
            Route::patch('tracker/change-requests/{changeRequest}', [ChangeRequestController::class, 'update'])->name('tracker.changes.update');
            Route::delete('tracker/change-requests/{changeRequest}', [ChangeRequestController::class, 'destroy'])->name('tracker.changes.destroy');

            Route::post('tracker/projects/{project}/client-dependencies', [ClientDependencyController::class, 'store'])->name('tracker.dependencies.store');
            Route::patch('tracker/client-dependencies/{dependency}', [ClientDependencyController::class, 'update'])->name('tracker.dependencies.update');
            Route::delete('tracker/client-dependencies/{dependency}', [ClientDependencyController::class, 'destroy'])->name('tracker.dependencies.destroy');
        });
    });
});

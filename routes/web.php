<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SenderIdController as AdminSenderIdController;
use App\Http\Controllers\Admin\TemplateApprovalController;
use App\Http\Controllers\Admin\TrafficController as AdminTrafficController;
use App\Http\Controllers\Api\DeliveryReportController;
use App\Http\Controllers\Client\ApiCredentialController;
use App\Http\Controllers\Client\BillingController;
use App\Http\Controllers\Client\CampaignController;
use App\Http\Controllers\Client\ContactController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\ReportController as ClientReportController;
use App\Http\Controllers\Client\SenderIdController as ClientSenderIdController;
use App\Http\Controllers\Client\SendSmsController;
use App\Http\Controllers\Client\TeamController;
use App\Http\Controllers\Client\TemplateController;
use App\Http\Controllers\PortalRedirectController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::post('/dlr/jasmin', [DeliveryReportController::class, 'jasmin'])->name('dlr.jasmin');

Route::get('/dashboard', PortalRedirectController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'platform'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::resource('clients', AdminClientController::class);
    Route::post('clients/{client}/approve', [AdminClientController::class, 'approve'])->name('clients.approve');
    Route::post('clients/{client}/suspend', [AdminClientController::class, 'suspend'])->name('clients.suspend');

    Route::get('credits', [AdminCreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [AdminCreditController::class, 'store'])->name('credits.store');

    Route::get('sender-ids', [AdminSenderIdController::class, 'index'])->name('sender-ids.index');
    Route::post('sender-ids/{senderId}/approve', [AdminSenderIdController::class, 'approve'])->name('sender-ids.approve');
    Route::post('sender-ids/{senderId}/reject', [AdminSenderIdController::class, 'reject'])->name('sender-ids.reject');
    Route::post('sender-ids/{senderId}/suspend', [AdminSenderIdController::class, 'suspend'])->name('sender-ids.suspend');

    Route::get('providers', [AdminProviderController::class, 'index'])->name('providers.index');
    Route::post('providers', [AdminProviderController::class, 'store'])->name('providers.store');

    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');

    Route::get('traffic', [AdminTrafficController::class, 'index'])->name('traffic.index');
    Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('approvals', [TemplateApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/templates/{template}/approve', [TemplateApprovalController::class, 'approve'])->name('approvals.templates.approve');
    Route::post('approvals/templates/{template}/reject', [TemplateApprovalController::class, 'reject'])->name('approvals.templates.reject');
});

Route::middleware(['auth', 'verified', 'client'])->prefix('portal/{client}')->name('client.')->group(function () {
    Route::get('/', ClientDashboardController::class)->name('dashboard');

    Route::get('send', [SendSmsController::class, 'create'])->name('send.create');
    Route::post('send/preview', [SendSmsController::class, 'preview'])->name('send.preview');
    Route::post('send', [SendSmsController::class, 'store'])->name('send.store');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::get('campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');

    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');

    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');

    Route::get('reports', [ClientReportController::class, 'index'])->name('reports.index');
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->name('team.store');

    Route::get('api', [ApiCredentialController::class, 'index'])->name('api.index');
    Route::post('api', [ApiCredentialController::class, 'store'])->name('api.store');
    Route::delete('api/{credential}', [ApiCredentialController::class, 'revoke'])->name('api.revoke');

    Route::get('sender-ids', [ClientSenderIdController::class, 'index'])->name('sender-ids.index');
    Route::post('sender-ids', [ClientSenderIdController::class, 'store'])->name('sender-ids.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

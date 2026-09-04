<?php

use App\Modules\Analytics\Http\Controllers\OperationsDashboardController;
use App\Modules\Analytics\Http\Controllers\TodayController;
use App\Modules\Assessments\Http\Controllers\AssessmentResponseController;
use App\Modules\Assessments\Http\Controllers\AssessmentTemplateController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Billing\Http\Controllers\FunderController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\CarePlanning\Http\Controllers\CarePlanController;
use App\Modules\Communication\Http\Controllers\AnnouncementController;
use App\Modules\Compliance\Http\Controllers\ComplianceDocumentController;
use App\Modules\Compliance\Http\Controllers\ComplianceRequirementController;
use App\Modules\Documents\Http\Controllers\DocumentController;
use App\Modules\Hr\Http\Controllers\LeaveRequestController;
use App\Modules\Hr\Http\Controllers\StaffDocumentController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\UserRoleController;
use App\Modules\Incidents\Http\Controllers\IncidentController;
use App\Modules\Medications\Http\Controllers\MedicationAdministrationController;
use App\Modules\Medications\Http\Controllers\MedicationController;
use App\Modules\Observations\Http\Controllers\ClinicalAlertController;
use App\Modules\Observations\Http\Controllers\ObservationController;
use App\Modules\Organization\Http\Controllers\BranchController;
use App\Modules\Organization\Http\Controllers\DepartmentController;
use App\Modules\Organization\Http\Controllers\TenantController;
use App\Modules\Payroll\Http\Controllers\PayPeriodController;
use App\Modules\Reports\Http\Controllers\ReportGeneratorController;
use App\Modules\Payroll\Http\Controllers\PayslipController;
use App\Modules\Rostering\Http\Controllers\ShiftController;
use App\Modules\Safeguarding\Http\Controllers\SafeguardingCaseController;
use App\Modules\ServiceUsers\Http\Controllers\FamilyPortalController;
use App\Modules\ServiceUsers\Http\Controllers\ServiceUserContactController;
use App\Modules\ServiceUsers\Http\Controllers\ServiceUserController;
use App\Modules\Staff\Http\Controllers\StaffController;
use App\Modules\Tracking\Http\Controllers\CarerLocationController;
use App\Modules\Tracking\Http\Controllers\DutyPeriodController;
use App\Modules\Training\Http\Controllers\TrainingCourseController;
use App\Modules\Training\Http\Controllers\TrainingRecordController;
use App\Modules\Visits\Http\Controllers\RouteController;
use App\Modules\Visits\Http\Controllers\VisitCheckInController;
use App\Modules\Visits\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // A Family Member login only ever reaches these — everything else in
        // this file lives inside the nested 'staff-only' group below, which
        // 403s a Family Member outright (see App\Http\Middleware\StaffOnly).
        Route::prefix('family-portal')->group(function () {
            Route::get('/', [FamilyPortalController::class, 'index']);
            Route::get('/{serviceUser}', [FamilyPortalController::class, 'show']);
        });

        Route::middleware(['staff-only'])->group(function () {
            Route::prefix('organizations')->group(function () {
                Route::get('tenants', [TenantController::class, 'index']);
                Route::post('tenants', [TenantController::class, 'store']);
                Route::get('tenants/{tenant}', [TenantController::class, 'show']);
                Route::patch('tenants/{tenant}', [TenantController::class, 'update']);
                Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus']);
                Route::get('tenants/{tenant}/branches', [BranchController::class, 'index']);
                Route::post('tenants/{tenant}/branches', [BranchController::class, 'store']);
                Route::patch('tenants/{tenant}/branches/{branch}', [BranchController::class, 'update']);
                Route::patch('tenants/{tenant}/branches/{branch}/status', [BranchController::class, 'updateStatus']);
                Route::get('tenants/{tenant}/departments', [DepartmentController::class, 'index']);
                Route::post('tenants/{tenant}/departments', [DepartmentController::class, 'store']);
            });

            Route::get('/audit-log', [AuditLogController::class, 'index']);

            Route::prefix('service-users')->group(function () {
                Route::get('/', [ServiceUserController::class, 'index']);
                Route::post('/', [ServiceUserController::class, 'store']);
                Route::get('/{serviceUser}', [ServiceUserController::class, 'show']);
                Route::patch('/{serviceUser}', [ServiceUserController::class, 'update']);
                Route::delete('/{serviceUser}', [ServiceUserController::class, 'destroy']);

                Route::get('/{serviceUser}/contacts', [ServiceUserContactController::class, 'index']);
                Route::post('/{serviceUser}/contacts', [ServiceUserContactController::class, 'store']);
                Route::delete('/{serviceUser}/contacts/{contact}', [ServiceUserContactController::class, 'destroy']);
                Route::post('/{serviceUser}/contacts/{contact}/grant-portal-access', [ServiceUserContactController::class, 'grantPortalAccess']);

                Route::get('/{serviceUser}/care-plans', [CarePlanController::class, 'index']);
                Route::post('/{serviceUser}/care-plans', [CarePlanController::class, 'store']);

                Route::get('/{serviceUser}/assessment-responses', [AssessmentResponseController::class, 'index']);
                Route::post('/{serviceUser}/assessment-responses', [AssessmentResponseController::class, 'store']);

                Route::get('/{serviceUser}/documents', [DocumentController::class, 'index']);
                Route::post('/{serviceUser}/documents', [DocumentController::class, 'store']);

                Route::get('/{serviceUser}/medications', [MedicationController::class, 'index']);
                Route::post('/{serviceUser}/medications', [MedicationController::class, 'store']);

                Route::get('/{serviceUser}/observations', [ObservationController::class, 'index']);
                Route::post('/{serviceUser}/observations', [ObservationController::class, 'store']);

                Route::get('/{serviceUser}/clinical-alerts', [ClinicalAlertController::class, 'index']);
            });

            Route::get('/care-plans/{carePlan}', [CarePlanController::class, 'show']);

            Route::get('/assessment-templates', [AssessmentTemplateController::class, 'index']);
            Route::post('/assessment-templates', [AssessmentTemplateController::class, 'store']);
            Route::get('/assessment-responses/{assessmentResponse}', [AssessmentResponseController::class, 'show']);

            Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
            Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

            Route::prefix('medications')->group(function () {
                Route::get('/{medication}', [MedicationController::class, 'show']);
                Route::patch('/{medication}', [MedicationController::class, 'update']);
                Route::get('/{medication}/administrations', [MedicationAdministrationController::class, 'index']);
                Route::post('/{medication}/administrations', [MedicationAdministrationController::class, 'store']);
            });

            Route::get('/observations/{observation}', [ObservationController::class, 'show']);

            Route::post('/clinical-alerts/{alert}/acknowledge', [ClinicalAlertController::class, 'acknowledge']);

            Route::prefix('incidents')->group(function () {
                Route::get('/', [IncidentController::class, 'index']);
                Route::post('/', [IncidentController::class, 'store']);
                Route::get('/{incident}', [IncidentController::class, 'show']);
                Route::patch('/{incident}', [IncidentController::class, 'update']);
            });

            Route::prefix('safeguarding-cases')->group(function () {
                Route::get('/', [SafeguardingCaseController::class, 'index']);
                Route::post('/', [SafeguardingCaseController::class, 'store']);
                Route::get('/{safeguardingCase}', [SafeguardingCaseController::class, 'show']);
                Route::patch('/{safeguardingCase}', [SafeguardingCaseController::class, 'update']);
            });

            Route::prefix('staff')->group(function () {
                Route::get('/', [StaffController::class, 'index']);
                Route::post('/', [StaffController::class, 'store']);
                Route::get('/{staff}', [StaffController::class, 'show']);
                Route::patch('/{staff}', [StaffController::class, 'update']);

                Route::get('/{staff}/documents', [StaffDocumentController::class, 'index']);
                Route::post('/{staff}/documents', [StaffDocumentController::class, 'store']);
            });

            Route::prefix('user-roles')->group(function () {
                Route::get('/', [UserRoleController::class, 'index']);
                Route::post('/', [UserRoleController::class, 'store']);
                Route::patch('/{user}', [UserRoleController::class, 'update']);
            });

            Route::prefix('funders')->group(function () {
                Route::get('/', [FunderController::class, 'index']);
                Route::post('/', [FunderController::class, 'store']);
                Route::get('/{funder}', [FunderController::class, 'show']);
                Route::patch('/{funder}', [FunderController::class, 'update']);
            });

            Route::prefix('invoices')->group(function () {
                Route::get('/', [InvoiceController::class, 'index']);
                Route::post('/generate', [InvoiceController::class, 'generate']);
                Route::get('/{invoice}', [InvoiceController::class, 'show']);
                Route::patch('/{invoice}', [InvoiceController::class, 'update']);
            });

            Route::prefix('pay-periods')->group(function () {
                Route::get('/', [PayPeriodController::class, 'index']);
                Route::post('/', [PayPeriodController::class, 'store']);
                Route::get('/{payPeriod}', [PayPeriodController::class, 'show']);
                Route::post('/{payPeriod}/generate-payslips', [PayPeriodController::class, 'generatePayslips']);
            });

            Route::prefix('payslips')->group(function () {
                Route::get('/', [PayslipController::class, 'index']);
                Route::get('/{payslip}', [PayslipController::class, 'show']);
                Route::patch('/{payslip}', [PayslipController::class, 'update']);
            });

            Route::prefix('leave-requests')->group(function () {
                Route::get('/', [LeaveRequestController::class, 'index']);
                Route::post('/', [LeaveRequestController::class, 'store']);
                Route::patch('/{leaveRequest}', [LeaveRequestController::class, 'update']);
            });

            Route::prefix('training-courses')->group(function () {
                Route::get('/', [TrainingCourseController::class, 'index']);
                Route::post('/', [TrainingCourseController::class, 'store']);
            });

            Route::prefix('training-records')->group(function () {
                Route::get('/', [TrainingRecordController::class, 'index']);
                Route::post('/', [TrainingRecordController::class, 'store']);
            });

            Route::get('/visits/route', [RouteController::class, 'show']);
            Route::prefix('visits')->group(function () {
                Route::get('/', [VisitController::class, 'index']);
                Route::post('/', [VisitController::class, 'store']);
                Route::get('/{visit}', [VisitController::class, 'show']);
                Route::patch('/{visit}', [VisitController::class, 'update']);
                Route::post('/{visit}/check-in', [VisitCheckInController::class, 'checkIn']);
                Route::post('/{visit}/check-out', [VisitCheckInController::class, 'checkOut']);
            });

            Route::prefix('carer-locations')->group(function () {
                Route::post('/', [CarerLocationController::class, 'store']);
                Route::get('/live', [CarerLocationController::class, 'live']);
            });

            Route::prefix('duty-periods')->group(function () {
                Route::get('/current', [DutyPeriodController::class, 'current']);
                // Static path must be declared before the {dutyPeriod} routes below.
                Route::get('/open', [DutyPeriodController::class, 'open']);
                Route::post('/', [DutyPeriodController::class, 'store']);
                Route::post('/{dutyPeriod}/check-out', [DutyPeriodController::class, 'checkOut']);
                Route::post('/{dutyPeriod}/force-close', [DutyPeriodController::class, 'forceClose']);
            });

            Route::prefix('shifts')->group(function () {
                Route::get('/', [ShiftController::class, 'index']);
                Route::post('/', [ShiftController::class, 'store']);
                Route::get('/{shift}', [ShiftController::class, 'show']);
                Route::patch('/{shift}', [ShiftController::class, 'update']);
            });

            Route::prefix('announcements')->group(function () {
                Route::get('/', [AnnouncementController::class, 'index']);
                Route::post('/', [AnnouncementController::class, 'store']);
            });

            Route::get('/today', [TodayController::class, 'index']);
            Route::get('/today/service-users/{serviceUser}/snapshot', [TodayController::class, 'snapshot']);
            Route::get('/operations-dashboard', [OperationsDashboardController::class, 'summary']);

            Route::get('/reports/generate', [ReportGeneratorController::class, 'generate']);

            Route::prefix('compliance-requirements')->group(function () {
                Route::get('/', [ComplianceRequirementController::class, 'index']);
                Route::post('/', [ComplianceRequirementController::class, 'store']);
                Route::get('/{complianceRequirement}', [ComplianceRequirementController::class, 'show']);
                Route::patch('/{complianceRequirement}', [ComplianceRequirementController::class, 'update']);
                Route::delete('/{complianceRequirement}', [ComplianceRequirementController::class, 'destroy']);
                Route::get('/{complianceRequirement}/documents', [ComplianceDocumentController::class, 'index']);
                Route::post('/{complianceRequirement}/documents', [ComplianceDocumentController::class, 'store']);
            });
        });
    });
});

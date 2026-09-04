<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Assessments\Models\AssessmentResponse;
use App\Modules\Assessments\Models\AssessmentTemplate;
use App\Modules\Billing\Models\Funder;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLineItem;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\Observations\Models\Observation;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Models\ServiceUserContact;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingCourse;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Visits\Models\Visit;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoDataSeeder extends Seeder
{
    protected Tenant $tenant;

    protected Branch $harare;

    protected Branch $bulawayo;

    /** @var array<string, User> keyed by role label */
    protected array $staff = [];

    /** @var array<int, ServiceUser> */
    protected array $serviceUsers = [];

    /** @var array<int, Funder> */
    protected array $funders = [];

    public function run(): void
    {
        $this->tenant = Tenant::where('slug', 'demo-care-group')->firstOrFail();
        $this->harare = Branch::where('tenant_id', $this->tenant->id)->where('name', 'Harare Branch')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->seedSecondBranch();
        $this->seedStaff();
        $this->seedFunders();
        $this->seedServiceUsers();
        $this->seedCarePlans();
        $this->seedMedications();
        $this->seedObservations();
        $this->seedVisits();
        $this->seedIncidents();
        $this->seedSafeguarding();
        $this->seedTraining();
        $this->seedCompliance();
        $this->seedAnnouncements();
        $this->seedLeave();
        $this->seedAssessments();
        $this->seedRostering();
        $this->seedBilling();
        $this->seedPayroll();
        $this->seedFamilyPortalContact();
    }

    protected function seedSecondBranch(): void
    {
        $this->bulawayo = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bulawayo Branch',
            'country' => 'Zimbabwe',
            'region' => 'Bulawayo',
            'address' => '14 Fife Street, Bulawayo',
        ]);
    }

    protected function seedStaff(): void
    {
        $orgAdmin = User::where('email', 'orgadmin@demo-care-group.test')->firstOrFail();
        StaffProfile::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $orgAdmin->id,
            'branch_id' => $this->harare->id,
            'employee_number' => 'EMP-0001',
            'job_title' => 'Operations Director',
            'employment_start_date' => now()->subYears(3)->toDateString(),
            'skills' => ['Operations Management', 'Care Governance'],
            'employment_status' => 'active',
            'hourly_rate' => null,
        ]);
        $this->staff['Organization Admin'] = $orgAdmin;

        $definitions = [
            ['name' => 'Grace Mutasa', 'role' => 'Branch Manager', 'branch' => $this->harare, 'title' => 'Branch Manager', 'skills' => ['Rota Planning', 'Team Leadership'], 'rate' => null],
            ['name' => 'Tendai Moyo', 'role' => 'Care Manager', 'branch' => $this->harare, 'title' => 'Care Manager', 'skills' => ['Care Planning', 'Risk Assessment'], 'rate' => null],
            ['name' => 'Chiedza Gumbo', 'role' => 'Care Coordinator', 'branch' => $this->bulawayo, 'title' => 'Care Coordinator', 'skills' => ['Scheduling', 'Family Liaison'], 'rate' => 12.50],
            ['name' => 'Rutendo Chikafu', 'role' => 'Nurse', 'branch' => $this->harare, 'title' => 'Registered Nurse', 'skills' => ['Medication Administration', 'Wound Care', 'Clinical Observations'], 'rate' => 16.00],
            ['name' => 'Farai Ncube', 'role' => 'Senior Carer', 'branch' => $this->harare, 'title' => 'Senior Carer', 'skills' => ['Manual Handling', 'Medication Administration', 'Personal Care'], 'rate' => 13.00],
            ['name' => 'Chipo Dube', 'role' => 'Carer / Support Worker', 'branch' => $this->harare, 'title' => 'Care Assistant', 'skills' => ['Personal Care', 'Meal Preparation'], 'rate' => 11.50],
            ['name' => 'Tinashe Sibanda', 'role' => 'Carer / Support Worker', 'branch' => $this->bulawayo, 'title' => 'Care Assistant', 'skills' => ['Personal Care', 'Mobility Support'], 'rate' => 11.50],
            ['name' => 'Blessing Moyo', 'role' => 'Carer / Support Worker', 'branch' => $this->harare, 'title' => 'Care Assistant', 'skills' => ['Personal Care', 'Dementia Care'], 'rate' => 11.50],
            ['name' => 'Nyasha Chirwa', 'role' => 'Finance Officer', 'branch' => $this->harare, 'title' => 'Finance Officer', 'skills' => ['Invoicing', 'Payroll'], 'rate' => null],
            ['name' => 'Rumbidzai Marufu', 'role' => 'HR Officer', 'branch' => $this->harare, 'title' => 'HR Officer', 'skills' => ['Recruitment', 'Employee Relations'], 'rate' => null],
            ['name' => 'Kudzai Chivasa', 'role' => 'Compliance Officer', 'branch' => $this->harare, 'title' => 'Compliance Officer', 'skills' => ['CQC Standards', 'Audit'], 'rate' => null],
            ['name' => 'Tendekayi Marange', 'role' => 'Organization Owner', 'branch' => $this->harare, 'title' => 'Managing Director', 'skills' => ['Strategic Leadership', 'Governance'], 'rate' => null],
            ['name' => 'Simbarashe Chitiyo', 'role' => 'Doctor', 'branch' => $this->harare, 'title' => 'General Practitioner', 'skills' => ['Diagnosis', 'Prescribing'], 'rate' => 20.00],
            ['name' => 'Rufaro Machingura', 'role' => 'Therapist', 'branch' => $this->harare, 'title' => 'Physiotherapist', 'skills' => ['Physiotherapy', 'Mobility Rehabilitation'], 'rate' => 15.00],
            ['name' => 'Tatenda Mhike', 'role' => 'Pharmacist', 'branch' => $this->harare, 'title' => 'Pharmacist', 'skills' => ['Medication Review', 'Dispensing'], 'rate' => 15.00],
            ['name' => 'Precious Zulu', 'role' => 'Receptionist', 'branch' => $this->bulawayo, 'title' => 'Receptionist', 'skills' => ['Front Desk', 'Scheduling Support'], 'rate' => 9.50],
            ['name' => 'Farirai Chitando', 'role' => 'Auditor', 'branch' => $this->harare, 'title' => 'Internal Auditor', 'skills' => ['Compliance Audit', 'Risk Review'], 'rate' => null],
        ];

        foreach ($definitions as $i => $def) {
            $email = strtolower(str_replace(' ', '.', $def['name'])) . '@demo-care-group.test';
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $def['name'],
                'email' => $email,
                'password' => 'password',
            ]);
            $role = Role::where('name', $def['role'])->where('tenant_id', $this->tenant->id)->firstOrFail();
            $user->assignRole($role);

            StaffProfile::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $user->id,
                'branch_id' => $def['branch']->id,
                'employee_number' => sprintf('EMP-%04d', $i + 2),
                'job_title' => $def['title'],
                'employment_start_date' => now()->subMonths(random_int(4, 30))->toDateString(),
                'skills' => $def['skills'],
                'employment_status' => 'active',
                'hourly_rate' => $def['rate'],
            ]);

            // Keep the first user created per role as "the" representative for
            // that role (used elsewhere for care_manager_id, reported_by, etc.);
            // don't overwrite on the (currently impossible) case of duplicate roles.
            $this->staff[$def['role']] ??= $user;
        }
    }

    protected function seedFunders(): void
    {
        $this->funders = [
            Funder::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'NHS Harare Central',
                'type' => 'nhs',
                'contact_name' => 'Continuing Care Team',
                'phone' => '+263 4 700 111',
                'email' => 'continuingcare@nhsharare.example',
                'default_hourly_rate' => 14.00,
                'status' => 'active',
            ]),
            Funder::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'City of Harare Social Services',
                'type' => 'local_authority',
                'contact_name' => 'Adult Social Care Team',
                'phone' => '+263 4 700 222',
                'email' => 'adultsocialcare@harare.example',
                'default_hourly_rate' => 12.00,
                'status' => 'active',
            ]),
            Funder::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Private Pay',
                'type' => 'self_funded',
                'default_hourly_rate' => 18.00,
                'status' => 'active',
            ]),
        ];
    }

    protected function seedServiceUsers(): void
    {
        $definitions = [
            [
                'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'preferred_name' => 'Ruth', 'gender' => 'Female',
                'dob' => '1938-03-14', 'phone' => '+263 77 123 4567', 'address' => '22 Baines Avenue, Harare', 'lat' => -17.8203, 'lng' => 31.0468,
                'funding_source' => 'Local Authority', 'branch' => $this->harare, 'care_manager' => 'Care Manager', 'funder' => 1,
                'allergies' => ['Penicillin'], 'diagnoses' => ['Osteoarthritis'], 'medical_conditions' => ['Type 2 Diabetes', 'Hypertension'],
                'disabilities' => [], 'mobility_notes' => 'Uses a walking frame; requires standing assistance from bed to chair.',
                'communication_needs' => null, 'dietary_needs' => 'Diabetic diet — low sugar.',
            ],
            [
                'first_name' => 'Josiah', 'last_name' => 'Ndlovu', 'preferred_name' => null, 'gender' => 'Male',
                'dob' => '1945-07-02', 'phone' => '+263 77 234 5678', 'address' => '9 Josiah Tongogara St, Harare', 'lat' => -17.8265, 'lng' => 31.0492,
                'funding_source' => 'NHS Continuing Care', 'branch' => $this->harare, 'care_manager' => 'Care Manager', 'funder' => 0,
                'allergies' => [], 'diagnoses' => ["Parkinson's Disease"], 'medical_conditions' => ["Parkinson's Disease"],
                'disabilities' => ['Limited mobility'], 'mobility_notes' => 'Slow gait, fall risk — always use gait belt.',
                'communication_needs' => 'Soft speech, allow extra time to respond.', 'dietary_needs' => null,
            ],
            [
                'first_name' => 'Agnes', 'last_name' => 'Moyo', 'preferred_name' => 'Aggie', 'gender' => 'Female',
                'dob' => '1932-11-20', 'phone' => '+263 77 345 6789', 'address' => '5 Enterprise Road, Harare', 'lat' => -17.8014, 'lng' => 31.0912,
                'funding_source' => 'Self-funded', 'branch' => $this->harare, 'care_manager' => 'Branch Manager', 'funder' => 2,
                'allergies' => [], 'diagnoses' => ['Dementia (early stage)'], 'medical_conditions' => ['Dementia (early stage)'],
                'disabilities' => [], 'mobility_notes' => null,
                'communication_needs' => 'Use short, simple sentences.', 'dietary_needs' => null,
            ],
            [
                'first_name' => 'Peter', 'last_name' => 'Sibanda', 'preferred_name' => null, 'gender' => 'Male',
                'dob' => '1950-01-09', 'phone' => '+263 71 456 7890', 'address' => '31 Fort Street, Bulawayo', 'lat' => -20.1531, 'lng' => 28.5839,
                'funding_source' => 'Local Authority', 'branch' => $this->bulawayo, 'care_manager' => 'Care Coordinator', 'funder' => 1,
                'allergies' => ['Latex'], 'diagnoses' => ['Chronic Obstructive Pulmonary Disease'], 'medical_conditions' => ['COPD'],
                'disabilities' => [], 'mobility_notes' => 'Breathless on exertion — pace all activity, keep inhaler within reach.',
                'communication_needs' => null, 'dietary_needs' => null,
            ],
            [
                'first_name' => 'Faith', 'last_name' => 'Gumbo', 'preferred_name' => null, 'gender' => 'Female',
                'dob' => '1960-05-28', 'phone' => '+263 77 567 8901', 'address' => '18 Second Street, Harare', 'lat' => -17.8298, 'lng' => 31.0413,
                'funding_source' => 'NHS Continuing Care', 'branch' => $this->harare, 'care_manager' => 'Care Manager', 'funder' => 0,
                'allergies' => [], 'diagnoses' => ['Stroke (2025)'], 'medical_conditions' => ['Stroke recovery'],
                'disabilities' => ['Right-side weakness'], 'mobility_notes' => 'Right-side weakness following stroke; uses a wheelchair for longer distances.',
                'communication_needs' => 'Mild expressive aphasia — allow time, offer choices rather than open questions.', 'dietary_needs' => 'Soft/pureed diet, thickened fluids.',
            ],
            [
                'first_name' => 'Enock', 'last_name' => 'Chirwa', 'preferred_name' => null, 'gender' => 'Male',
                'dob' => '1942-09-16', 'phone' => '+263 77 678 9012', 'address' => '3 Chinhoyi Street, Harare', 'lat' => -17.8318, 'lng' => 31.0385,
                'funding_source' => 'Self-funded', 'branch' => $this->harare, 'care_manager' => 'Branch Manager', 'funder' => 2,
                'allergies' => [], 'diagnoses' => [], 'medical_conditions' => [], 'disabilities' => [],
                'mobility_notes' => null, 'communication_needs' => null, 'dietary_needs' => null,
                'status' => 'discharged',
            ],
        ];

        foreach ($definitions as $def) {
            $this->serviceUsers[] = ServiceUser::create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $def['branch']->id,
                'care_manager_id' => $this->staff[$def['care_manager']]->id,
                'first_name' => $def['first_name'],
                'last_name' => $def['last_name'],
                'preferred_name' => $def['preferred_name'],
                'date_of_birth' => $def['dob'],
                'gender' => $def['gender'],
                'language' => 'English',
                'phone' => $def['phone'],
                'address' => $def['address'],
                // Without coordinates the visit check-in geofence has nothing to
                // measure against and silently waves every check-in through.
                'latitude' => $def['lat'],
                'longitude' => $def['lng'],
                'funding_source' => $def['funding_source'],
                'funder_id' => $this->funders[$def['funder']]->id,
                'status' => $def['status'] ?? 'active',
                'allergies' => $def['allergies'],
                'diagnoses' => $def['diagnoses'],
                'medical_conditions' => $def['medical_conditions'],
                'disabilities' => $def['disabilities'],
                'mobility_notes' => $def['mobility_notes'],
                'communication_needs' => $def['communication_needs'],
                'dietary_needs' => $def['dietary_needs'],
            ]);
        }
    }

    protected function seedCarePlans(): void
    {
        $sectionsByServiceUser = [
            0 => [ // Ruth Chikafu
                ['area' => 'personal_care', 'need' => 'Requires support washing and dressing due to reduced mobility.', 'goal' => 'Ruth maintains her dignity and personal hygiene each day.', 'intervention' => 'Carer to assist with washing, dressing, and hair care each morning visit.', 'status' => 'ongoing', 'risk' => 'medium', 'equipment' => null],
                ['area' => 'medication', 'need' => 'Requires prompting and administration of diabetes and blood pressure medication.', 'goal' => 'Ruth\'s medication is administered safely and on schedule.', 'intervention' => 'Carer to administer medication per MAR chart and record blood glucose before breakfast.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Blood glucose monitor'],
                ['area' => 'nutrition', 'need' => 'Diabetic diet management.', 'goal' => 'Ruth maintains stable blood sugar through balanced diet.', 'intervention' => 'Carer to prepare diabetic-friendly meals per dietary plan.', 'status' => 'met', 'risk' => 'low', 'equipment' => null],
                ['area' => 'mobility', 'need' => 'Fall risk due to reduced balance.', 'goal' => 'Ruth mobilises safely around her home.', 'intervention' => 'Carer to supervise all transfers and provide standing assistance using the walking frame.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Walking frame'],
            ],
            1 => [ // Josiah Ndlovu
                ['area' => 'mobility', 'need' => "Parkinson's-related gait instability, high fall risk.", 'goal' => 'Josiah moves around his home without falls.', 'intervention' => 'Carer to use gait belt for all transfers and walk alongside Josiah at his pace.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Gait belt'],
                ['area' => 'medication', 'need' => 'Time-critical Parkinson\'s medication.', 'goal' => 'Medication is administered within 30 minutes of scheduled time.', 'intervention' => 'Carer to administer medication strictly on schedule and record any delay.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => null],
                ['area' => 'communication', 'need' => 'Soft, slow speech makes communication difficult.', 'goal' => 'Josiah is able to communicate his needs effectively.', 'intervention' => 'Carer to allow extra time, face Josiah directly, and confirm understanding.', 'status' => 'ongoing', 'risk' => 'low', 'equipment' => null],
            ],
            2 => [ // Agnes Moyo
                ['area' => 'behaviour', 'need' => 'Becomes anxious in unfamiliar situations due to early-stage dementia.', 'goal' => 'Agnes remains calm and reassured during visits.', 'intervention' => 'Carer to maintain a consistent routine and use a calm, familiar approach.', 'status' => 'ongoing', 'risk' => 'medium', 'equipment' => null],
                ['area' => 'daily_living', 'need' => 'Needs prompting with daily tasks.', 'goal' => 'Agnes completes daily routines with support.', 'intervention' => 'Carer to prompt and support with washing, dressing, and meal preparation.', 'status' => 'ongoing', 'risk' => 'low', 'equipment' => null],
                ['area' => 'social_activities', 'need' => 'Reduced social contact since husband\'s passing.', 'goal' => 'Agnes has regular meaningful social engagement.', 'intervention' => 'Carer to spend time chatting and looking through photo albums each visit.', 'status' => 'met', 'risk' => 'low', 'equipment' => null],
            ],
            3 => [ // Peter Sibanda
                ['area' => 'respiratory_care', 'need' => 'COPD causes breathlessness on exertion.', 'goal' => 'Peter manages daily activity without respiratory distress.', 'intervention' => 'Carer to pace all activities, ensure inhaler is within reach, and monitor for signs of distress.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Salbutamol inhaler, pulse oximeter'],
                ['area' => 'personal_care', 'need' => 'Breathlessness limits ability to self-care.', 'goal' => 'Peter maintains personal hygiene without over-exertion.', 'intervention' => 'Carer to assist with washing and dressing, allowing rest breaks.', 'status' => 'ongoing', 'risk' => 'medium', 'equipment' => null],
            ],
            4 => [ // Faith Gumbo
                ['area' => 'mobility', 'need' => 'Right-side weakness following stroke.', 'goal' => 'Faith transfers safely with support.', 'intervention' => 'Carer to use hoist/wheelchair per moving-and-handling plan for longer distances.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Hoist, wheelchair'],
                ['area' => 'nutrition', 'need' => 'Swallowing difficulty following stroke.', 'goal' => 'Faith eats and drinks safely without aspiration.', 'intervention' => 'Carer to provide soft/pureed meals and thickened fluids per SALT plan.', 'status' => 'ongoing', 'risk' => 'high', 'equipment' => 'Fluid thickener'],
                ['area' => 'communication', 'need' => 'Mild expressive aphasia.', 'goal' => 'Faith is able to make her needs known.', 'intervention' => 'Carer to offer simple choices rather than open questions and allow processing time.', 'status' => 'ongoing', 'risk' => 'low', 'equipment' => null],
            ],
            5 => [ // Enock Chirwa (discharged)
                ['area' => 'personal_care', 'need' => 'Required support with washing and dressing.', 'goal' => 'Enock maintained personal hygiene.', 'intervention' => 'Carer assisted with washing and dressing each visit.', 'status' => 'met', 'risk' => 'low', 'equipment' => null],
            ],
        ];

        foreach ($sectionsByServiceUser as $index => $sections) {
            $serviceUser = $this->serviceUsers[$index];
            $carePlan = CarePlan::create([
                'tenant_id' => $this->tenant->id,
                'service_user_id' => $serviceUser->id,
                'version' => 1,
                'status' => $serviceUser->status === 'discharged' ? 'archived' : 'active',
                'effective_from' => now()->subMonths(random_int(2, 8))->toDateString(),
                'created_by' => $this->staff['Care Manager']->id,
                'notes' => 'Initial care plan agreed with service user and family.',
            ]);

            foreach ($sections as $section) {
                CarePlanSection::create([
                    'tenant_id' => $this->tenant->id,
                    'care_plan_id' => $carePlan->id,
                    'area' => $section['area'],
                    'identified_need' => $section['need'],
                    'risk' => $section['risk'] ?? null,
                    'goal' => $section['goal'],
                    'intervention' => $section['intervention'],
                    'equipment' => $section['equipment'] ?? null,
                    'frequency' => 'Every visit',
                    'responsible_staff_id' => $this->staff['Senior Carer']->id,
                    'start_date' => $carePlan->effective_from,
                    'review_date' => now()->addMonths(3)->toDateString(),
                    'status' => $section['status'],
                    'notes' => null,
                ]);
            }
        }
    }

    protected function seedMedications(): void
    {
        $medsByServiceUser = [
            0 => [ // Ruth Chikafu
                ['name' => 'Metformin', 'strength' => '500mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Twice daily', 'is_prn' => false],
                ['name' => 'Lisinopril', 'strength' => '10mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Once daily', 'is_prn' => false],
                ['name' => 'Paracetamol', 'strength' => '500mg', 'dose' => '2 tablets', 'route' => 'Oral', 'frequency' => 'As required', 'is_prn' => true],
            ],
            1 => [ // Josiah Ndlovu
                ['name' => 'Co-careldopa', 'strength' => '25mg/100mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Four times daily', 'is_prn' => false],
            ],
            2 => [ // Agnes Moyo
                ['name' => 'Donepezil', 'strength' => '5mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Once daily, evening', 'is_prn' => false],
            ],
            3 => [ // Peter Sibanda
                ['name' => 'Salbutamol Inhaler', 'strength' => '100mcg', 'dose' => '2 puffs', 'route' => 'Inhaled', 'frequency' => 'As required', 'is_prn' => true],
                ['name' => 'Tiotropium', 'strength' => '18mcg', 'dose' => '1 capsule (inhaled)', 'route' => 'Inhaled', 'frequency' => 'Once daily', 'is_prn' => false],
            ],
            4 => [ // Faith Gumbo
                ['name' => 'Atorvastatin', 'strength' => '20mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Once daily, evening', 'is_prn' => false],
                ['name' => 'Aspirin', 'strength' => '75mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'Once daily', 'is_prn' => false],
            ],
        ];

        $carers = [$this->staff['Senior Carer'], $this->staff['Carer / Support Worker'], $this->staff['Nurse']];

        foreach ($medsByServiceUser as $index => $meds) {
            $serviceUser = $this->serviceUsers[$index];

            foreach ($meds as $med) {
                $medication = Medication::create([
                    'tenant_id' => $this->tenant->id,
                    'service_user_id' => $serviceUser->id,
                    'name' => $med['name'],
                    'strength' => $med['strength'],
                    'form' => 'Tablet',
                    'dose' => $med['dose'],
                    'route' => $med['route'],
                    'frequency' => $med['frequency'],
                    'schedule' => null,
                    'start_date' => now()->subMonths(random_int(1, 6))->toDateString(),
                    'end_date' => null,
                    'prescriber' => 'Dr. M. Chidziva',
                    'pharmacy' => 'Baines Avenue Pharmacy',
                    'instructions' => 'Take with food.',
                    'is_prn' => $med['is_prn'],
                    'prn_instructions' => $med['is_prn'] ? 'May give up to 4 times in 24 hours. Do not exceed maximum dose.' : null,
                    'is_controlled_drug' => false,
                    'status' => 'active',
                    'created_by' => $this->staff['Nurse']->id,
                ]);

                if ($med['is_prn']) {
                    continue;
                }

                // Administration history for the last 10 days, mostly on time.
                for ($daysAgo = 9; $daysAgo >= 0; $daysAgo--) {
                    $status = 'administered';
                    if ($daysAgo === 4 && $index === 0) {
                        $status = 'refused';
                    } elseif ($daysAgo === 2 && $index === 3) {
                        $status = 'missed';
                    }

                    MedicationAdministration::create([
                        'tenant_id' => $this->tenant->id,
                        'medication_id' => $medication->id,
                        'visit_id' => null,
                        'status' => $status,
                        'administered_at' => now()->subDays($daysAgo)->setTime(8, random_int(0, 30)),
                        'administered_by' => $carers[array_rand($carers)]->id,
                        'witness_id' => null,
                        'notes' => $status === 'refused' ? 'Service user declined medication this morning.' : ($status === 'missed' ? 'Carer arrived after service user had already left for a hospital appointment.' : null),
                    ]);
                }
            }
        }
    }

    protected function seedObservations(): void
    {
        $observationDefs = [
            0 => ['type' => 'blood_glucose', 'value' => ['reading' => 7.2], 'unit' => 'mmol/L', 'alert' => null],
            1 => ['type' => 'blood_pressure', 'value' => ['systolic' => 165, 'diastolic' => 98], 'unit' => 'mmHg', 'alert' => 'Blood pressure elevated — above normal range.'],
            3 => ['type' => 'oxygen_saturation', 'value' => ['reading' => 91], 'unit' => '%', 'alert' => 'Oxygen saturation below target range for COPD management plan.'],
            4 => ['type' => 'temperature', 'value' => ['reading' => 36.8], 'unit' => '°C', 'alert' => null],
        ];

        foreach ($observationDefs as $index => $def) {
            $serviceUser = $this->serviceUsers[$index];

            $observation = Observation::create([
                'tenant_id' => $this->tenant->id,
                'service_user_id' => $serviceUser->id,
                'visit_id' => null,
                'type' => $def['type'],
                'value' => $def['value'],
                'unit' => $def['unit'],
                'recorded_by' => $this->staff['Nurse']->id,
                'recorded_at' => now()->subHours(random_int(2, 20)),
                'notes' => null,
            ]);

            // A few more routine readings over the past week for chart data.
            for ($daysAgo = 1; $daysAgo <= 6; $daysAgo++) {
                Observation::create([
                    'tenant_id' => $this->tenant->id,
                    'service_user_id' => $serviceUser->id,
                    'visit_id' => null,
                    'type' => $def['type'],
                    'value' => $def['value'],
                    'unit' => $def['unit'],
                    'recorded_by' => $this->staff['Nurse']->id,
                    'recorded_at' => now()->subDays($daysAgo)->setTime(9, 0),
                    'notes' => null,
                ]);
            }

            if ($def['alert']) {
                ClinicalAlert::create([
                    'tenant_id' => $this->tenant->id,
                    'service_user_id' => $serviceUser->id,
                    'observation_id' => $observation->id,
                    'message' => $def['alert'],
                    'severity' => $def['type'] === 'oxygen_saturation' ? 'critical' : 'warning',
                    'acknowledged_at' => null,
                    'acknowledged_by' => null,
                ]);
            }
        }
    }

    protected function seedVisits(): void
    {
        $carers = [$this->staff['Senior Carer'], $this->staff['Carer / Support Worker'], $this->staff['Nurse']];
        $today = now()->toDateString();

        // Today: a deliberate spread across every status the Today page renders.
        $this->createVisit($this->serviceUsers[0], $carers[0], $today, '07:30', '08:30', 'completed', checkedIn: true, checkedOut: true);
        $this->createVisit($this->serviceUsers[1], $carers[1], $today, '09:00', '10:00', 'completed', checkedIn: true, checkedOut: true);
        $this->createVisit($this->serviceUsers[2], $carers[0], $today, now()->subMinutes(40)->format('H:i'), now()->addMinutes(20)->format('H:i'), 'in_progress', checkedIn: true, checkedOut: false, checkInAt: now()->subMinutes(35));
        $this->createVisit($this->serviceUsers[3], $carers[1], $today, now()->subHours(2)->format('H:i'), now()->subHours(1)->format('H:i'), 'scheduled', checkedIn: false, checkedOut: false); // renders as "Late"
        $this->createVisit($this->serviceUsers[4], $carers[2], $today, now()->addHours(2)->format('H:i'), now()->addHours(3)->format('H:i'), 'scheduled');
        $this->createVisit($this->serviceUsers[0], $carers[0], $today, now()->addHours(4)->format('H:i'), now()->addHours(5)->format('H:i'), 'scheduled');
        $this->createVisit($this->serviceUsers[1], null, $today, '18:00', '19:00', 'scheduled'); // unassigned — drags down rota coverage

        // Rest of this week: mix of completed, missed, and unassigned for the stat tiles.
        $weekStart = now()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            if ($date->isToday()) {
                continue;
            }

            $serviceUser = $this->serviceUsers[$i % 5];
            $carer = $i === 1 ? null : $carers[$i % 3];
            $status = $date->isPast() ? ($i === 2 ? 'missed' : 'completed') : 'scheduled';

            $this->createVisit(
                $serviceUser,
                $carer,
                $date->toDateString(),
                '10:00',
                '11:00',
                $status,
                checkedIn: $status === 'completed',
                checkedOut: $status === 'completed',
            );
        }

        // A handful further into the past and future for the Visits list generally.
        for ($daysAgo = 3; $daysAgo <= 14; $daysAgo += 3) {
            $this->createVisit(
                $this->serviceUsers[$daysAgo % 5],
                $carers[$daysAgo % 3],
                now()->subDays($daysAgo)->toDateString(),
                '13:00',
                '14:00',
                'completed',
                checkedIn: true,
                checkedOut: true,
            );
        }
        for ($daysAhead = 2; $daysAhead <= 10; $daysAhead += 2) {
            $this->createVisit(
                $this->serviceUsers[$daysAhead % 5],
                $carers[$daysAhead % 3],
                now()->addDays($daysAhead)->toDateString(),
                '11:00',
                '12:00',
                'scheduled',
            );
        }

        // One cancelled visit for variety.
        $this->createVisit($this->serviceUsers[5], null, now()->addDays(1)->toDateString(), '10:00', '11:00', 'cancelled');
    }

    protected function createVisit(
        ServiceUser $serviceUser,
        ?User $carer,
        string $date,
        string $startTime,
        string $endTime,
        string $status,
        bool $checkedIn = false,
        bool $checkedOut = false,
        ?\Carbon\Carbon $checkInAt = null,
    ): Visit {
        $visitDate = \Carbon\Carbon::parse($date);

        return Visit::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer?->id,
            'visit_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'care_tasks' => ['Personal care', 'Medication prompt', 'Meal preparation'],
            'medication_tasks' => true,
            'required_skills' => [],
            'priority' => 'medium',
            'status' => $status,
            'notes' => null,
            'check_in_at' => $checkedIn ? ($checkInAt ?? $visitDate->copy()->setTimeFromTimeString($startTime)) : null,
            'check_in_lat' => $checkedIn ? -17.8292 : null,
            'check_in_lng' => $checkedIn ? 31.0522 : null,
            'check_out_at' => $checkedOut ? $visitDate->copy()->setTimeFromTimeString($endTime) : null,
            'check_out_lat' => $checkedOut ? -17.8292 : null,
            'check_out_lng' => $checkedOut ? 31.0522 : null,
        ]);
    }

    protected function seedIncidents(): void
    {
        $defs = [
            ['type' => 'fall', 'severity' => 'medium', 'status' => 'investigating', 'su' => 0, 'desc' => 'Ruth was found on the floor of her bedroom having slipped while transferring from bed to walking frame. No visible injury, GP informed as a precaution.'],
            ['type' => 'medication_error', 'severity' => 'high', 'status' => 'corrective_action', 'su' => 1, 'desc' => 'Evening dose of co-careldopa was given 90 minutes late due to carer running behind schedule, causing increased tremor.'],
            ['type' => 'behavioural', 'severity' => 'low', 'status' => 'reported', 'su' => 2, 'desc' => 'Agnes became distressed and repeatedly asked to go home during the afternoon visit. Carer used reassurance techniques and the episode resolved within 15 minutes.'],
            ['type' => 'property_damage', 'severity' => 'low', 'status' => 'closed', 'su' => null, 'desc' => 'A kitchen cupboard door hinge broke during a routine visit. Reported to landlord for repair.'],
            ['type' => 'infection', 'severity' => 'critical', 'status' => 'reviewed', 'su' => 3, 'desc' => "Peter was admitted to hospital with a suspected chest infection exacerbating his COPD. Family and GP notified immediately."],
        ];

        foreach ($defs as $def) {
            Incident::create([
                'tenant_id' => $this->tenant->id,
                'service_user_id' => $def['su'] !== null ? $this->serviceUsers[$def['su']]->id : null,
                'type' => $def['type'],
                'severity' => $def['severity'],
                'description' => $def['desc'],
                'immediate_action' => 'First aid/comfort provided, senior carer and family notified.',
                'status' => $def['status'],
                'reported_by' => $this->staff['Senior Carer']->id,
                'assigned_to' => $this->staff['Care Manager']->id,
                'reviewed_by' => in_array($def['status'], ['reviewed', 'closed'], true) ? $this->staff['Compliance Officer']->id : null,
                'investigation_notes' => $def['status'] !== 'reported' ? 'Reviewed with staff involved; process reinforced during team huddle.' : null,
                'corrective_actions' => $def['status'] === 'corrective_action' ? 'Rota adjusted to build in travel buffer between time-critical medication visits.' : null,
                'reviewed_at' => in_array($def['status'], ['reviewed', 'closed'], true) ? now()->subDays(2) : null,
                'closed_at' => $def['status'] === 'closed' ? now()->subDays(1) : null,
            ]);
        }
    }

    protected function seedSafeguarding(): void
    {
        SafeguardingCase::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $this->serviceUsers[2]->id,
            'victim_name' => 'Agnes Moyo',
            'alleged_perpetrator' => 'Unknown — reported by neighbour.',
            'concern_type' => 'Financial abuse concern',
            'immediate_risk' => false,
            'external_agencies_notified' => 'Local authority safeguarding team notified 2 days after report.',
            'investigation_notes' => 'A neighbour raised concern that an unfamiliar visitor had been asking Agnes for money. No confirmed loss identified so far.',
            'actions_taken' => 'Increased visit frequency temporarily; family informed and monitoring bank correspondence.',
            'outcome' => null,
            'status' => 'investigating',
            'reported_by' => $this->staff['Care Manager']->id,
            'confidential_notes' => 'Awaiting update from local authority safeguarding lead.',
        ]);

        SafeguardingCase::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $this->serviceUsers[4]->id,
            'victim_name' => 'Faith Gumbo',
            'alleged_perpetrator' => null,
            'concern_type' => 'Self-neglect concern',
            'immediate_risk' => false,
            'external_agencies_notified' => null,
            'investigation_notes' => 'Carer noted the home was colder than usual and the fridge was low on food during two consecutive visits.',
            'actions_taken' => 'Care manager contacted family to arrange a heating check and additional grocery support.',
            'outcome' => 'Family arranged weekly grocery delivery; no further concerns identified.',
            'status' => 'actions_taken',
            'reported_by' => $this->staff['Carer / Support Worker']->id,
            'confidential_notes' => null,
        ]);
    }

    protected function seedTraining(): void
    {
        $courses = [
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'Manual Handling', 'category' => 'Health & Safety', 'validity_period_months' => 12, 'is_mandatory' => true]),
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'Safeguarding Adults Level 2', 'category' => 'Safeguarding', 'validity_period_months' => 36, 'is_mandatory' => true]),
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'Medication Administration', 'category' => 'Clinical', 'validity_period_months' => 24, 'is_mandatory' => true]),
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'Fire Safety', 'category' => 'Health & Safety', 'validity_period_months' => 12, 'is_mandatory' => true]),
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'First Aid at Work', 'category' => 'Health & Safety', 'validity_period_months' => 36, 'is_mandatory' => false]),
            TrainingCourse::create(['tenant_id' => $this->tenant->id, 'name' => 'Infection Prevention & Control', 'category' => 'Clinical', 'validity_period_months' => 12, 'is_mandatory' => true]),
        ];

        $carers = ['Senior Carer', 'Carer / Support Worker', 'Nurse', 'Care Manager', 'Branch Manager', 'Care Coordinator'];

        foreach ($carers as $index => $roleKey) {
            $user = $this->staff[$roleKey];

            foreach ($courses as $courseIndex => $course) {
                // Skip a couple of combinations so not every staff member has every course.
                if (($index + $courseIndex) % 4 === 3) {
                    continue;
                }

                $completedMonthsAgo = ($index + $courseIndex) % 6 + 1;
                $completedDate = now()->subMonths($completedMonthsAgo);
                $expiryDate = $course->validity_period_months
                    ? $completedDate->copy()->addMonths($course->validity_period_months)
                    : null;

                // Force a spread of expiry states: a couple expiring within 30
                // days, a couple already expired, the rest comfortably valid.
                if ($expiryDate && $index === 0 && $courseIndex === 0) {
                    $expiryDate = now()->addDays(12);
                } elseif ($expiryDate && $index === 1 && $courseIndex === 2) {
                    $expiryDate = now()->addDays(25);
                } elseif ($expiryDate && $index === 2 && $courseIndex === 1) {
                    $expiryDate = now()->subDays(10);
                }

                TrainingRecord::create([
                    'tenant_id' => $this->tenant->id,
                    'user_id' => $user->id,
                    'training_course_id' => $course->id,
                    'completed_date' => $completedDate->toDateString(),
                    'expiry_date' => $expiryDate?->toDateString(),
                    'notes' => null,
                    'recorded_by' => $this->staff['HR Officer']->id,
                ]);
            }
        }
    }

    protected function seedCompliance(): void
    {
        $defs = [
            ['name' => 'CQC / Local Authority Registration', 'category' => 'Registration', 'jurisdiction' => 'Zimbabwe', 'status' => 'compliant', 'issued' => -18, 'renewal' => 6, 'ref' => 'REG-2024-0187'],
            ['name' => 'Public Liability Insurance', 'category' => 'Insurance', 'jurisdiction' => 'Zimbabwe', 'status' => 'compliant', 'issued' => -6, 'renewal' => 6, 'ref' => 'INS-991-2026'],
            ['name' => 'Fire Risk Assessment', 'category' => 'Health & Safety', 'jurisdiction' => 'Harare', 'status' => 'pending', 'issued' => -14, 'renewal' => 1, 'ref' => 'FRA-2025-014'],
            ['name' => 'DBS Umbrella Registration', 'category' => 'Safeguarding', 'jurisdiction' => 'Zimbabwe', 'status' => 'non_compliant', 'issued' => -30, 'renewal' => -2, 'ref' => 'DBS-4471'],
        ];

        foreach ($defs as $def) {
            ComplianceRequirement::create([
                'tenant_id' => $this->tenant->id,
                'name' => $def['name'],
                'category' => $def['category'],
                'jurisdiction' => $def['jurisdiction'],
                'status' => $def['status'],
                'issued_date' => now()->addMonths($def['issued'])->toDateString(),
                'renewal_date' => now()->addMonths($def['renewal'])->toDateString(),
                'reference_number' => $def['ref'],
                'responsible_user_id' => $this->staff['Compliance Officer']->id,
                'notes' => null,
                'created_by' => $this->staff['Compliance Officer']->id,
            ]);
        }
    }

    protected function seedAnnouncements(): void
    {
        Announcement::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => null,
            'title' => 'Welcome to CareNexa360',
            'body' => "We've moved all rota, care planning, and compliance tracking into CareNexa360. Please reach out to your branch manager with any questions while we settle in.",
            'posted_by' => $this->staff['Organization Admin']->id,
            'pinned' => true,
        ]);

        Announcement::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->harare->id,
            'title' => 'Updated manual handling refresher dates',
            'body' => 'The manual handling refresher session for Harare-based staff has been moved to next Thursday at 10am in the branch office. Please confirm attendance with Rumbidzai.',
            'posted_by' => $this->staff['HR Officer']->id,
            'pinned' => false,
        ]);

        Announcement::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => null,
            'title' => 'Reminder: log medication refusals promptly',
            'body' => 'Please make sure any medication refusal or missed dose is logged in the MAR chart immediately and flagged to your care manager the same day.',
            'posted_by' => $this->staff['Compliance Officer']->id,
            'pinned' => false,
        ]);
    }

    protected function seedLeave(): void
    {
        $defs = [
            ['user' => 'Senior Carer', 'type' => 'annual', 'start' => 10, 'end' => 14, 'status' => 'approved'],
            ['user' => 'Carer / Support Worker', 'type' => 'sick', 'start' => -2, 'end' => -1, 'status' => 'approved'],
            ['user' => 'Nurse', 'type' => 'annual', 'start' => 20, 'end' => 27, 'status' => 'pending'],
            ['user' => 'Care Coordinator', 'type' => 'unpaid', 'start' => 30, 'end' => 32, 'status' => 'pending'],
        ];

        foreach ($defs as $def) {
            LeaveRequest::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->staff[$def['user']]->id,
                'type' => $def['type'],
                'start_date' => now()->addDays($def['start'])->toDateString(),
                'end_date' => now()->addDays($def['end'])->toDateString(),
                'status' => $def['status'],
                'reason' => null,
                'approved_by' => $def['status'] === 'approved' ? $this->staff['Branch Manager']->id : null,
                'approved_at' => $def['status'] === 'approved' ? now()->subDays(3) : null,
                'notes' => null,
            ]);
        }
    }

    protected function seedAssessments(): void
    {
        $template = AssessmentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Initial Care Needs Assessment',
            'category' => 'Care Planning',
            'description' => 'Baseline assessment completed on referral to establish care needs and preferences.',
            'fields' => [
                ['key' => 'mobility_level', 'label' => 'Mobility level', 'type' => 'select', 'options' => ['Independent', 'Needs assistance', 'Uses walking aid', 'Wheelchair user', 'Bed-bound'], 'required' => true],
                ['key' => 'falls_risk_score', 'label' => 'Falls risk score (1-10)', 'type' => 'score', 'required' => true],
                ['key' => 'cognitive_status', 'label' => 'Cognitive status notes', 'type' => 'textarea', 'required' => false],
                ['key' => 'preferred_wake_time', 'label' => 'Preferred wake time', 'type' => 'text', 'required' => false],
            ],
            'is_active' => true,
        ]);

        AssessmentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Falls Risk Assessment',
            'category' => 'Clinical',
            'description' => 'Standard falls risk screening tool, reviewed quarterly.',
            'fields' => [
                ['key' => 'history_of_falls', 'label' => 'History of falls in last 12 months', 'type' => 'checkbox', 'required' => false],
                ['key' => 'balance_score', 'label' => 'Balance score (1-10)', 'type' => 'score', 'required' => true],
                ['key' => 'environment_hazards', 'label' => 'Environmental hazards noted', 'type' => 'textarea', 'required' => false],
            ],
            'is_active' => true,
        ]);

        $answers = [
            ['mobility_level' => 'Uses walking aid', 'falls_risk_score' => 6, 'cognitive_status' => 'Alert and orientated.', 'preferred_wake_time' => '07:00'],
            ['mobility_level' => 'Needs assistance', 'falls_risk_score' => 8, 'cognitive_status' => 'Mild short-term memory changes noted.', 'preferred_wake_time' => '08:00'],
            ['mobility_level' => 'Independent', 'falls_risk_score' => 4, 'cognitive_status' => 'Some confusion in unfamiliar settings.', 'preferred_wake_time' => '07:30'],
        ];

        foreach ($answers as $index => $answer) {
            AssessmentResponse::create([
                'tenant_id' => $this->tenant->id,
                'assessment_template_id' => $template->id,
                'service_user_id' => $this->serviceUsers[$index]->id,
                'answers' => $answer,
                'completed_by' => $this->staff['Care Manager']->id,
                'completed_at' => now()->subMonths(2),
                'status' => 'completed',
            ]);
        }
    }

    protected function seedRostering(): void
    {
        $carers = [$this->staff['Senior Carer'], $this->staff['Carer / Support Worker'], $this->staff['Nurse']];
        $weekStart = now()->startOfWeek();

        foreach ($carers as $carerIndex => $carer) {
            for ($day = 0; $day < 5; $day++) {
                $date = $weekStart->copy()->addDays($day);
                Shift::create([
                    'tenant_id' => $this->tenant->id,
                    'user_id' => $carer->id,
                    'branch_id' => $this->harare->id,
                    'shift_date' => $date->toDateString(),
                    'start_time' => $carerIndex === 2 ? '20:00' : '07:00',
                    'end_time' => $carerIndex === 2 ? '08:00' : '15:00',
                    'shift_type' => $carerIndex === 2 ? 'night' : 'day',
                    'status' => $date->isPast() ? 'completed' : 'confirmed',
                    'notes' => null,
                ]);
            }
        }
    }

    protected function seedBilling(): void
    {
        foreach ([0, 1, 3] as $index) {
            $serviceUser = $this->serviceUsers[$index];
            $funder = $serviceUser->funder_id ? Funder::find($serviceUser->funder_id) : $this->funders[2];

            $invoice = Invoice::create([
                'tenant_id' => $this->tenant->id,
                'service_user_id' => $serviceUser->id,
                'funder_id' => $funder->id,
                'invoice_number' => sprintf('INV-2026-%04d', $index + 1),
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'issue_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(25)->toDateString(),
                'status' => $index === 0 ? 'paid' : ($index === 1 ? 'sent' : 'draft'),
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'currency' => 'USD',
                'notes' => null,
                'created_by' => $this->staff['Finance Officer']->id,
            ]);

            $rate = $funder->default_hourly_rate ?? 14.00;
            $lineItems = [
                ['description' => 'Home care visits — weekday mornings', 'quantity' => 20, 'unit_rate' => $rate],
                ['description' => 'Home care visits — weekend', 'quantity' => 8, 'unit_rate' => $rate * 1.25],
            ];

            $subtotal = 0;
            foreach ($lineItems as $item) {
                $amount = round($item['quantity'] * $item['unit_rate'], 2);
                $subtotal += $amount;

                InvoiceLineItem::create([
                    'tenant_id' => $this->tenant->id,
                    'invoice_id' => $invoice->id,
                    'visit_id' => null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_rate' => $item['unit_rate'],
                    'amount' => $amount,
                ]);
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'total' => $subtotal,
            ]);
        }
    }

    protected function seedPayroll(): void
    {
        $payPeriod = PayPeriod::create([
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'notes' => null,
        ]);

        $paidStaff = ['Care Coordinator', 'Nurse', 'Senior Carer', 'Carer / Support Worker'];

        foreach ($paidStaff as $roleKey) {
            $user = $this->staff[$roleKey];
            $profile = StaffProfile::where('tenant_id', $this->tenant->id)->where('user_id', $user->id)->first();
            $rate = (float) ($profile->hourly_rate ?? 12.00);
            $hours = 160;
            $gross = round($rate * $hours, 2);
            $deductions = round($gross * 0.08, 2);

            Payslip::create([
                'tenant_id' => $this->tenant->id,
                'pay_period_id' => $payPeriod->id,
                'user_id' => $user->id,
                'regular_hours' => $hours,
                'gross_pay' => $gross,
                'deductions' => $deductions,
                'net_pay' => $gross - $deductions,
                'status' => 'finalized',
                'generated_at' => now()->subDays(20),
            ]);
        }
    }

    protected function seedFamilyPortalContact(): void
    {
        $familyUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tafadzwa Chikafu',
            'email' => 'tafadzwa.chikafu@example.com',
            'password' => 'password',
        ]);
        $familyRole = Role::where('name', 'Family Member')->where('tenant_id', $this->tenant->id)->firstOrFail();
        $familyUser->assignRole($familyRole);

        ServiceUserContact::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $this->serviceUsers[0]->id,
            'user_id' => $familyUser->id,
            'type' => 'family',
            'name' => 'Tafadzwa Chikafu',
            'relationship' => 'Daughter',
            'phone' => '+263 77 999 0001',
            'email' => 'tafadzwa.chikafu@example.com',
            'address' => null,
            'notes' => 'Has portal access to view care updates.',
        ]);

        ServiceUserContact::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $this->serviceUsers[1]->id,
            'user_id' => null,
            'type' => 'next_of_kin',
            'name' => 'Tapiwa Ndlovu',
            'relationship' => 'Son',
            'phone' => '+263 77 999 0002',
            'email' => 'tapiwa.ndlovu@example.com',
            'address' => null,
            'notes' => null,
        ]);
    }
}

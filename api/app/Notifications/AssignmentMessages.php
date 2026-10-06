<?php

namespace App\Notifications;

use App\Modules\CarePlanning\Models\CarePlanRiskAssessment;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Quality\Models\Complaint;
use App\Modules\Rostering\Models\Shift;
use App\Modules\ServiceUsers\Models\ServiceUser;

/**
 * The wording of each assignment email, in one place so they read alike.
 */
class AssignmentMessages
{
    public static function shift(Shift $shift): AssignmentNotification
    {
        $lines = [
            "You've been rostered for a ".($shift->shift_type ? "{$shift->shift_type} " : '').'shift.',
            'Date: '.$shift->shift_date->toDateString(),
            "Time: {$shift->start_time}–{$shift->end_time}",
        ];
        if ($shift->notes) {
            $lines[] = "Notes: {$shift->notes}";
        }

        return new AssignmentNotification('shift', 'New shift assigned', $lines, 'View Rota', '/rostering');
    }

    public static function incident(Incident $incident): AssignmentNotification
    {
        $incident->loadMissing('serviceUser');
        $lines = [
            "You've been assigned to investigate an incident.",
            'Type: '.self::label($incident->type),
            'Severity: '.self::label($incident->severity),
        ];
        if ($incident->serviceUser) {
            $lines[] = 'Client: '.self::name($incident->serviceUser);
        }
        if ($incident->description) {
            $lines[] = 'Summary: '.str($incident->description)->limit(200);
        }

        return new AssignmentNotification('incident', 'Incident assigned to you', $lines, 'View Incidents', '/incidents');
    }

    public static function complaint(Complaint $complaint): AssignmentNotification
    {
        $complaint->loadMissing('serviceUser');
        $lines = [
            "You've been assigned a complaint to investigate.",
            "From: {$complaint->complainant_name}".($complaint->complainant_relationship ? " ({$complaint->complainant_relationship})" : ''),
            'Category: '.self::label($complaint->category),
            'Severity: '.self::label($complaint->severity),
        ];
        if ($complaint->serviceUser) {
            $lines[] = 'Client: '.self::name($complaint->serviceUser);
        }
        if ($complaint->response_due_date) {
            $lines[] = 'Response due: '.$complaint->response_due_date->toDateString();
        }

        return new AssignmentNotification('complaint', 'Complaint assigned to you', $lines, 'View Complaints', '/complaints');
    }

    public static function complianceRequirement(ComplianceRequirement $requirement): AssignmentNotification
    {
        $lines = ["You're now responsible for the compliance requirement \"{$requirement->name}\"."];
        if ($requirement->category) {
            $lines[] = "Category: {$requirement->category}";
        }
        if ($requirement->renewal_date) {
            $lines[] = 'Renewal due: '.$requirement->renewal_date->toDateString();
        }

        return new AssignmentNotification('compliance_requirement', 'Compliance requirement assigned to you', $lines, 'View Compliance', '/compliance');
    }

    public static function careManager(ServiceUser $serviceUser): AssignmentNotification
    {
        return new AssignmentNotification(
            'care_manager',
            'You are now care manager for '.self::name($serviceUser),
            ["You've been made the care manager for ".self::name($serviceUser).'.'],
            'View Client',
            "/service-users/{$serviceUser->id}",
        );
    }

    public static function carer(ServiceUser $serviceUser): AssignmentNotification
    {
        $lines = ["You've been added to ".self::name($serviceUser)."'s care team."];
        if ($serviceUser->address) {
            $lines[] = "Address: {$serviceUser->address}";
        }

        return new AssignmentNotification(
            'carer',
            "You've been added to ".self::name($serviceUser)."'s care team",
            $lines,
            'View Client',
            "/service-users/{$serviceUser->id}",
        );
    }

    /**
     * One email per person per care plan version, listing everything newly
     * given to them.
     *
     * @param  list<CarePlanSection>  $sections
     * @param  list<CarePlanRiskAssessment>  $risks
     */
    public static function carePlan(ServiceUser $serviceUser, int $version, array $sections, array $risks): AssignmentNotification
    {
        $name = self::name($serviceUser);
        $lines = ["You've been given responsibilities on {$name}'s care plan (version {$version})."];

        foreach ($sections as $section) {
            $lines[] = 'Responsible for care area: '.self::label($section->area)
                .($section->review_date ? ' (review by '.$section->review_date->toDateString().')' : '');
        }
        foreach ($risks as $risk) {
            $lines[] = "Action owner for risk: {$risk->hazard}"
                .($risk->action_due_date ? ' (action due '.$risk->action_due_date->toDateString().')' : '');
        }

        return new AssignmentNotification('care_plan', "Care plan responsibilities for {$name}", $lines, 'View Care Plan', "/service-users/{$serviceUser->id}");
    }

    public static function role(string $role, bool $newAccount): AssignmentNotification
    {
        return $newAccount
            ? new AssignmentNotification(
                'role',
                'Your CareNexa360 account is ready',
                ["An account has been created for you with the {$role} role.", 'Sign in with this email address. Ask your manager for your password if you were not given one.'],
                'Sign In',
                '/login',
            )
            : new AssignmentNotification(
                'role',
                "You've been given the {$role} role",
                ["Your role in CareNexa360 is now {$role}. What you can see and do in the app has changed to match."],
                'Open CareNexa360',
                '/',
            );
    }

    private static function name(ServiceUser $serviceUser): string
    {
        return trim("{$serviceUser->first_name} {$serviceUser->last_name}") ?: 'a client';
    }

    private static function label(?string $value): string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : '—';
    }
}

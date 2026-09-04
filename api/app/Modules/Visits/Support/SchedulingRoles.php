<?php

namespace App\Modules\Visits\Support;

class SchedulingRoles
{
    /**
     * Who can reassign a visit to a different carer or move its date/time —
     * the Schedule page's core action. Deliberately separate from who can
     * update a visit at all: a carer still needs to tick off their own
     * care_tasks/medication and check in/out (see VisitController::update,
     * which only applies this gate when carer_id/visit_date/start_time/
     * end_time are actually being changed).
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Care Coordinator',
    ];
}

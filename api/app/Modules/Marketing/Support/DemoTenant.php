<?php

namespace App\Modules\Marketing\Support;

/**
 * The shared, publicly-advertised login for demo-care-group — the fully
 * seeded sandbox tenant (see DatabaseSeeder/DemoDataSeeder) anyone who
 * requests a demo is pointed at, so they can explore real-looking data
 * immediately instead of waiting on a sales call. One login for everyone
 * who asks, not a fresh tenant per request — it's read-mostly sightseeing,
 * not a private trial, so there's nothing here worth provisioning in
 * isolation for each visitor.
 *
 * Lives on its own database and deployment (demo.carenexa360.co.uk), not
 * this app's own database — a visitor exploring the demo must never be able
 * to see a real customer's data, and vice versa.
 */
class DemoTenant
{
    public const LOGIN_EMAIL = 'orgadmin@demo-care-group.test';

    public const LOGIN_PASSWORD = 'CareNexa360Demo!';

    public const LOGIN_URL = 'https://demo.carenexa360.co.uk/login';
}

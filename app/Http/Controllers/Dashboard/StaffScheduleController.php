<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Contracts\View\View;

/**
 * The {staff} route parameter is resolved through the tenant scope (the tenant
 * is bound before route model binding runs), so another tenant's staff id is
 * simply a 404.
 */
final class StaffScheduleController extends Controller
{
    public function __invoke(Staff $staff): View
    {
        return view('dashboard.staff-schedule', ['staff' => $staff]);
    }
}

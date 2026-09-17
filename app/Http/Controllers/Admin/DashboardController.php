<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'templateCount' => CertificateTemplate::count(),
            'certificateCount' => Certificate::count(),
            'batchCount' => CertificateBatch::count(),
            'adminCount' => User::count(),
        ]);
    }
}

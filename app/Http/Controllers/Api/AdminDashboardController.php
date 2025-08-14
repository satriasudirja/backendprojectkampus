<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AdminDashboardService;
use App\Models\SimpegBerita;
use App\Models\SimpegUnitKerja;

class AdminDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(AdminDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get all dashboard data in a single endpoint
     */
    public function getDashboardData(Request $request)
    {
        $unitKerjaId = $request->input('unit_kerja_id');
        
        $data = [
            'staff_summary' => $this->dashboardService->getStaffSummary($unitKerjaId),
            'staff_distribution' => $this->dashboardService->getStaffDistribution($unitKerjaId),
            'work_relationships' => $this->dashboardService->getWorkRelationships($unitKerjaId),
            'education_distribution' => $this->dashboardService->getEducationDistribution($unitKerjaId),
            'academic_education' => $this->dashboardService->getAcademicEducationDistribution($unitKerjaId),
            'non_academic_education' => $this->dashboardService->getNonAcademicEducationDistribution($unitKerjaId),
            'news' => $this->dashboardService->getNews($unitKerjaId),
            'birthdays' => $this->dashboardService->getCurrentMonthBirthdays($unitKerjaId),
            'pensiun' => $this->dashboardService->getCurrentMonthPensiun($unitKerjaId),
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
    
    /**
     * Get detailed news by ID
     */
 public function getNewsDetail($id)
{
    $news = SimpegBerita::findOrFail($id);
    
    // Add the related unit kerja records manually
    $news->related_unit_kerja = $news->allUnitKerja();
    
    return response()->json([
        'status' => 'success',
        'data' => $news
    ]);
}
}
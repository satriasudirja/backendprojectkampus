<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUnitKerja;

class UnitKerjaController extends Controller
{
    /**
     * Get hierarchical units for dropdown
     */
    public function getUnitsDropdown()
    {
        // Get root units (university level)
        $rootUnits = SimpegUnitKerja::whereNull('parent_unit_id')
            ->orderBy('nama_unit')
            ->get(['kode_unit', 'nama_unit']);
            
        $result = [];
        
        foreach ($rootUnits as $rootUnit) {
            $unitData = [
                'id' => $rootUnit->kode_unit,
                'text' => $rootUnit->nama_unit,
                'children' => $this->getChildUnits($rootUnit->kode_unit)
            ];
            
            $result[] = $unitData;
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }
    
    /**
     * Recursively get child units
     */
    private function getChildUnits($parentId)
    {
        $childUnits = SimpegUnitKerja::where('parent_unit_id', $parentId)
            ->orderBy('nama_unit')
            ->get(['kode_unit', 'nama_unit']);
            
        $result = [];
        
        foreach ($childUnits as $childUnit) {
            $unitData = [
                'id' => $childUnit->kode_unit,
                'text' => $childUnit->nama_unit,
                'children' => $this->getChildUnits($childUnit->kode_unit)
            ];
            
            $result[] = $unitData;
        }
        
        return $result;
    }
    public function dropdown()
    {
        $unitKerja = SimpegUnitKerja::select('kode_unit as id', 'nama_unit as text')
            ->orderBy('nama_unit', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $unitKerja
        ]);
    }

    // Show method (modified to handle numeric vs UUID IDs)
    public function show($id)
    {
        // Check if the ID is "dropdown" and handle it specially
        if ($id === 'dropdown') {
            return $this->dropdown();
        }

        // Regular show logic
        $unitKerja = SimpegUnitKerja::find($id);

        if (!$unitKerja) {
            return response()->json(['success' => false, 'message' => 'Unit kerja tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $unitKerja
        ]);
    }
}
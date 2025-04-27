<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUserRole;
use App\Services\ActivityLogger;

class SimpegUserRoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = SimpegUserRole::orderBy('created_at', 'desc')->paginate(10);

        $prefix = $request->segment(2);

        $roles->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/role/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/role/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function show(Request $request, $id)
    {
        $role = SimpegUserRole::find($id);

        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $role,
            'update_url' => url("/api/{$prefix}/role/" . $role->id),
            'delete_url' => url("/api/{$prefix}/role/" . $role->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $role = SimpegUserRole::create([
            'nama' => $request->nama,
        ]);

        ActivityLogger::log('create', $role, $role->toArray());

        return response()->json([
            'success' => true,
            'data' => $role,
            'message' => 'Role berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = SimpegUserRole::find($id);

        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $old = $role->getOriginal();

        $role->update([
            'nama' => $request->nama,
        ]);

        $changes = array_diff_assoc($role->toArray(), $old);
        ActivityLogger::log('update', $role, $changes);

        return response()->json([
            'success' => true,
            'data' => $role,
            'message' => 'Role berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $role = SimpegUserRole::find($id);

        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
        }

        $roleData = $role->toArray();

        $role->delete();

        ActivityLogger::log('delete', $role, $roleData);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus (soft delete)'
        ]);
    }
}

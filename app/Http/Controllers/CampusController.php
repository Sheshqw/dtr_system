<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampusController extends Controller
{
    public function index(Request $request)
    {
        $campuses = Campus::withCount('employees')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => $this->format($c));

        return response()->json($campuses);
    }

    public function store(Request $request)
    {
        $this->gate($request->user());

        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:campuses,name',
            'code'          => 'required|string|max:20|unique:campuses,code',
            'address'       => 'nullable|string|max:255',
            'director_name' => 'nullable|string|max:120',
            'is_active'     => 'sometimes|boolean',
        ]);

        $campus = Campus::create($data);

        return response()->json($this->format($campus->loadCount('employees')), 201);
    }

    public function update(Request $request, Campus $campus)
    {
        $this->gate($request->user());

        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:campuses,name,' . $campus->id,
            'code'          => 'required|string|max:20|unique:campuses,code,' . $campus->id,
            'address'       => 'nullable|string|max:255',
            'director_name' => 'nullable|string|max:120',
            'is_active'     => 'sometimes|boolean',
        ]);

        $campus->update($data);

        return response()->json($this->format($campus->fresh()->loadCount('employees')));
    }

    /**
     * Upload or replace the campus's photo in the login page slideshow.
     */
    public function uploadPhoto(Request $request, Campus $campus)
    {
        $this->gate($request->user());

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $this->deleteStoredPhoto($campus);
        $campus->update(['photo_path' => $request->file('photo')->store('campuses', 'public')]);

        return response()->json($this->format($campus->fresh()->loadCount('employees')));
    }

    /**
     * Remove an uploaded photo — the campus goes back to its bundled official
     * photo, or out of the slideshow if it has none.
     */
    public function resetPhoto(Request $request, Campus $campus)
    {
        $this->gate($request->user());

        $this->deleteStoredPhoto($campus);
        $campus->update(['photo_path' => null]);

        return response()->json($this->format($campus->fresh()->loadCount('employees')));
    }

    public function destroy(Campus $campus)
    {
        $this->gate(request()->user());

        if ($campus->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete a campus with assigned employees.'], 422);
        }

        $this->deleteStoredPhoto($campus);
        $campus->delete();

        return response()->json(['message' => 'Campus deleted.']);
    }

    private function deleteStoredPhoto(Campus $campus): void
    {
        if ($campus->photo_path && Storage::disk('public')->exists($campus->photo_path)) {
            Storage::disk('public')->delete($campus->photo_path);
        }
    }

    private function format(Campus $c): array
    {
        return [
            'id'               => $c->id,
            'name'             => $c->name,
            'code'             => $c->code,
            'address'          => $c->address,
            'director_name'    => $c->director_name,
            'photo_url'        => $c->photo_url,
            'has_custom_photo' => (bool) $c->photo_path,
            'is_active'        => $c->is_active,
            'employees_count'  => $c->employees_count ?? 0,
        ];
    }

    private function gate(\App\Models\User $user): void
    {
        if ($user->role !== 'super_admin') {
            abort(403, 'Only Super Admin can manage campuses.');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = User::where('email', $request->email)->first();

        if (! $employee || ! $employee->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account not found or inactive.'],
            ]);
        }

        if ($employee->locked_until && $employee->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => ['Account is temporarily locked. Try again later.'],
            ]);
        }

        if (! Hash::check($request->password, $employee->password)) {
            $attempts = $employee->failed_login_attempts + 1;
            $employee->failed_login_attempts = $attempts;

            if ($attempts >= 5) {
                $employee->locked_until = now()->addMinutes(30);
            }

            $employee->save();

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $employee->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        $token = $employee->createToken('dtr-token')->plainTextToken;

        $employee->loadMissing('department', 'campus');

        return response()->json([
            'token'    => $token,
            'employee' => [
                'id'          => $employee->id,
                'name'        => $employee->full_name,
                'email'       => $employee->email,
                'role'        => $employee->role,
                'photo'       => $employee->photo_path,
                'employee_id' => $employee->employee_id,
                'department'  => $employee->department?->name,
                'position'    => $employee->position,
                'campus_id'   => $employee->campus_id,
                'campus'      => $employee->campus?->only('id', 'name', 'code'),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $employee = $request->user();

        if (! Hash::check($request->current_password, $employee->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $employee->update(['password' => Hash::make($request->password)]);
        $employee->tokens()->delete();

        $token = $employee->createToken('dtr-token')->plainTextToken;

        return response()->json([
            'message' => 'Password changed successfully.',
            'token'   => $token,
        ]);
    }

    public function me(Request $request)
    {
        $employee = $request->user()->load('department', 'shift');

        $employee->loadMissing('campus');

        return response()->json([
            'id'          => $employee->id,
            'name'        => $employee->full_name,
            'first_name'  => $employee->first_name,
            'last_name'   => $employee->last_name,
            'email'       => $employee->email,
            'role'        => $employee->role,
            'employee_id' => $employee->employee_id,
            'position'    => $employee->position,
            'photo'       => $employee->photo_path,
            'campus_id'   => $employee->campus_id,
            'campus'      => $employee->campus?->only('id', 'name', 'code'),
            'department'  => $employee->department?->only('id', 'name', 'code'),
            'shift'       => $employee->shift?->only('id', 'name', 'morning_in', 'afternoon_out'),
        ]);
    }
}

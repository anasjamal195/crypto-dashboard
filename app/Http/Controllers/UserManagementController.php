<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('role', 'LIKE', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Order by latest first
        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get unique roles for filter dropdown
        $roles = User::distinct()->pluck('role')->filter();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Remove the specified user from storage
     */
    public function destroy(User $user)
    {
        try {
            // Prevent admin from deleting themselves
            if ($user->id === Auth::id()) {
                return redirect()->route('admin.users.index')
                    ->with('error', 'You cannot delete your own account.');
            }

            // Store user name for success message
            $userName = $user->name;

            // Delete the user
            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('success', "User '{$userName}' has been deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')
                ->with('error', 'An error occurred while deleting the user. Please try again.');
        }
    }
}

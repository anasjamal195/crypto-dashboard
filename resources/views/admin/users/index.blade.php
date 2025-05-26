{{-- resources/views/admin/users/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <style>
        .user-management-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            color: white;
        }

        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .role-admin {
            background-color: #dc3545;
            color: white;
        }

        .role-trader {
            background-color: #28a745;
            color: white;
        }

        .role-user {
            background-color: #17a2b8;
            color: white;
        }

        .search-filters {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .btn-delete {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: white;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
    </style>

    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-users mr-2"></i>
                            User Management
                        </h6>
                        <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
                            <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fas fa-home"></i></a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">Users</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <span class="text-white">Total Users: {{ $users->total() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        {{-- Success/Error Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="row">
            <div class="col">
                <div class="card user-management-card">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <h3 class="mb-0">Users List</h3>
                            </div>
                        </div>
                    </div>

                    {{-- Search and Filter Section --}}
                    <div class="card-body pt-0">
                        <div class="search-filters">
                            <form method="GET" action="{{ route('admin.users.index') }}">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3 mb-md-0">
                                            <label for="search" class="form-label">Search Users</label>
                                            <input type="text" class="form-control" id="search" name="search"
                                                value="{{ request('search') }}"
                                                placeholder="Search by name, email, or role...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3 mb-md-0">
                                            <label for="role" class="form-label">Filter by Role</label>
                                            <select class="form-control" id="role" name="role">
                                                <option value="">All Roles</option>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role }}"
                                                        {{ request('role') == $role ? 'selected' : '' }}>
                                                        {{ ucfirst($role) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                                @if (request('search') || request('role'))
                                    <div class="row mt-2">
                                        <div class="col">
                                            <a href="{{ route('admin.users.index') }}"
                                                class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-times"></i> Clear Filters
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </form>
                        </div>

                        {{-- Users Table --}}
                        <div class="table-responsive">
                            <table class="table align-items-center table-flush">
                                <thead class="thead-light">
                                    <tr>
                                        <th scope="col">User</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Domain</th>
                                        <th scope="col">Joined</th>
                                        <th scope="col">Last Updated</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>
                                                <div class="media align-items-center">
                                                    <div class="user-avatar mr-3">
                                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                                    </div>
                                                    <div class="media-body">
                                                        <span
                                                            class="mb-0 text-sm font-weight-bold">{{ $user->name }}</span>
                                                        <br>
                                                        <small class="text-muted">ID: {{ $user->id }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-sm">{{ $user->email }}</span>
                                                @if ($user->email_verified_at)
                                                    <i class="fas fa-check-circle text-success ml-1" title="Verified"></i>
                                                @else
                                                    <i class="fas fa-exclamation-circle text-warning ml-1"
                                                        title="Not Verified"></i>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="role-badge role-{{ strtolower($user->role ?? 'user') }}">
                                                    {{ ucfirst($user->role ?? 'User') }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="">
                                                    {{ ucfirst($user->domain_name ?? 'Self Hosted') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-sm">{{ $user->created_at->format('M d, Y') }}</span>
                                                <br>
                                                <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                            </td>
                                            <td>
                                                <span class="text-sm">{{ $user->updated_at->format('M d, Y') }}</span>
                                                <br>
                                                <small class="text-muted">{{ $user->updated_at->diffForHumans() }}</small>
                                            </td>
                                            <td>
                                                @if ($user->id !== auth()->id())
                                                    <button type="button" class="btn btn-delete btn-sm"
                                                        onclick="confirmDelete({{ $user->id }}, '{{ $user->name }}')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                @else
                                                    <span class="text-muted text-sm">Current User</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                                <p class="text-muted mb-0">No users found</p>
                                                @if (request('search') || request('role'))
                                                    <small class="text-muted">Try adjusting your search criteria</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        @if ($users->hasPages())
                            <div class="card-footer py-4">
                                <nav aria-label="User pagination">
                                    {{ $users->appends(request()->query())->links() }}
                                </nav>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger"><small><i class="fas fa-exclamation-triangle"></i> This action cannot be
                            undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteForm').action = `/admin/users/${userId}`;
            $('#deleteModal').modal('show');
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
@endsection

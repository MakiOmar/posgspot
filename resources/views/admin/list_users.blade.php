<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Users List - {{ config('app.name', 'POS') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .user-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .user-type.user {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .user-type.admin_crm {
            background-color: #fff3e0;
            color: #f57c00;
        }
        .user-type.admin_super {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .role-badge {
            background-color: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-right: 5px;
        }
        .no-users {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            flex: 1;
            min-width: 150px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Admin Users Management</h1>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">{{ $admin_users->count() }}</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $admin_users->where('status', 'active')->count() }}</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $admin_users->where('user_type', 'admin_super')->count() }}</div>
                <div class="stat-label">Super Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $admin_users->where('user_type', 'admin_crm')->count() }}</div>
                <div class="stat-label">CRM Admins</div>
            </div>
        </div>

        <a href="{{ route('admin.users.create', ['password' => request()->get('password')]) }}" class="btn">
            ➕ Create New Admin User
        </a>

        @if($admin_users->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>User Type</th>
                        <th>Business</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admin_users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->surname }} {{ $user->first_name }} {{ $user->last_name }}</td>
                            <td><strong>{{ $user->username }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="user-type {{ $user->user_type }}">
                                    {{ ucfirst(str_replace('_', ' ', $user->user_type)) }}
                                </span>
                            </td>
                            <td>{{ $user->business->name ?? 'N/A' }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                    <span class="role-badge">{{ str_replace('#' . ($user->business_id ?? ''), '', $role->name) }}</span>
                                @endforeach
                                @if($user->roles->count() == 0)
                                    <span style="color: #999; font-style: italic;">No roles assigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-{{ $user->status }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-users">
                <h3>No Admin Users Found</h3>
                <p>There are currently no admin users in the system.</p>
                <a href="{{ route('admin.users.create', ['password' => request()->get('password')]) }}" class="btn">
                    Create First Admin User
                </a>
            </div>
        @endif
    </div>
</body>
</html>

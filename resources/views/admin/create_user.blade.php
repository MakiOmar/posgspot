<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Admin User</title>
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
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .required {
            color: #e74c3c;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .user-type-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .user-type-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .user-type-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .user-type-info li {
            margin-bottom: 5px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Create Admin User</h1>
        
        <div class="user-type-info">
            <h3>Admin User Types:</h3>
            <ul>
                <li><strong>User:</strong> Regular business user with assigned role</li>
                <li><strong>Admin CRM:</strong> Admin with CRM permissions for specific business</li>
                <li><strong>Admin Super:</strong> Super admin with system-wide permissions</li>
            </ul>
        </div>

        <div id="alert" class="alert"></div>

        <form id="adminUserForm" method="POST" action="{{ route('admin.users.store') }}">
            <input type="hidden" name="admin_password" value="{{ request()->get('password') }}">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="surname">Surname <span class="required">*</span></label>
                    <input type="text" id="surname" name="surname" required maxlength="10">
                    <div class="help-text">Maximum 10 characters</div>
                </div>
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" required minlength="4">
                    <div class="help-text">Minimum 4 characters, must be unique</div>
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                    <div class="help-text">Must be unique and valid email address</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <div class="help-text">Minimum 6 characters</div>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="business_id">Business <span class="required">*</span></label>
                    <select id="business_id" name="business_id" required>
                        <option value="">Select Business</option>
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}">{{ $business->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_type">User Type <span class="required">*</span></label>
                    <select id="user_type" name="user_type" required>
                        <option value="">Select User Type</option>
                        <option value="user">User</option>
                        <option value="admin_crm">Admin CRM</option>
                        <option value="admin_super">Admin Super</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn" id="submitBtn">Create Admin User</button>
            <button type="button" class="btn btn-secondary" onclick="submitFormFallback()" style="margin-top: 10px;">
                Submit (Fallback)
            </button>
        </form>

        <a href="{{ route('admin.users.index', ['password' => request()->get('password')]) }}" class="btn btn-secondary">
            View All Admin Users
        </a>
    </div>

    <script>
        // Add CSRF token to the form
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('adminUserForm');
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
        });

        document.getElementById('adminUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const alert = document.getElementById('alert');
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creating...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('{{ route("admin.users.store") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        alert.className = 'alert alert-success';
                        alert.innerHTML = `<strong>Success!</strong> ${result.msg}<br>User ID: ${result.user_id}<br>Username: ${result.username}`;
                        alert.style.display = 'block';
                        this.reset();
                    } else {
                        alert.className = 'alert alert-error';
                        alert.innerHTML = `<strong>Error!</strong> ${result.msg}`;
                        alert.style.display = 'block';
                    }
                } else {
                    // Handle HTTP error responses
                    let errorMsg = 'Server error occurred';
                    try {
                        const errorResult = await response.json();
                        errorMsg = errorResult.msg || errorResult.message || errorMsg;
                    } catch (e) {
                        errorMsg = `HTTP ${response.status}: ${response.statusText}`;
                    }
                    alert.className = 'alert alert-error';
                    alert.innerHTML = `<strong>Error!</strong> ${errorMsg}`;
                    alert.style.display = 'block';
                }
            } catch (error) {
                console.error('Network error:', error);
                alert.className = 'alert alert-error';
                alert.innerHTML = `<strong>Error!</strong> Network error occurred. Please check your connection and try again.<br><small>Details: ${error.message}</small>`;
                alert.style.display = 'block';
            } finally {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });

        // Fallback form submission function
        function submitFormFallback() {
            const form = document.getElementById('adminUserForm');
            const alert = document.getElementById('alert');
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            // Submit form normally (will cause page reload)
            alert.className = 'alert alert-success';
            alert.innerHTML = '<strong>Submitting...</strong> Please wait while the form is being processed.';
            alert.style.display = 'block';
            
            form.submit();
        }
    </script>
</body>
</html>

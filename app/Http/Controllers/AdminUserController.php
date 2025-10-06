<?php

namespace App\Http\Controllers;

use App\User;
use App\Business;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    protected $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    /**
     * Show the admin user creation form
     */
    public function create(Request $request)
    {
        // Password protection
        $password = $request->get('password');
        if ($password !== 'Dd918273@!@') {
            return response()->view('admin.password_protection', [], 401);
        }

        $businesses = Business::select('id', 'name')->get();
        return view('admin.create_user', compact('businesses'));
    }

    /**
     * Store a newly created admin user
     */
    public function store(Request $request)
    {
        // Password protection
        $password = $request->get('admin_password');
        if ($password !== 'Dd918273@!@') {
            return response()->json(['success' => false, 'msg' => 'Unauthorized access'], 401);
        }

        try {
            DB::beginTransaction();

            // Validation
            $validator = Validator::make($request->all(), [
                'surname' => 'required|string|max:10',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'username' => 'required|string|min:4|max:255|unique:users',
                'email' => 'required|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'business_id' => 'required|exists:businesses,id',
                'user_type' => 'required|in:user,admin_crm,admin_super'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user_details = $request->only([
                'surname', 'first_name', 'last_name', 'username', 'email', 
                'password', 'business_id', 'user_type'
            ]);

            // Hash password
            $user_details['password'] = Hash::make($user_details['password']);
            
            // Set additional required fields
            $user_details['allow_login'] = 1;
            $user_details['status'] = 'active';
            $user_details['selected_contacts'] = 0;
            $user_details['is_cmmsn_agnt'] = 0;
            $user_details['language'] = 'en';

            // Create the user
            $user = User::create($user_details);

            // Assign Admin role based on user type
            if ($user_details['user_type'] === 'admin_crm' || $user_details['user_type'] === 'admin_super') {
                $role_name = 'Admin#' . $user_details['business_id'];
                $role = Role::where('name', $role_name)
                           ->where('business_id', $user_details['business_id'])
                           ->first();
                
                if ($role) {
                    $user->assignRole($role->name);
                }
            }

            // For super admin, add to administrator_usernames
            if ($user_details['user_type'] === 'admin_super') {
                $admin_usernames = config('constants.administrator_usernames');
                $admin_list = $admin_usernames ? explode(',', $admin_usernames) : [];
                $admin_list[] = $user_details['username'];
                
                // Update environment variable (you might want to update .env file manually)
                // This is a simplified approach - in production you'd want to update .env file
                config(['constants.administrator_usernames' => implode(',', array_unique($admin_list))]);
            }

            DB::commit();

            $response = [
                'success' => true,
                'msg' => 'Admin user created successfully!',
                'user_id' => $user->id,
                'username' => $user->username
            ];

            // Handle AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($response);
            }

            // Handle regular form submissions
            return redirect()->route('admin.users.index', ['password' => $request->get('admin_password')])
                           ->with('status', $response);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $errorResponse = [
                'success' => false,
                'msg' => 'Error creating admin user: ' . $e->getMessage()
            ];

            // Handle AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($errorResponse, 500);
            }

            // Handle regular form submissions
            return redirect()->back()
                           ->withInput()
                           ->with('status', $errorResponse);
        }
    }

    /**
     * List all admin users
     */
    public function index(Request $request)
    {
        // Password protection
        $password = $request->get('password');
        if ($password !== 'Dd918273@!@') {
            return response()->view('admin.password_protection', [], 401);
        }

        $admin_users = User::with(['business', 'roles'])
                          ->where('user_type', '!=', 'user')
                          ->orWhereHas('roles', function($q) {
                              $q->where('name', 'like', 'Admin#%');
                          })
                          ->get();

        return view('admin.list_users', compact('admin_users'));
    }
}

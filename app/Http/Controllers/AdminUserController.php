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

        // Get all businesses for selection
        $businesses = Business::select('id', 'name')->get();
        
        // Get available roles for the first business (or default business)
        $default_business_id = $businesses->first()->id ?? 1;
        $roles = $this->util->getDropdownForRoles($default_business_id);
        
        return view('admin.create_user', compact('businesses', 'roles'));
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
                'business_id' => 'required|exists:business,id', // Fixed: use 'business' not 'businesses'
                'user_type' => 'required|in:user,admin_crm,admin_super',
                'role_id' => 'nullable|exists:roles,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Use the existing createUser method from Util class
            // We need to mock the request to include all required fields
            $mocked_request = new \Illuminate\Http\Request();
            $mocked_request->merge([
                'surname' => $request->input('surname'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'user_type' => $request->input('user_type'),
                'allow_login' => 1,
                'is_active' => 'active',
                'selected_contacts' => 0,
                'business_id' => $request->input('business_id')
            ]);

            // Mock the authenticated user for the createUser method
            $business_id = $request->input('business_id');
            
            // Create user details manually (similar to Util::createUser)
            $user_details = [
                'surname' => $request->input('surname'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'user_type' => $request->input('user_type'),
                'business_id' => $business_id,
                'allow_login' => 1,
                'status' => 'active',
                'selected_contacts' => 0,
                'is_cmmsn_agnt' => 0,
                'language' => 'en'
            ];

            // Create the user
            $user = User::create($user_details);

            // Assign role based on selection
            $role_id = $request->input('role_id');
            if ($role_id) {
                $role = Role::find($role_id);
                if ($role) {
                    $user->assignRole($role->name);
                }
            } else if ($user_details['user_type'] === 'admin_crm' || $user_details['user_type'] === 'admin_super') {
                // Fallback: Assign Admin role for admin types
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

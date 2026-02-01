<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('manage_users');
        return view('users.modern');
    }

    public function create()
    {
        $this->authorize('manage_users');
        $roles = Role::all();
        return view('users.modern-create', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_users');
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'is_active' => true,
        ]);

        $roles = Role::whereIn('id', $request->roles)->pluck('name')->toArray();
        $user->assignRole($roles);

        return redirect()->route('users.index')->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    public function show(User $user)
    {
        return view('users.modern-show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorize('manage_users');
        $roles = Role::all();
        return view('users.modern-edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage_users');
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        $data = $request->only('name', 'email', 'phone', 'is_active');
        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->pluck('name')->toArray();
            $user->syncRoles($roles);
        }

        return redirect()->route('users.index')->with('success', 'تم تحديث المستخدم');
    }

    public function destroy(User $user)
    {
        $this->authorize('manage_users');
        $user->delete();
        return back()->with('success', 'تم حذف المستخدم');
    }

    public function assignRoles(User $user, Request $request)
    {
        $this->authorize('manage_users');
        $request->validate(['roles' => 'required|array']);

        $roles = Role::whereIn('id', $request->roles)->pluck('name')->toArray();
        $user->syncRoles($roles);

        return back()->with('success', 'تم تحديث الأدوار');
    }

    public function tableData()
    {
        $this->authorize('manage_users');
        $users = User::with('roles')->where('is_hidden', false)->get();

        return DataTables::of($users)
            ->addColumn('roles', fn($row) => implode(', ', $row->getRoleNames()->toArray()))
            ->addColumn('status', fn($row) => $row->is_active ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-danger">معطل</span>')
            ->rawColumns(['status'])
            ->toJson();
    }
}

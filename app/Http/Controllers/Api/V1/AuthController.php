<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'يرجى إدخال البريد الإلكتروني',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'password.required' => 'يرجى إدخال كلمة المرور',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة',
                'errors' => ['email' => ['بيانات الدخول غير صحيحة']],
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->mePayload($request), 'message' => 'تم تسجيل الدخول بنجاح']);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->mePayload($request)]);
    }

    /**
     * Everything the SPA shell needs at start (plan 7.2): user, roles,
     * permissions, computed abilities, organization settings, unread count.
     */
    private function mePayload(Request $request): array
    {
        $user = $request->user();

        $abilities = [
            'may_spend' => $user->can('spend_money') || $user->hasRole('مندوب'),
            'may_manage_treasury' => $user->can('manage_treasury'),
            'may_approve_custody' => $user->can('approve_custody'),
            'may_create_custody' => $user->can('create_custody'),
            'may_transfer_custody' => $user->can('transfer_custody'),
            'may_direct_spend' => $user->can('direct_spend_from_treasury'),
            'may_manage_expense_items' => $user->can('manage_expense_items'),
            'may_view_all_records' => $user->can('view_all_records'),
            'may_view_all_expenses' => $user->can('view_all_expenses'),
            'may_manage_users' => $user->can('manage_users'),
            'may_manage_settings' => $user->can('manage_settings'),
            'may_manage_social_cases' => $user->can('manage_social_cases'),
            'may_review_social_case' => $user->can('review_social_case'),
            'may_add_comments' => $user->can('add_comments'),
            'owns_custody' => \App\Models\Custody::where('agent_id', $user->id)
                ->whereIn('status', ['accepted', 'active', 'partially_returned'])
                ->exists(),
            'is_supervisor' => $user->hasRole('مشرف') && ! $user->hasAnyRole(['مدير', 'محاسب', 'مندوب']),
            'is_agent' => $user->hasRole('مندوب'),
            'is_manager' => $user->hasRole('مدير'),
            'is_accountant' => $user->hasRole('محاسب'),
            'is_researcher' => $user->hasRole('باحث اجتماعي'),
        ];

        $settings = Setting::pluck('value', 'key');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'abilities' => $abilities,
            'organization' => [
                'name' => $settings->get('organization_name', config('app.name')),
                'logo' => $settings->get('organization_logo'),
                'email' => $settings->get('organization_email'),
            ],
            'unread_notifications_count' => \App\Models\Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
        ];
    }
}

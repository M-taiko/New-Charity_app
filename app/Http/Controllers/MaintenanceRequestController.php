<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('مدير') || $user->hasRole('محاسب') || $user->hasRole('مشرف')) {
            $requests = MaintenanceRequest::with(['reporter', 'assignee'])->latest()->get();
        } else {
            $requests = MaintenanceRequest::with(['reporter', 'assignee'])
                ->where('reported_by', $user->id)->latest()->get();
        }

        $stats = [
            'pending'     => MaintenanceRequest::where('status', 'pending')->count(),
            'in_progress' => MaintenanceRequest::where('status', 'in_progress')->count(),
            'resolved'    => MaintenanceRequest::where('status', 'resolved')->count(),
        ];

        return view('procurement.maintenance-requests.index', compact('requests', 'stats'));
    }

    public function create()
    {
        return view('procurement.maintenance-requests.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'location'    => 'nullable|string|max:255',
            'priority'    => 'required|in:low,medium,high,urgent',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'location', 'priority']);
        $data['reported_by'] = auth()->id();
        $data['status'] = 'pending';

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('maintenance-requests', 'public');
        }

        $mr = MaintenanceRequest::create($data);

        // Notify managers
        $managers = User::role('مدير')->get();
        foreach ($managers as $manager) {
            NotificationService::notifyUser(
                $manager->id,
                'طلب صيانة جديد',
                auth()->user()->name . ' أبلغ عن مشكلة: ' . $mr->title,
                'warning',
                $mr->id,
                'maintenance_request'
            );
        }

        ActivityLogService::log('created', 'طلب صيانة جديد: ' . $mr->title, $mr);

        return redirect()->route('maintenance-requests.show', $mr)
            ->with('success', 'تم إرسال طلب الصيانة');
    }

    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $maintenanceRequest->load(['reporter', 'assignee', 'reviewer']);
        $users = User::where('is_active', true)->where('is_hidden', false)->get();
        return view('procurement.maintenance-requests.show', compact('maintenanceRequest', 'users'));
    }

    public function assign(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeManager();

        $request->validate(['assigned_to' => 'required|exists:users,id']);

        $maintenanceRequest->update([
            'assigned_to' => $request->assigned_to,
            'status'      => 'in_progress',
            'reviewed_by' => auth()->id(),
        ]);

        NotificationService::notifyUser(
            $request->assigned_to,
            'تم تكليفك بطلب صيانة',
            'تم تكليفك بمعالجة: ' . $maintenanceRequest->title,
            'info',
            $maintenanceRequest->id,
            'maintenance_request'
        );

        NotificationService::notifyUser(
            $maintenanceRequest->reported_by,
            'جاري معالجة طلب صيانتك',
            'تم تكليف ' . User::find($request->assigned_to)->name . ' بمعالجة: ' . $maintenanceRequest->title,
            'info',
            $maintenanceRequest->id,
            'maintenance_request'
        );

        ActivityLogService::log('assigned', 'تكليف بطلب صيانة: ' . $maintenanceRequest->title, $maintenanceRequest);

        return back()->with('success', 'تم التكليف بالطلب');
    }

    public function resolve(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $user = auth()->user();
        // Assignee or manager can resolve
        if ($maintenanceRequest->assigned_to !== $user->id && !$user->hasRole('مدير') && !$user->hasRole('محاسب')) {
            abort(403);
        }

        $request->validate(['resolution_notes' => 'nullable|string|max:1000']);

        $maintenanceRequest->update([
            'status'           => 'resolved',
            'resolution_notes' => $request->resolution_notes,
            'resolved_at'      => now(),
        ]);

        NotificationService::notifyUser(
            $maintenanceRequest->reported_by,
            'تم حل مشكلة الصيانة',
            'تم حل: ' . $maintenanceRequest->title,
            'success',
            $maintenanceRequest->id,
            'maintenance_request'
        );

        ActivityLogService::log('completed', 'تم حل طلب صيانة: ' . $maintenanceRequest->title, $maintenanceRequest);

        return back()->with('success', 'تم تسجيل حل المشكلة');
    }

    public function reject(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeManager();

        $maintenanceRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
        ]);

        NotificationService::notifyUser(
            $maintenanceRequest->reported_by,
            'تم رفض طلب الصيانة',
            'تم رفض طلب: ' . $maintenanceRequest->title,
            'danger',
            $maintenanceRequest->id,
            'maintenance_request'
        );

        ActivityLogService::rejected($maintenanceRequest, 'رفض طلب صيانة: ' . $maintenanceRequest->title);

        return back()->with('success', 'تم رفض الطلب');
    }

    private function authorizeManager(): void
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            abort(403);
        }
    }
}

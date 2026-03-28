<?php

namespace App\Http\Controllers;

use App\Models\SocialCase;
use App\Models\SocialCaseDocument;
use App\Models\FamilyMember;
use App\Models\Notification;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialCaseController extends Controller
{
    public function index()
    {
        return view('social-cases.modern');
    }

    public function create()
    {
        return view('social-cases.modern-form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'national_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'assistance_type' => 'required|in:cash,monthly_salary,medicine,treatment,other',
            'assistance_other' => 'nullable|string|max:100',
            'researcher_id' => 'required|exists:users,id',
            // New fields from Excel
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,widowed,divorced',
            'family_members_count' => 'nullable|integer|min:1',
            'monthly_income' => 'nullable|numeric|min:0',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'health_conditions' => 'nullable|string',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'requested_amount' => 'nullable|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif,xlsx,xls',
            'family_members' => 'nullable|array',
            'family_members.*.name' => 'required|string|max:255',
            'family_members.*.relationship' => 'required|string|max:100',
            'family_members.*.gender' => 'required|in:male,female',
            'family_members.*.phone' => 'nullable|string|max:20',
        ]);

        DB::transaction(function() use ($request) {
            $case = SocialCase::create(array_merge(
                [
                    'researcher_id' => $request->researcher_id,
                    'status' => 'pending',
                ],
                $request->only([
                    'name', 'national_id', 'phone', 'description', 'assistance_type', 'assistance_other',
                    'address', 'city', 'district', 'birth_date', 'gender', 'marital_status',
                    'family_members_count', 'monthly_income', 'monthly_expenses',
                    'health_conditions', 'has_disability', 'disability_description',
                    'special_needs', 'requested_amount'
                ])
            ));

            // Create family members if provided
            if ($request->has('family_members') && is_array($request->family_members)) {
                foreach ($request->family_members as $member) {
                    if (!empty($member['name'])) {
                        $case->familyMembers()->create($member);
                    }
                }
            }

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = $file->getClientOriginalName();
                    $filePath = $file->store('social-cases/' . $case->id, 'public');

                    SocialCaseDocument::create([
                        'social_case_id' => $case->id,
                        'name' => $fileName,
                        'file_path' => $filePath,
                        'file_type' => $file->getClientOriginalExtension(),
                    ]);
                }
            }

            // Notify managers for review
            $this->notifyManagers($case);
        });

        return redirect()->route('social_cases.index')->with('success', 'تم إنشاء الحالة الاجتماعية بنجاح');
    }

    public function show(SocialCase $socialCase)
    {
        $socialCase->load('familyMembers');
        return view('social-cases.modern-show', compact('socialCase'));
    }

    public function edit(SocialCase $socialCase)
    {
        $this->authorize('manage_social_cases');
        return view('social-cases.modern-form', compact('socialCase'));
    }

    public function update(Request $request, SocialCase $socialCase)
    {
        $this->authorize('manage_social_cases');
        $request->validate([
            'name' => 'required|string|max:200',
            'national_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'assistance_type' => 'required|in:cash,monthly_salary,medicine,treatment,other',
            'researcher_id' => 'required|exists:users,id',
            // New fields from Excel
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,widowed,divorced',
            'family_members_count' => 'nullable|integer|min:1',
            'monthly_income' => 'nullable|numeric|min:0',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'health_conditions' => 'nullable|string',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'requested_amount' => 'nullable|numeric|min:0',
            'family_members' => 'nullable|array',
            'family_members.*.name' => 'required|string|max:255',
            'family_members.*.relationship' => 'required|string|max:100',
            'family_members.*.gender' => 'required|in:male,female',
            'family_members.*.phone' => 'nullable|string|max:20',
        ]);

        DB::transaction(function() use ($request, $socialCase) {
            $socialCase->update($request->only([
                'name', 'national_id', 'phone', 'description', 'assistance_type', 'researcher_id',
                'address', 'city', 'district', 'birth_date', 'gender', 'marital_status',
                'family_members_count', 'monthly_income', 'monthly_expenses',
                'health_conditions', 'has_disability', 'disability_description',
                'special_needs', 'requested_amount'
            ]));

            // Delete old family members
            $socialCase->familyMembers()->delete();

            // Create new family members if provided
            if ($request->has('family_members') && is_array($request->family_members)) {
                foreach ($request->family_members as $member) {
                    if (!empty($member['name'])) {
                        $socialCase->familyMembers()->create($member);
                    }
                }
            }
        });

        return redirect()->route('social_cases.index')->with('success', 'تم تحديث الحالة الاجتماعية');
    }

    public function approve(SocialCase $socialCase)
    {
        $this->authorize('review_social_case');
        $socialCase->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('success', 'تمت الموافقة على الحالة');
    }

    public function reject(SocialCase $socialCase, Request $request)
    {
        $this->authorize('review_social_case');
        $socialCase->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'internal_notes' => $request->notes,
        ]);

        return back()->with('success', 'تم رفض الحالة');
    }

    public function toggleActive(SocialCase $socialCase)
    {
        $this->authorize('manage_social_cases');

        $socialCase->update([
            'is_active' => !$socialCase->is_active,
        ]);

        $statusMessage = $socialCase->is_active
            ? 'تم تنشيط الحالة بنجاح'
            : 'تم إيقاف الحالة بنجاح';

        return redirect()
            ->route('social_cases.index')
            ->with('success', $statusMessage);
    }

    public function tableData()
    {
        $cases = SocialCase::with(['researcher', 'familyMembers'])->get();

        return DataTables::of($cases)
            ->addColumn('researcher_name', fn($row) => $row->researcher->name)
            ->addColumn('status_label', fn($row) => $this->getStatusLabel($row->status))
            ->addColumn('family_count', fn($row) => $row->familyMembers->count())
            ->addColumn('is_active', fn($row) => $row->is_active)
            ->rawColumns(['status_label'])
            ->toJson();
    }

    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => '<span class="badge bg-warning">قيد الانتظار</span>',
            'approved' => '<span class="badge bg-success">موافق عليه</span>',
            'rejected' => '<span class="badge bg-danger">مرفوض</span>',
            'completed' => '<span class="badge bg-secondary">مكتمل</span>',
        ];
        return $labels[$status] ?? '';
    }

    public function researcherCases()
    {
        $user = auth()->user();

        // Get researcher's cases with family members loaded
        $cases = SocialCase::where('researcher_id', $user->id)->with('familyMembers')->get();

        $totalCases = $cases->count();
        $pendingCases = $cases->where('status', 'pending')->count();
        $approvedCases = $cases->where('status', 'approved')->count();
        $rejectedCases = $cases->where('status', 'rejected')->count();

        return view('social-cases.researcher-cases', compact(
            'cases',
            'totalCases',
            'pendingCases',
            'approvedCases',
            'rejectedCases'
        ));
    }

    public function getFamilyMembers(SocialCase $socialCase)
    {
        $socialCase->load('familyMembers');
        return response()->json([
            'family_members' => $socialCase->familyMembers,
        ]);
    }

    private function notifyManagers($case)
    {
        $managers = \App\Models\User::role('مدير')->get();

        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => 'حالة اجتماعية جديدة',
                'message' => "تم إنشاء حالة اجتماعية جديدة: {$case->name}",
                'type' => 'info',
                'related_id' => $case->id,
                'related_type' => 'social_case',
            ]);
        }
    }
}

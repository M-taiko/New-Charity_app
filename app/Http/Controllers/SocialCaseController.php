<?php

namespace App\Http\Controllers;

use App\Models\SocialCase;
use App\Models\SocialCaseDocument;
use App\Models\FamilyMember;
use App\Models\Notification;
use App\Services\ActivityLogService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SocialCaseController extends Controller
{
    public function index()
    {
        return view('social-cases.modern');
    }

    public function create()
    {
        return view('social-cases.modern-form-unified');
    }

    public function store(Request $request)
    {
        // Full data entry - accept all fields
        $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:20',
            'affiliated_to' => 'required|string|max:255',
            'case_intake_status' => 'required|in:searched_by_phone,completed_externally,needs_research',
            'researcher_id' => 'required|exists:users,id',
            'nationality' => 'nullable|in:egyptian,other',
            'national_id' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'house_type' => 'nullable|string|max:100',
            'house_condition' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
            'income_source' => 'nullable|string|max:255',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'family_composition' => 'nullable|string|max:255',
            'children_count' => 'nullable|integer|min:0',
            'disabled_count' => 'nullable|integer|min:0',
            'disability_type' => 'nullable|string|max:255',
            'health_conditions' => 'nullable|string|max:500',
            'assistance_type' => 'nullable|string|max:100',
            'assistance_reason' => 'nullable|string|max:500',
            'other_assistance' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function() use ($request) {
            // Determine phase based on whether all fields are filled
            $allFieldsFilled = $request->filled('nationality') && $request->filled('address') &&
                             $request->filled('monthly_income') && $request->filled('assistance_type');
            $phase = $allFieldsFilled ? 2 : 1;

            $case = SocialCase::create(array_merge(
                [
                    'researcher_id' => $request->researcher_id,
                    'status' => 'pending',
                    'phase' => $phase,
                ],
                $request->only([
                    'name', 'phone', 'affiliated_to', 'case_intake_status',
                    'nationality', 'national_id', 'address', 'house_type',
                    'house_condition', 'monthly_income', 'income_source',
                    'monthly_expenses', 'family_composition', 'children_count',
                    'disabled_count', 'disability_type', 'health_conditions',
                    'assistance_type', 'assistance_reason', 'other_assistance',
                    'description'
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

            // Handle document uploads (new format)
            if ($request->filled('documents')) {
                try {
                    $documents = json_decode($request->documents, true);

                    if (is_array($documents)) {
                        foreach ($documents as $doc) {
                            if (!empty($doc['file']) && !empty($doc['name'])) {
                                // Decode base64 file data
                                $fileData = $doc['file'];
                                if (strpos($fileData, 'data:') === 0) {
                                    list($type, $fileData) = explode(';', $fileData);
                                    list(, $fileData) = explode(',', $fileData);
                                    $fileData = base64_decode($fileData);
                                }

                                // Generate unique filename
                                $fileName = time() . '_' . str_replace([' ', '/'], '_', $doc['name']);
                                $filePath = 'social-cases/' . $case->id . '/' . $fileName;

                                // Create directory if needed
                                if (!Storage::disk('public')->exists('social-cases/' . $case->id)) {
                                    Storage::disk('public')->makeDirectory('social-cases/' . $case->id);
                                }

                                // Save file
                                Storage::disk('public')->put($filePath, $fileData);

                                SocialCaseDocument::create([
                                    'social_case_id' => $case->id,
                                    'name' => $doc['name'],
                                    'file_path' => $filePath,
                                    'file_type' => $doc['type'] ?? '',
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error saving documents: ' . $e->getMessage());
                }
            }

            // Handle legacy file uploads if any
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

        ActivityLogService::log('created', 'تم إنشاء حالة اجتماعية جديدة');
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
        return view('social-cases.modern-form-unified', compact('socialCase'));
    }

    public function update(Request $request, SocialCase $socialCase)
    {
        $this->authorize('manage_social_cases');

        // Phase 2: Advance to full details
        if ($request->filled('advance_phase') && $request->advance_phase == 2) {
            // Validate phase 2 fields with national_id format check
            $rules = [
                'national_id' => 'required|string|max:20',
                'nationality' => 'required|in:egyptian,other',
                'name' => 'required|string|max:200',
                'phone' => 'required|string|max:20',
                'description' => 'nullable|string',
                'assistance_type' => 'required|in:cash,monthly_salary,medicine,treatment,other',
                'researcher_id' => 'required|exists:users,id',
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
            ];

            // Add national_id format validation for Egyptian nationality
            if ($request->nationality === 'egyptian') {
                $rules['national_id'] = 'required|regex:/^\d{14}$/';
            }

            $request->validate($rules);

            DB::transaction(function() use ($request, $socialCase) {
                $socialCase->update(array_merge(
                    ['phase' => 2],
                    $request->only([
                        'name', 'national_id', 'phone', 'description', 'assistance_type', 'researcher_id',
                        'address', 'city', 'district', 'birth_date', 'gender', 'marital_status',
                        'family_members_count', 'monthly_income', 'monthly_expenses',
                        'health_conditions', 'has_disability', 'disability_description',
                        'special_needs', 'requested_amount', 'nationality'
                    ])
                ));

                // Delete old family members and create new ones
                $socialCase->familyMembers()->delete();
                if ($request->has('family_members') && is_array($request->family_members)) {
                    foreach ($request->family_members as $member) {
                        if (!empty($member['name'])) {
                            $socialCase->familyMembers()->create($member);
                        }
                    }
                }
            });

            return redirect()->route('social_cases.show', $socialCase)->with('success', 'تم إتمام بيانات الحالة الاجتماعية');
        }

        // Full data update for both phases
        $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:20',
            'affiliated_to' => 'required|string|max:255',
            'case_intake_status' => 'required|in:searched_by_phone,completed_externally,needs_research',
            'researcher_id' => 'required|exists:users,id',
            'nationality' => 'nullable|in:egyptian,other',
            'national_id' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'house_type' => 'nullable|string|max:100',
            'house_condition' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
            'income_source' => 'nullable|string|max:255',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'family_composition' => 'nullable|string|max:255',
            'children_count' => 'nullable|integer|min:0',
            'disabled_count' => 'nullable|integer|min:0',
            'disability_type' => 'nullable|string|max:255',
            'health_conditions' => 'nullable|string|max:500',
            'assistance_type' => 'nullable|string|max:100',
            'assistance_reason' => 'nullable|string|max:500',
            'other_assistance' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'family_members' => 'nullable|array',
            'family_members.*.name' => 'required|string|max:255',
            'family_members.*.relationship' => 'required|string|max:100',
            'family_members.*.gender' => 'required|in:male,female',
            'family_members.*.phone' => 'nullable|string|max:20',
        ]);

        DB::transaction(function() use ($request, $socialCase) {
            // Determine phase based on whether all fields are filled
            $allFieldsFilled = $request->filled('nationality') && $request->filled('address') &&
                             $request->filled('monthly_income') && $request->filled('assistance_type');
            $newPhase = $allFieldsFilled ? 2 : 1;

            $socialCase->update(array_merge(
                ['phase' => $newPhase],
                $request->only([
                    'name', 'phone', 'affiliated_to', 'case_intake_status', 'researcher_id',
                    'nationality', 'national_id', 'address', 'house_type',
                    'house_condition', 'monthly_income', 'income_source',
                    'monthly_expenses', 'family_composition', 'children_count',
                    'disabled_count', 'disability_type', 'health_conditions',
                    'assistance_type', 'assistance_reason', 'other_assistance',
                    'description'
                ])
            ));

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

            // Handle document uploads (new format)
            if ($request->filled('documents')) {
                try {
                    $documents = json_decode($request->documents, true);

                    if (is_array($documents)) {
                        foreach ($documents as $doc) {
                            if (!empty($doc['file']) && !empty($doc['name'])) {
                                // Decode base64 file data
                                $fileData = $doc['file'];
                                if (strpos($fileData, 'data:') === 0) {
                                    list($type, $fileData) = explode(';', $fileData);
                                    list(, $fileData) = explode(',', $fileData);
                                    $fileData = base64_decode($fileData);
                                }

                                // Generate unique filename
                                $fileName = time() . '_' . str_replace([' ', '/'], '_', $doc['name']);
                                $filePath = 'social-cases/' . $socialCase->id . '/' . $fileName;

                                // Create directory if needed
                                if (!Storage::disk('public')->exists('social-cases/' . $socialCase->id)) {
                                    Storage::disk('public')->makeDirectory('social-cases/' . $socialCase->id);
                                }

                                // Save file
                                Storage::disk('public')->put($filePath, $fileData);

                                SocialCaseDocument::create([
                                    'social_case_id' => $socialCase->id,
                                    'name' => $doc['name'],
                                    'file_path' => $filePath,
                                    'file_type' => $doc['type'] ?? '',
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error saving documents: ' . $e->getMessage());
                }
            }
        });

        return redirect()->route('social_cases.show', $socialCase)->with('success', 'تم تحديث بيانات الحالة الاجتماعية بنجاح');
    }

    public function approve(SocialCase $socialCase)
    {
        $this->authorize('review_social_case');
        $socialCase->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        ActivityLogService::approved($socialCase, 'تمت الموافقة على الحالة الاجتماعية: ' . $socialCase->name);
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

        ActivityLogService::rejected($socialCase, 'تم رفض الحالة الاجتماعية: ' . $socialCase->name);
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

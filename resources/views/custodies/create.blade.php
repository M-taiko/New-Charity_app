@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>إنشاء عهدة جديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('custodies.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">المندوب <span class="text-danger">*</span></label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">اختر المندوب</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            @error('agent_id') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">المبلغ (ج.م) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" required value="{{ old('amount') }}">
                            @error('amount') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الملاحظات</label>
                            <textarea name="notes" class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <p class="alert alert-info">
                                رصيد الخزينة الحالي: <strong>{{ number_format($treasury->balance, 2) }} ج.م</strong>
                            </p>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">حفظ</button>
                            <a href="{{ route('custodies.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

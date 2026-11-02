# Part 2: remaining standard pages. Complex money pages (custody detail/form,
# expense form/detail, directions management, reports dashboard, chat,
# settings, profile) are hand-written separately.
import sys, os, importlib.util
_here = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location('gen_pages', os.path.join(_here, 'gen-pages.py'))
_mod = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_mod)
list_page, detail_page, write = _mod.list_page, _mod.detail_page, _mod.write

# ── Custodies ───────────────────────────────────────────────────────

write('custodies', 'list', 'list.component.ts', list_page({
    'selector': 'app-custody-list', 'class': 'CustodyListPage',
    'doc': '/** Custodies management list (plan 20): T9 status cards that add to the total. */',
    'title': 'custody.list.title', 'endpoint': '/custodies',
    'empty_title': 'custody.list.empty', 'empty_text': 'custody.list.empty_text',
    'row': {
        'id': 'number', 'agent': 'string | null', 'amount': 'string',
        'transferred_in': 'string', 'transferred_out': 'string', 'spent': 'string',
        'remaining': 'string', 'spent_percent': 'number', 'status': 'string',
        'status_detail': 'string | null', 'created_at': 'string | null',
    },
    'columns': [
        ('agent', 'custody.list.col_agent', 'text', True),
        ('amount', 'custody.list.col_amount', 'money', True),
        ('transferred_in', 'custody.list.col_transferred_in', 'money'),
        ('spent', 'custody.list.col_spent', 'money'),
        ('remaining', 'custody.list.col_remaining', 'money', True),
        ('spent_percent', 'custody.list.col_spent_percent', 'text'),
        ('status', 'custody.list.col_status', 'stamp', 'custody_status'),
    ],
    'summary': [
        ('custody.list.accepted', 'accepted', 'count'),
        ('custody.list.pending', 'pending', 'count'),
        ('custody.list.rejected', 'rejected', 'count'),
        ('custody.list.total', 'total', 'count'),
    ],
    'filters': [],
    'actions': "<p-button [label]=\"'custody.list.create' | translate\" size=\"small\" routerLink=\"/custodies/new\" />",
}))

write('custodies', 'tracking', 'tracking.component.ts', list_page({
    'selector': 'app-custody-tracking', 'class': 'TrackingPage',
    'doc': '/** All custodies tracking (plan 20): manager/accountant/supervisor; read-only for supervisor. */',
    'title': 'custody.tracking.title', 'endpoint': '/custodies/tracking',
    'empty_title': 'custody.tracking.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'agent': 'string | null', 'amount': 'string', 'spent': 'string',
        'returned': 'string', 'remaining': 'string', 'status': 'string', 'created_at': 'string | null',
    },
    'columns': [
        ('agent', 'custody.tracking.col_agent', 'text', True),
        ('amount', 'common.amount', 'money', True),
        ('spent', 'custody.tracking.spent', 'money'),
        ('returned', 'custody.tracking.returned', 'money'),
        ('remaining', 'custody.tracking.remaining', 'money'),
        ('status', 'common.status', 'stamp', 'custody_status'),
    ],
    'summary': [
        ('custody.tracking.total', 'total_custodies', 'count'),
        ('custody.tracking.active', 'active', 'count'),
        ('custody.tracking.pending', 'pending', 'count'),
        ('custody.tracking.remaining', 'total_remaining', 'money'),
    ],
    'filters': [],
    'actions': '',
}))

write('custodies', 'movements', 'movements.component.ts', list_page({
    'selector': 'app-custody-movements', 'class': 'MovementsPage',
    'doc': '/** Custody movements ledger (plan 20): date/type/agent filters + summary; printable. */',
    'title': 'custody.movements.title', 'endpoint': '/custodies/movements',
    'empty_title': 'custody.movements.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'type': 'string', 'amount': 'string',
        'description': 'string | null', 'user': 'string | null',
        'date': 'string | null', 'custody_id': 'number | null',
    },
    'columns': [
        ('date', 'custody.movements.col_date', 'date', True),
        ('type', 'custody.movements.col_type', 'enum', 'transaction_type'),
        ('custody_id', 'custody.movements.col_custody', 'text'),
        ('description', 'custody.movements.col_description', 'text'),
        ('user', 'custody.movements.col_by', 'text'),
        ('amount', 'common.amount', 'money', True),
    ],
    'summary': [
        ('custody.movements.received', 'received', 'money'),
        ('custody.movements.returned', 'returned', 'money'),
        ('custody.movements.transferred', 'transferred', 'money'),
        ('custody.movements.spent', 'spent', 'money'),
    ],
    'filters': [],
    'actions': '',
}))

write('custodies', 'mine-movements', 'mine-movements.component.ts', list_page({
    'selector': 'app-my-movements', 'class': 'MyMovementsPage',
    'doc': '/** My treasury movements (plan 20): tabs of my money flow. */',
    'title': 'custody.my_movements.title', 'endpoint': '/agent/transactions',
    'empty_title': 'custody.my_movements.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'type': 'string', 'amount': 'string',
        'description': 'string | null', 'user': 'string | null', 'date': 'string | null',
    },
    'columns': [
        ('date', 'common.date', 'date', True),
        ('type', 'common.type', 'enum', 'transaction_type'),
        ('description', 'common.description', 'text'),
        ('amount', 'common.amount', 'money', True),
    ],
    'summary': [
        ('custody.my_movements.custodies_count', 'custodies_count', 'count'),
        ('custody.my_movements.total_received', 'total_received', 'money'),
        ('custody.my_movements.total_spent', 'total_spent', 'money'),
        ('custody.my_movements.remaining', 'remaining', 'money'),
    ],
    'filters': [],
    'actions': '',
}))

write('custodies', 'return-requests', 'return-requests.component.ts', list_page({
    'selector': 'app-return-requests', 'class': 'ReturnRequestsPage',
    'doc': '/** Custody return requests (plan 20): approve-with-treasury or reject. */',
    'title': 'custody.return_requests.title', 'endpoint': '/custodies/return-requests',
    'empty_title': 'custody.return_requests.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'custody_id': 'number', 'agent': 'string | null',
        'requested_by': 'string | null', 'amount': 'string', 'reason': 'string | null',
        'status': 'string', 'created_at': 'string | null',
        'approved_by': 'string | null', 'rejection_reason': 'string | null',
    },
    'columns': [
        ('custody_id', 'custody.return_requests.col_number', 'text', True),
        ('agent', 'custody.return_requests.col_agent', 'text'),
        ('requested_by', 'custody.return_requests.col_requested_by', 'text'),
        ('amount', 'common.amount', 'money', True),
        ('reason', 'custody.return_requests.col_reason', 'text'),
        ('status', 'common.status', 'stamp', 'return_request'),
        ('created_at', 'common.date', 'date'),
    ],
    'summary': [],
    'filters': [],
    'actions': '',
}))

# ── Expenses ────────────────────────────────────────────────────────

write('expenses', 'list', 'list.component.ts', list_page({
    'selector': 'app-expense-list', 'class': 'ExpenseListPage',
    'doc': '/** All expenses (plan 20): T31 filters (direction, no-direction, custody, amount range) + filtered total. */',
    'title': 'expense.list.title', 'endpoint': '/expenses',
    'empty_title': 'expense.list.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'user': 'string | null', 'type': 'string', 'amount': 'string',
        'description': 'string | null', 'created_at': 'string | null', 'direction': 'string | null',
        'reviewed': 'boolean', 'reviewed_by': 'string | null', 'is_quick_expense': 'boolean',
        'has_attachment': 'boolean', 'can_review': 'boolean', 'can_unreview': 'boolean',
    },
    'columns': [
        ('user', 'expense.list.col_user', 'text'),
        ('type', 'expense.list.col_type', 'enum', 'expense_type'),
        ('direction', 'expense.list.col_direction', 'text'),
        ('amount', 'common.amount', 'money', True),
        ('created_at', 'expense.list.col_date', 'date', True),
        ('reviewed', 'expense.list.col_review', 'enum', 'review_state'),
    ],
    'summary': [
        ('expense.list.filtered_total', 'filtered_total', 'money'),
        ('common.count', 'count', 'count'),
    ],
    'filters': [],
    'actions': "<p-button [label]=\"'expense.list.create' | translate\" size=\"small\" routerLink=\"/expenses/new\" />\n        <p-button [label]=\"'expense.list.edit_requests' | translate\" size=\"small\" [outlined]=\"true\" routerLink=\"/expenses/edit-requests\" />",
}))

write('expenses', 'mine', 'mine.component.ts', list_page({
    'selector': 'app-my-expenses', 'class': 'MyExpensesPage',
    'doc': '/** My expenses (plan 20): agent role or custody owner. */',
    'title': 'expense.mine.title', 'endpoint': '/my-expenses',
    'empty_title': 'expense.mine.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'type': 'string', 'amount': 'string', 'description': 'string | null',
        'created_at': 'string | null', 'direction': 'string | null', 'reviewed': 'boolean',
    },
    'columns': [
        ('created_at', 'expense.mine.col_date', 'date', True),
        ('amount', 'common.amount', 'money', True),
        ('type', 'expense.mine.col_type', 'enum', 'expense_type'),
        ('description', 'expense.mine.col_description', 'text'),
    ],
    'summary': [
        ('expense.mine.total', 'total_amount', 'money'),
        ('expense.mine.count', 'count', 'count'),
        ('expense.mine.general', 'general_count', 'count'),
        ('expense.mine.cases', 'social_count', 'count'),
    ],
    'filters': [],
    'actions': "<p-button [label]=\"'expense.mine.create' | translate\" size=\"small\" routerLink=\"/expenses/new\" />",
}))

write('expenses', 'edit-requests', 'edit-requests.component.ts', list_page({
    'selector': 'app-edit-requests', 'class': 'EditRequestsPage',
    'doc': '/** Expense edit requests (plan 20, T16/T17). */',
    'title': 'expense.edit_requests.title', 'endpoint': '/expenses/edit-requests',
    'empty_title': 'expense.edit_requests.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'expense_id': 'number', 'requested_by': 'string | null',
        'original_amount': 'string', 'requested_amount': 'string', 'status': 'string',
        'requested_at': 'string | null',
    },
    'columns': [
        ('expense_id', 'expense.edit_requests.col_number', 'text', True),
        ('requested_by', 'expense.edit_requests.col_requester', 'text'),
        ('original_amount', 'expense.edit_requests.col_original', 'money'),
        ('requested_amount', 'expense.edit_requests.col_new', 'money'),
        ('status', 'common.status', 'stamp', 'edit_request'),
        ('requested_at', 'common.date', 'date'),
    ],
    'summary': [
        ('expense.edit_requests.pending', 'pending', 'count'),
        ('expense.edit_requests.approved', 'approved', 'count'),
        ('expense.edit_requests.rejected', 'rejected', 'count'),
    ],
    'filters': [],
    'actions': '',
}))

# ── Social cases ────────────────────────────────────────────────────

write('social-cases', 'list', 'list.component.ts', list_page({
    'selector': 'app-case-list', 'class': 'CaseListPage',
    'doc': '/** Social cases (plan 20): T34 governorate filter + chip. */',
    'title': 'case.list.title', 'endpoint': '/social-cases',
    'empty_title': 'case.list.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'name': 'string', 'phone': 'string | null',
        'researcher': 'string | null', 'assistance_type': 'string | null',
        'status': 'string', 'created_at': 'string | null', 'governorate': 'string | null',
    },
    'columns': [
        ('name', 'case.list.col_name', 'text', True),
        ('phone', 'case.list.col_phone', 'text'),
        ('governorate', 'case.form.governorate', 'text'),
        ('researcher', 'case.list.col_researcher', 'text'),
        ('status', 'case.list.col_status', 'stamp', 'social_case'),
        ('created_at', 'common.date', 'date'),
    ],
    'summary': [
        ('case.list.total', 'total', 'count'),
        ('case.list.pending', 'pending', 'count'),
        ('case.list.approved', 'approved', 'count'),
        ('case.list.rejected', 'rejected', 'count'),
    ],
    'filters': [],
    'actions': "<p-button [label]=\"'case.list.create' | translate\" size=\"small\" routerLink=\"/cases/new\" />",
}))

write('social-cases', 'mine', 'mine.component.ts', list_page({
    'selector': 'app-my-cases', 'class': 'MyCasesPage',
    'doc': '/** My cases — researcher only (plan 20). */',
    'title': 'case.mine.title', 'endpoint': '/my-cases',
    'empty_title': 'case.mine.empty', 'empty_text': 'common.no_data',
    'row': {
        'id': 'number', 'name': 'string', 'phone': 'string | null',
        'assistance_type': 'string | null', 'status': 'string', 'created_at': 'string | null',
    },
    'columns': [
        ('name', 'case.mine.col_name', 'text', True),
        ('phone', 'case.mine.col_phone', 'text'),
        ('assistance_type', 'case.mine.col_assistance', 'text'),
        ('status', 'case.mine.col_status', 'stamp', 'social_case'),
        ('created_at', 'case.mine.col_date', 'date'),
    ],
    'summary': [
        ('case.list.total', 'total', 'count'),
        ('case.list.pending', 'pending', 'count'),
        ('case.list.approved', 'approved', 'count'),
    ],
    'filters': [],
    'actions': "<p-button [label]=\"'case.mine.create' | translate\" size=\"small\" routerLink=\"/cases/new\" />",
}))

print('part2 done')

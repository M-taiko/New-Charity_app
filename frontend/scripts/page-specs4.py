import importlib.util, os
sp = importlib.util.spec_from_file_location('gen_pages', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'gen-pages.py'))
mod = importlib.util.module_from_spec(sp)
sp.loader.exec_module(mod)
list_page, write = mod.list_page, mod.write

S = [
  dict(feature='hr', slug='employees', cls='EmployeesPage', title='hr.employees.title', endpoint='/hr/employees', empty='hr.employees.empty',
    row={'id':'number','name':'string | null','department':'string | null','job_title':'string | null','hire_date':'string | null','salary':'string','status':'string'},
    cols=[('name','hr.employees.col_name','text'),('department','hr.employees.col_department','text'),('job_title','hr.employees.col_title','text'),('salary','hr.salaries.detail.base','money'),('status','common.status','text')], summary=[]),
  dict(feature='hr', slug='attendance', cls='AttendancePage', title='hr.attendance.title', endpoint='/hr/attendance', empty='hr.attendance.empty',
    row={'id':'number','employee':'string | null','date':'string | null','check_in':'string | null','check_out':'string | null','status':'string'},
    cols=[('employee','hr.employees.col_name','text'),('date','common.date','date'),('check_in','hr.attendance.check_in','text'),('check_out','hr.attendance.check_out','text'),('status','common.status','text')], summary=[]),
  dict(feature='hr', slug='kpi', cls='KpiPage', title='hr.kpi.title', endpoint='/hr/kpi', empty='hr.kpi.empty',
    row={'id':'number','employee':'string | null','name':'string','target':'string','actual':'string','percentage':'number','period_start':'string | null'},
    cols=[('employee','hr.kpi.col_employee','text'),('name','hr.kpi.col_name','text'),('target','hr.kpi.col_target','money'),('actual','hr.kpi.col_actual','money'),('percentage','hr.kpi.col_percent','text')], summary=[]),
  dict(feature='hr', slug='salaries', cls='SalariesPage', title='hr.salaries.title', endpoint='/hr/salaries', empty='hr.salaries.empty',
    row={'id':'number','employee':'string | null','year':'number','month':'number','base_salary':'string','final_salary':'string','status':'string'},
    cols=[('employee','hr.salaries.col_name','text'),('year','hr.salaries.year','text'),('month','hr.salaries.month','text'),('base_salary','hr.salaries.col_base','money'),('final_salary','hr.salaries.col_final','money'),('status','common.status','text')],
    summary=[('hr.salaries.detail.title','total_final','money')]),
  dict(feature='procurement', slug='purchase-list', cls='PurchaseListPage', title='procurement.purchase.list_title', endpoint='/purchase-requests', empty='procurement.purchase.empty',
    row={'id':'number','title':'string','category':'string | null','priority':'string','estimated_cost':'string','status':'string','requested_by':'string | null','created_at':'string | null'},
    cols=[('title','procurement.purchase.col_title','text'),('category','procurement.purchase.col_category','text'),('priority','procurement.purchase.col_priority','text'),('estimated_cost','procurement.purchase.col_cost','money'),('status','common.status','text'),('requested_by','procurement.purchase.col_requester','text')],
    summary=[('procurement.purchase.pending','pending','count'),('procurement.purchase.approved','approved','count'),('procurement.purchase.purchased','purchased','count')]),
  dict(feature='procurement', slug='maintenance-list', cls='MaintenanceListPage', title='procurement.maintenance.list_title', endpoint='/maintenance-requests', empty='procurement.maintenance.empty',
    row={'id':'number','title':'string','location':'string | null','priority':'string','status':'string','reported_by':'string | null','assigned_to':'string | null','created_at':'string | null'},
    cols=[('title','procurement.maintenance.col_title','text'),('location','procurement.maintenance.col_location','text'),('priority','procurement.maintenance.col_priority','text'),('status','common.status','text'),('reported_by','procurement.maintenance.col_reporter','text')],
    summary=[('procurement.maintenance.pending','pending','count'),('procurement.maintenance.in_progress','in_progress','count'),('procurement.maintenance.resolved','resolved','count')]),
  dict(feature='procurement', slug='suppliers', cls='SuppliersPage', title='procurement.suppliers.title', endpoint='/suppliers', empty='procurement.suppliers.empty',
    row={'id':'number','name':'string','phone':'string | null','email':'string | null','purchase_requests_count':'number','is_active':'boolean'},
    cols=[('name','procurement.suppliers.col_name','text'),('phone','procurement.suppliers.col_phone','text'),('email','procurement.suppliers.col_email','text'),('purchase_requests_count','procurement.suppliers.col_requests','text')], summary=[]),
  dict(feature='tasks', slug='list', cls='TaskListPage', title='task.list.title', endpoint='/tasks', empty='task.list.empty',
    row={'id':'number','title':'string','assigned_to':'string | null','status':'string','priority':'string','due_date':'string | null'},
    cols=[('title','task.list.col_title','text'),('assigned_to','task.list.col_assignee','text'),('status','common.status','text'),('priority','task.list.col_priority','text'),('due_date','task.list.col_due','date')],
    summary=[('task.list.pending','pending','count'),('task.list.in_progress','in_progress','count'),('task.list.completed','completed','count')]),
  dict(feature='notifications', slug='list', cls='NotificationsPage', title='notification.title', endpoint='/notifications', empty='notification.empty',
    row={'id':'number','title':'string','message':'string','type':'string','is_read':'boolean','created_at':'string | null'},
    cols=[('title','common.name','text'),('message','common.description','text'),('created_at','common.date','date')], summary=[]),
  dict(feature='broadcasts', slug='list', cls='BroadcastsPage', title='broadcast.title', endpoint='/broadcasts', empty='broadcast.empty',
    row={'id':'number','title':'string','message':'string','level':'string','is_active':'boolean','sender':'string | null','created_at':'string | null'},
    cols=[('title','broadcast.col_title','text'),('level','broadcast.col_level','text'),('sender','broadcast.col_sender','text'),('created_at','broadcast.col_date','date')], summary=[]),
  dict(feature='activity', slug='list', cls='ActivityListPage', title='activity.title', endpoint='/activity-logs', empty='activity.empty',
    row={'id':'number','user':'string | null','event':'string','subject_type':'string | null','description':'string | null','created_at':'string | null'},
    cols=[('created_at','activity.col_time','date'),('user','activity.col_user','text'),('event','activity.col_event','text'),('description','activity.col_description','text')], summary=[]),
  dict(feature='activity', slug='mine', cls='MyActivityPage', title='activity.mine_title', endpoint='/my-activity', empty='activity.empty',
    row={'id':'number','user':'string | null','event':'string','description':'string | null','created_at':'string | null'},
    cols=[('created_at','activity.col_time','date'),('event','activity.col_event','text'),('description','activity.col_description','text')], summary=[]),
  dict(feature='users', slug='list', cls='UserListPage', title='user.list.title', endpoint='/users', empty='user.list.empty',
    row={'id':'number','name':'string','email':'string','phone':'string | null','roles':'string[]','is_active':'boolean'},
    cols=[('name','user.list.col_name','text'),('email','user.list.col_email','text'),('phone','user.list.col_phone','text'),('is_active','user.list.col_status','text')],
    summary=[('user.list.total','total','count'),('user.list.active','active','count')]),
]

for s in S:
    write(s['feature'], s['slug'], s['slug'] + '.component.ts', list_page({
        'selector': 'app-' + s['slug'] + '-page', 'class': s['cls'],
        'doc': '/** ' + s['title'] + ' (plan 20). */',
        'title': s['title'], 'endpoint': s['endpoint'],
        'empty_title': s['empty'], 'empty_text': 'common.no_data',
        'row': s['row'], 'columns': [tuple(c) for c in s['cols']],
        'summary': [tuple(x) for x in s['summary']],
        'filters': [], 'actions': '',
    }))
print('lists generated:', len(S))

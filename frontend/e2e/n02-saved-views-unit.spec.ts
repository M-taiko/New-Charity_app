import { expect, test } from '@playwright/test';
import {
  MAX_SAVED_VIEWS,
  activeView,
  deleteView,
  emptyViews,
  findView,
  nameProblem,
  parseViews,
  renameView,
  saveView,
  setActiveView,
  setDefaultView,
  snapshotKey,
  viewsStorageKey,
  type SavedViews,
  type ViewSnapshot,
} from '../src/app/shared/lib/saved-views';

/**
 * N-02 — the rules of saved views, apart from the table and the browser:
 * the key they are stored under, the limit, what makes two states the same
 * view, and what is read back from storage.
 */

const A: ViewSnapshot = { filters: { status: 'pending' }, q: '  بنزين ', sort: '-amount', hiddenColumns: ['type', 'notes'] };
const B: ViewSnapshot = { filters: {}, q: '', sort: 'created_at', hiddenColumns: [] };

function saved(name: string, snapshot: ViewSnapshot = A, state: SavedViews = emptyViews()): SavedViews {
  const result = saveView(state, snapshot, name, 1000, () => `id-${name}`);
  if (!result.ok) {
    throw new Error(`could not save ${name}: ${result.problem}`);
  }
  return result.state;
}

function ten(): SavedViews {
  let state = emptyViews();
  for (let index = 1; index <= 10; index++) {
    state = saved(`عرض ${index}`, B, state);
  }
  return state;
}

test.describe('N-02 the saved-views store', () => {
  test('views are stored under the table, then the signed-in user — and apart for every list', () => {
    expect(viewsStorageKey('custodies/list.component', 12)).toBe('table.custodies/list.component.views.12');
    expect(viewsStorageKey('expenses/list.component', 12)).not.toBe(viewsStorageKey('custodies/list.component', 12));
    expect(viewsStorageKey('expenses/list.component', 13)).not.toBe(viewsStorageKey('expenses/list.component', 12));
    // no user yet: one place for those, never mixed with a user's
    expect(viewsStorageKey('expenses/list.component', null)).toBe('table.expenses/list.component.views.anonymous');
    expect(viewsStorageKey('expenses/list.component', undefined)).toBe('table.expenses/list.component.views.anonymous');
  });

  test('a saved view holds the table as it was, under its own copy, and becomes the one on screen', () => {
    const filters = { status: 'pending' };
    const result = saveView(emptyViews(), { ...A, filters }, '  عرض   المعلقة  ', 5000, () => 'v1');
    expect(result.ok).toBe(true);
    if (!result.ok) {
      return;
    }
    const view = result.view;
    expect(view).toMatchObject({ id: 'v1', name: 'عرض المعلقة', savedAt: 5000, q: 'بنزين', sort: '-amount', hiddenColumns: ['type', 'notes'] });
    expect(result.state.views).toEqual([view]);
    expect(result.state.activeId).toBe('v1');
    // the view keeps its own copy: the table moving on does not change it
    (filters as Record<string, string>).status = 'closed';
    expect(view.filters).toEqual({ status: 'pending' });
  });

  test('at most ten views; the eleventh is refused', () => {
    expect(MAX_SAVED_VIEWS).toBe(10);
    const full = ten();
    expect(full.views).toHaveLength(10);
    const result = saveView(full, A, 'عرض جديد');
    expect(result).toEqual({ ok: false, problem: 'limit' });
    // a place frees up with a deletion
    const after = deleteView(full, 'id-عرض 4');
    expect(saveView(after, A, 'عرض جديد').ok).toBe(true);
  });

  test('a name is refused when empty or already used — whatever its case or spacing', () => {
    const state = saved('عرض   المعلقة');
    expect(nameProblem(state, '')).toBe('empty');
    expect(nameProblem(state, '   ')).toBe('empty');
    expect(nameProblem(state, 'عرض المعلقة')).toBe('taken');
    expect(nameProblem(state, 'عرض   المعلقة')).toBe('taken');
    expect(nameProblem(state, ' عرض آخر ')).toBeNull();
    expect(saveView(state, A, 'عرض المعلقة')).toEqual({ ok: false, problem: 'taken' });
  });

  test('two states are the same view when what they filter is the same — not when a value, the sort, the search or a column differs', () => {
    expect(snapshotKey(A)).toBe(snapshotKey({ ...A, filters: { status: 'pending', other: undefined, empty: '', none: null, list: [] } }));
    // the order of keys and of hidden columns is not part of it
    expect(snapshotKey({ ...A, filters: { b: '2', a: '1' } })).toBe(snapshotKey({ ...A, filters: { a: '1', b: '2' } }));
    expect(snapshotKey({ ...A, hiddenColumns: ['notes', 'type'] })).toBe(snapshotKey(A));
    expect(snapshotKey({ ...A, q: 'بنزين' })).toBe(snapshotKey(A));
    // a range with an empty part is the same range
    expect(snapshotKey({ ...A, filters: { amount: { min: 5, max: undefined } } })).toBe(snapshotKey({ ...A, filters: { amount: { min: 5 } } }));

    expect(snapshotKey(A)).not.toBe(snapshotKey({ ...A, filters: { status: 'closed' } }));
    expect(snapshotKey(A)).not.toBe(snapshotKey({ ...A, sort: 'amount' }));
    expect(snapshotKey(A)).not.toBe(snapshotKey({ ...A, q: 'بنزين آخر' }));
    expect(snapshotKey(A)).not.toBe(snapshotKey({ ...A, hiddenColumns: ['type'] }));
    expect(snapshotKey(A)).not.toBe(snapshotKey({ ...A, filters: { amount: { min: 5, max: 10 } } }));
  });

  test('the view on screen says when the table moved away from it', () => {
    let state = saved('عرض');
    expect(findView(state, 'id-عرض')?.name).toBe('عرض');
    expect(findView(state, 'no-such-view')).toBeNull();
    expect(findView(state, null)).toBeNull();
    const active = activeView(state, A);
    expect(active?.edited).toBe(false);
    expect(active?.view.name).toBe('عرض');
    expect(activeView(state, { ...A, q: 'شيء آخر' })?.edited).toBe(true);
    // after leaving it there is no view on screen, the table is not compared any more
    state = setActiveView(state, null);
    expect(activeView(state, A)).toBeNull();
  });

  test('renaming keeps the id; a name already used leaves it as it was', () => {
    const state = saved('أ', A, saved('ب', B));
    const renamed = renameView(state, 'id-أ', '  أول  ');
    expect(renamed.views.map((view) => view.name)).toEqual(['ب', 'أول']);
    expect(renamed.views[0].id).toBe('id-ب');
    expect(renameView(renamed, 'id-أ', 'ب')).toBe(renamed);
    expect(renameView(renamed, 'id-أ', '   ')).toBe(renamed);
  });

  test('deleting the default or the view on screen clears that role too', () => {
    let state = setDefaultView(saved('أ', A), 'id-أ');
    state = setActiveView(state, 'id-أ');
    const after = deleteView(state, 'id-أ');
    expect(after).toEqual(emptyViews());
    // deleting another view leaves them
    state = saved('ب', B, state);
    state = setDefaultView(state, 'id-ب');
    expect(deleteView(state, 'id-nothing')).toBe(state);
    expect(deleteView(state, 'id-nothing').defaultId).toBe('id-ب');
  });

  test('the default view is the one a fresh visit starts with; it can be withdrawn', () => {
    const state = setDefaultView(saved('أ'), 'id-أ');
    expect(state.defaultId).toBe('id-أ');
    expect(setDefaultView(state, null).defaultId).toBeNull();
  });

  test('what was stored is read back whole', () => {
    const state = setActiveView(setDefaultView(ten(), 'id-عرض 3'), 'id-عرض 7');
    expect(parseViews(JSON.parse(JSON.stringify(state)))).toEqual(state);
  });

  test('a damaged entry never breaks the table: what is not well-formed is dropped', () => {
    expect(parseViews(null)).toEqual(emptyViews());
    expect(parseViews('views')).toEqual(emptyViews());
    expect(parseViews([])).toEqual(emptyViews());
    expect(parseViews({ views: 'no' })).toEqual(emptyViews());
    expect(parseViews({ views: [null, 5, 'x', {}, { id: '', name: 'n' }, { id: 'a', name: '  ' }, { id: 'b' }] })).toEqual(emptyViews());

    const parsed = parseViews({
      views: [
        { id: 'a', name: 'عرض', filters: { status: 'pending', bad: 42 as unknown, range: { min: '5' } }, q: 3 as unknown, sort: null as unknown, hiddenColumns: ['type', 7 as unknown, 'notes'] },
        { id: 'a', name: 'مكرر بالمعرّف' },
        { id: 'c', name: 'عرض' },
      ],
      defaultId: 'zzz',
      activeId: 'a',
    });
    expect(parsed.views).toHaveLength(1);
    expect(parsed.views[0]).toEqual({ id: 'a', name: 'عرض', savedAt: 0, filters: { status: 'pending', range: { min: '5' } }, q: '', sort: '', hiddenColumns: ['type', 'notes'] });
    expect(parsed.defaultId).toBeNull();
    expect(parsed.activeId).toBe('a');
  });

  test('never more than ten are read, however many were stored', () => {
    const stored = { views: Array.from({ length: 14 }, (_, index) => ({ id: `v${index}`, name: `عرض ${index}` })) };
    expect(parseViews(stored).views).toHaveLength(10);
  });
});

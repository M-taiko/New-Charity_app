import { expect, test } from '@playwright/test';
import {
  SEARCH_DEBOUNCE_MS,
  SEARCH_MIN_CHARS,
  answerIsFor,
  moveActive,
  needsRecordSearch,
  pageMatches,
  paletteItems,
  sectionStart,
  splitMatch,
  type GroupInfo,
  type PageEntry,
  type SearchAnswer,
} from '../src/app/shared/lib/command-palette';

/**
 * N-01 — the command palette's record search, the decisions that need
 * neither Angular nor the network: when to ask the server, how a match is
 * highlighted, and how pages, records and "see all" links become the one
 * list the arrow keys walk down.
 */

const CUSTODIES: GroupInfo = { key: 'custodies', label: 'custodies', icon: 'wallet', list: '/custodies', detail: '/custodies' };
const EXPENSES: GroupInfo = { key: 'expenses', label: 'expenses', icon: 'receipt', list: '/expenses/mine', detail: '/expenses' };
const USERS: GroupInfo = { key: 'users', label: 'users', icon: 'users', list: '/users', detail: '/users' };

const PAGES: PageEntry[] = [
  { label: 'الخزينة', route: '/treasury', icon: 'wallet' },
  { label: 'المصروفات', route: '/expenses', icon: 'receipt' },
  { label: 'تفاصيل المصروف', route: '/expenses/:id', icon: 'receipt' },
];

const HITS = {
  custodies: [
    { id: 43, title: 'حسام إسماعيل', subtitle: 'الخزينة الرئيسية' },
    { id: 41, title: 'حسام إسماعيل', subtitle: null },
  ],
  expenses: [{ id: 149, title: 'تسديد سلفة حسام', subtitle: 'حسام إسماعيل' }],
  social_cases: [],
  users: [{ id: 3, title: 'حسام إسماعيل', subtitle: 'h@example.test' }],
};

const GROUPS = [CUSTODIES, EXPENSES, USERS];

function answer(q: string): SearchAnswer {
  return { data: HITS, meta: { q, per_group: 5, groups: ['custodies', 'expenses', 'users'] } };
}

test.describe('N-01 when to ask the server', () => {
  test('two characters at least, and only what was typed — not the spaces around it', () => {
    expect(SEARCH_MIN_CHARS).toBe(2);
    expect(SEARCH_DEBOUNCE_MS).toBe(250);
    expect(needsRecordSearch('')).toBe(false);
    expect(needsRecordSearch('ح')).toBe(false);
    expect(needsRecordSearch('  ')).toBe(false);
    expect(needsRecordSearch('حسام')).toBe(true);
    expect(needsRecordSearch(' حس ')).toBe(true);
  });

  test('an answer belongs to the term it was asked for, whatever the spaces', () => {
    expect(answerIsFor(answer('حسام'), 'حسام')).toBe(true);
    expect(answerIsFor(answer('حسام'), ' حسام ')).toBe(true);
    expect(answerIsFor(answer('حسام'), 'حسامي')).toBe(false);
  });
});

test.describe('N-01 what is matched and how it is marked', () => {
  test('a page matches by its label or its route, whatever the case', () => {
    expect(pageMatches(PAGES[0], 'خزين')).toBe(true);
    expect(pageMatches(PAGES[2], '/expenses/')).toBe(true);
    expect(pageMatches(PAGES[0], 'HR')).toBe(false);
    expect(pageMatches({ label: 'الموارد البشرية', route: '/hr', icon: 'x' }, 'hr')).toBe(true);
    expect(pageMatches(PAGES[0], '')).toBe(true);
  });

  test('the first occurrence of what was typed, in three parts', () => {
    expect(splitMatch('حسام إسماعيل', 'حسام')).toEqual({ before: '', match: 'حسام', after: ' إسماعيل' });
    expect(splitMatch('سلفة حسام الشهر', 'حسام')).toEqual({ before: 'سلفة ', match: 'حسام', after: ' الشهر' });
    expect(splitMatch('تسديد سلفة حسام', ' حسام ')).toEqual({ before: 'تسديد سلفة ', match: 'حسام', after: '' });
    // the case of what was typed is not the text's: the text keeps its own
    expect(splitMatch('Hossam expenses', 'hossam')).toEqual({ before: '', match: 'Hossam', after: ' expenses' });
    expect(splitMatch('حسام إسماعيل', 'عمر')).toBeNull();
    expect(splitMatch('حسام إسماعيل', '')).toBeNull();
  });
});

test.describe('N-01 the one list the arrow keys walk down', () => {
  test('nothing typed, nothing asked: the pages alone', () => {
    const items = paletteItems({ term: '', pages: PAGES, groups: GROUPS, hits: null });
    expect(items.map((item) => item.kind)).toEqual(['page', 'page', 'page']);
  });

  test('with an answer: the pages that match, then each group, and "see all" only after a group that has something', () => {
    // "حسام" is in no page's label or route: records only, in the groups' order
    const items = paletteItems({ term: 'حسام', pages: PAGES, groups: GROUPS, hits: HITS });
    expect(items.map((item) => item.kind)).toEqual(['hit', 'hit', 'all', 'hit', 'all', 'hit', 'all']);
    if (items[0].kind !== 'hit' || items[2].kind !== 'all') {
      throw new Error('the order changed');
    }
    expect(items[0].hit.id).toBe(43);
    expect(items[2].group.key).toBe('custodies');
    // the page's own link for "see all": this user's "my expenses"
    const expensesAll = items[4];
    expect(expensesAll.kind === 'all' && expensesAll.group.list).toBe('/expenses/mine');

    // a term that is in a page's label and in the answer: the page comes first
    const mixed = paletteItems({ term: 'مصروف', pages: PAGES, groups: GROUPS, hits: HITS });
    expect(mixed.map((item) => item.kind)).toEqual(['page', 'page', 'hit', 'hit', 'all', 'hit', 'all', 'hit', 'all']);
    expect(mixed[0].kind === 'page' && mixed[0].entry.route).toBe('/expenses');
  });

  test('a group with no results shows neither its records nor a "see all"', () => {
    const items = paletteItems({ term: 'zz', pages: [], groups: [...GROUPS, { key: 'social_cases', label: 'cases', icon: 'x', list: '/social-cases', detail: '/social-cases' }], hits: HITS });
    expect(items.filter((item) => item.kind !== 'page' && item.group.key === 'social_cases')).toEqual([]);
  });

  test('no more than five matching pages', () => {
    const many = Array.from({ length: 9 }, (_, index): PageEntry => ({ label: `مصروف ${index}`, route: `/e${index}`, icon: 'x' }));
    expect(paletteItems({ term: 'مصروف', pages: many, groups: [], hits: null })).toHaveLength(5);
  });

  test('each heading comes once, at the first item of its section', () => {
    const items = paletteItems({ term: 'مصروف', pages: PAGES, groups: GROUPS, hits: HITS });
    expect(items.map((_, index) => sectionStart(items, index))).toEqual(['pages', '', 'custodies', '', '', 'expenses', '', 'users', '']);
    // records only: no heading for pages that are not there
    const records = paletteItems({ term: 'حسام', pages: PAGES, groups: GROUPS, hits: HITS });
    expect(records.map((_, index) => sectionStart(records, index))).toEqual(['custodies', '', '', 'expenses', '', 'users', '']);
    expect(sectionStart([], 0)).toBe('');
  });

  test('the arrow keys stop at both ends and never leave an empty list', () => {
    expect(moveActive(0, 1, 5)).toBe(1);
    expect(moveActive(4, 1, 5)).toBe(4);
    expect(moveActive(4, -1, 5)).toBe(3);
    expect(moveActive(0, -1, 5)).toBe(0);
    expect(moveActive(3, 1, 0)).toBe(0);
    expect(moveActive(3, -1, 0)).toBe(0);
  });
});

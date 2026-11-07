/**
 * The action-kind system (Task6 T6-13): every action in the app has exactly
 * one look, chosen here — never by hand in a template. A kind maps to a
 * PrimeNG severity, a variant (filled / outlined / text) and a Tabler icon.
 *
 * | Kind          | Severity | Variant                          |
 * |---------------|----------|----------------------------------|
 * | destructive   | danger   | filled in confirm dialogs, outlined/text elsewhere |
 * | approve       | success  | filled for the decisive action, outlined elsewhere |
 * | caution       | warn     | outlined                         |
 * | informational | info     | outlined / text                  |
 * | primary       | —(brand) | filled — ONE per page header/dialog |
 * | secondary     | secondary| outlined — back, cancel, export   |
 * | tertiary      | —        | text / icon-only — rows          |
 */

export type ActionSeverity = 'success' | 'danger' | 'warn' | 'info' | 'secondary';
export type ActionVariant = 'filled' | 'outlined' | 'text';

export interface ActionStyle {
  readonly severity?: ActionSeverity;
  readonly variant: ActionVariant;
  /** Tabler icon name from icon-map — resolved by the caller */
  readonly icon?: string;
}

export const ACTION_KINDS = {
  // destructive / dangerous — red
  delete: { severity: 'danger', variant: 'filled', icon: 'trash' },
  reject: { severity: 'danger', variant: 'filled', icon: 'x' },
  cancel: { severity: 'danger', variant: 'filled', icon: 'x' },
  deactivate: { severity: 'danger', variant: 'outlined', icon: 'user' },
  remove: { severity: 'danger', variant: 'text', icon: 'x' },
  reverse: { severity: 'danger', variant: 'outlined', icon: 'arrows-exchange' },
  cancelTransfer: { severity: 'danger', variant: 'filled', icon: 'x' },
  closePoll: { severity: 'danger', variant: 'text', icon: 'x' }, // T7-05: chat poll close

  // approve / confirm / success — green
  approve: { severity: 'success', variant: 'filled', icon: 'check' },
  accept: { severity: 'success', variant: 'filled', icon: 'check' },
  receive: { severity: 'success', variant: 'filled', icon: 'check' },
  markReviewed: { severity: 'success', variant: 'outlined', icon: 'check' },
  markPaid: { severity: 'success', variant: 'outlined', icon: 'cash' },
  markPurchased: { severity: 'success', variant: 'outlined', icon: 'shopping-cart' },
  resolve: { severity: 'success', variant: 'outlined', icon: 'check' },
  assign: { severity: 'success', variant: 'outlined', icon: 'user' }, // T7-05: maintenance assign
  activate: { severity: 'success', variant: 'outlined', icon: 'user' }, // T7-05: case activate
  recordExpense: { severity: 'success', variant: 'outlined', icon: 'cash' }, // T7-05: salary → expense
  start: { severity: 'success', variant: 'outlined', icon: 'check' }, // T7-05: task start
  complete: { severity: 'success', variant: 'outlined', icon: 'check' }, // T7-05: task complete

  // caution — amber, outlined
  requestReturn: { severity: 'warn', variant: 'outlined', icon: 'arrow-back-up' },
  returnCustody: { severity: 'warn', variant: 'outlined', icon: 'arrow-back-up' }, // T7-05: return to treasury
  reopen: { severity: 'warn', variant: 'outlined', icon: 'arrow-back-up' }, // T7-05: task reopen
  freeze: { severity: 'warn', variant: 'outlined', icon: 'lock' },
  sendReview: { severity: 'warn', variant: 'outlined', icon: 'eye' },
  unreview: { severity: 'warn', variant: 'outlined', icon: 'eye' },

  // informational / navigation-like — blue-teal, outlined/text
  viewMovements: { severity: 'info', variant: 'text', icon: 'arrows-exchange' },
  openLinked: { severity: 'info', variant: 'text', icon: 'external-link' },
  transfer: { severity: 'info', variant: 'outlined', icon: 'arrows-exchange' },
  delegate: { severity: 'info', variant: 'outlined', icon: 'arrows-exchange' }, // T7-05: task delegate

  // primary — brand brown, filled, one per header/dialog
  save: { variant: 'filled', icon: 'check' },
  create: { variant: 'filled', icon: 'plus' },
  submit: { variant: 'filled', icon: 'check' },
  apply: { variant: 'filled', icon: 'filter' },
  send: { variant: 'filled', icon: 'check' }, // T7-05: task send

  // secondary — outlined brand
  back: { severity: 'secondary', variant: 'outlined', icon: 'chevron-right' },
  close: { severity: 'secondary', variant: 'text', icon: 'x' },
  export: { severity: 'secondary', variant: 'outlined', icon: 'download' },
  print: { severity: 'secondary', variant: 'outlined', icon: 'printer' },
  download: { severity: 'secondary', variant: 'outlined', icon: 'download' }, // T7-05: payslip/attachment
  quickAdd: { severity: 'secondary', variant: 'outlined', icon: 'plus' }, // T7-05: quick expense
  customize: { severity: 'secondary', variant: 'outlined', icon: 'dots' }, // T7-05: dashboard customize
  donate: { severity: 'secondary', variant: 'outlined', icon: 'cash' }, // T7-05: receive donation
  reset: { severity: 'secondary', variant: 'text', icon: 'filter' }, // T7-05: clear filters

  // tertiary / row actions — text / icon-only
  view: { variant: 'text', icon: 'eye' },
  edit: { variant: 'text', icon: 'pencil' },
  more: { variant: 'text', icon: 'dots' },
} as const satisfies Record<string, ActionStyle>;

export type ActionKind = keyof typeof ACTION_KINDS;

/** The one way to read a kind's look — pages never pick severity by hand. */
export function actionStyle(kind: ActionKind): ActionStyle {
  return ACTION_KINDS[kind];
}

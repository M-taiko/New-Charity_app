/**
 * Journal-ledger styling (RD-4.10/RD-5.3): one shared mapping from a
 * transaction type to its status-pill class and signed money class — used by
 * the custody movements ledgers and the custody detail journal.
 */
export function journalStampClass(type: string): string {
  if (type.includes('return')) {
    return 'stamp--success';
  }
  if (type.includes('transfer_in') || type === 'custody_out') {
    return 'stamp--info';
  }
  if (type.includes('transfer_out')) {
    return 'stamp--danger';
  }
  if (type.includes('expense')) {
    return 'stamp--pending';
  }
  return 'stamp--neutral';
}

export function journalMoneyClass(type: string): string {
  if (type.includes('return') || type.includes('transfer_in') || type === 'custody_out') {
    return 'money money--pos';
  }
  if (type.includes('transfer_out') || type.includes('expense')) {
    return 'money money--neg';
  }
  return '';
}

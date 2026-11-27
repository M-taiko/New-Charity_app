/** one plotted value of a chart series (labelled on the axis / in the legend) */
export interface SeriesPoint {
  readonly value: number;
}

/**
 * Whether a chart has nothing to plot: no series at all, or every value of
 * every series equal to zero. A missing or non-finite value counts as zero.
 *
 * Chart.js happily draws an empty plot for an all-zero series — a bare axis
 * running `0 … 1` with no bar, slice or point, and no word of explanation
 * (the dashboard's "expenses in the last 7 days" on a quiet week, R5-02).
 * Such a chart is shown as an empty state instead.
 *
 * Several series count as one chart: pass the compare series too, so a
 * compare chart whose main series is zero but whose second one is not is
 * still a chart.
 */
export function chartIsEmpty(...series: readonly (readonly SeriesPoint[] | null | undefined)[]): boolean {
  const lists = series.filter((list): list is readonly SeriesPoint[] => !!list && list.length > 0);
  if (lists.length === 0) {
    return true;
  }
  return lists.every((list) => list.every((point) => !Number.isFinite(point.value) || point.value === 0));
}

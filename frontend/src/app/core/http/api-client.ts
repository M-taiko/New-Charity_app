import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import type { ActionResponse, ItemResponse, ListParams, ListResponse, Lookups } from '@shared/models/api.models';

/** Typed GET/POST helpers implementing the list contract (plan 7.3). */
@Injectable({ providedIn: 'root' })
export class ApiClient {
  private http = inject(HttpClient);
  private lookupsSignal = signal<Lookups | null>(null);

  readonly lookups = this.lookupsSignal.asReadonly();

  list<T>(path: string, params: ListParams): Observable<ListResponse<T>> {
    return this.http.get<ListResponse<T>>(path, { params: this.cleanParams(params) });
  }

  get<T>(path: string): Observable<T> {
    return this.http.get<ItemResponse<T>>(path).pipe(map((res) => res.data));
  }

  post<T = unknown>(path: string, body: unknown): Observable<T> {
    return this.http.post<T>(path, body);
  }

  /**
   * A state-changing endpoint that answers with the standard
   * {data, message, level} action contract (accept/reject/delete/…).
   */
  action(path: string, body: Record<string, unknown> = {}): Observable<ActionResponse> {
    return this.http.post<ActionResponse>(path, body);
  }

  /** T6-71: an export/file download — the authenticated blob path for Excel/PDF/CSV. */
  getBlob(path: string): Observable<Blob> {
    return this.http.get(path, { responseType: 'blob' });
  }

  /** The app-wide delete convention: POST {resource}/{id}/delete (T3-01). */
  remove(path: string): Observable<ActionResponse> {
    return this.action(path);
  }

  /** Reference data is cached for a short lifetime (plan 18). */
  loadLookups(force = false): Observable<Lookups> {
    const cached = this.lookupsSignal();
    if (force || cached === null) {
      return this.get<Lookups>('/lookups').pipe(tap((data) => { this.lookupsSignal.set(data); }));
    }
    return new Observable<Lookups>((observer) => {
      observer.next(cached);
      observer.complete();
    });
  }

  private cleanParams(params: ListParams): Record<string, string> {
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === '' || value === 'all') {
        continue;
      }
      if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        result[key] = String(value);
      }
    }
    return result;
  }
}

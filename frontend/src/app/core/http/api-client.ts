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

  /** The app-wide delete convention: POST {resource}/{id}/delete (T3-01). */
  remove(path: string): Observable<ActionResponse> {
    return this.action(path);
  }

  /** Reference data is cached for a short lifetime (plan 18). */
  loadLookups(force = false): Observable<Lookups> {
    if (force || this.lookupsSignal() === null) {
      return this.get<Lookups>('/lookups').pipe(tap((data) => { this.lookupsSignal.set(data); }));
    }
    return new Observable<Lookups>((observer) => {
      observer.next(this.lookupsSignal() as Lookups);
      observer.complete();
    });
  }

  private cleanParams(params: ListParams): Record<string, string> {
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '' && value !== 'all') {
        result[key] = String(value);
      }
    }
    return result;
  }
}

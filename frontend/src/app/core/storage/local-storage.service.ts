import { Injectable } from '@angular/core';

/** Namespaced wrapper so SPA keys never collide with other apps on the host. */
@Injectable({ providedIn: 'root' })
export class LocalStorageService {
  private prefix = 'naguib-selim.';

  get<T>(key: string, fallback: T): T {
    try {
      const raw = localStorage.getItem(this.prefix + key);
      return raw === null ? fallback : (JSON.parse(raw) as T);
    } catch {
      return fallback;
    }
  }

  set<T>(key: string, value: T): void {
    try {
      localStorage.setItem(this.prefix + key, JSON.stringify(value));
    } catch {
      // storage full or blocked — settings are a convenience, never a requirement
    }
  }

  remove(key: string): void {
    try {
      localStorage.removeItem(this.prefix + key);
    } catch {
      // ignore
    }
  }
}

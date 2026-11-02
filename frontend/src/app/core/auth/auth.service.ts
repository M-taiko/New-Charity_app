import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable} from 'rxjs';
import { tap } from 'rxjs';
import { ItemResponse, Me } from '@shared/models/api.models';

/**
 * Session state for the SPA (plan 18). The /me payload (roles, permissions,
 * abilities) is the single source for menus, guards and the can directive.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  private readonly meSignal = signal<Me | null>(null);
  private loaded = false;

  readonly me = this.meSignal.asReadonly();
  readonly isLoggedIn = computed(() => this.meSignal() !== null);
  readonly abilities = computed(() => this.meSignal()?.abilities ?? null);

  loadMe(): Observable<ItemResponse<Me>> {
    return this.http.get<ItemResponse<Me>>('/me').pipe(tap((res) => { this.setMe(res.data); }));
  }

  login(email: string, password: string, remember: boolean): Observable<ItemResponse<Me>> {
    return this.http
      .post<ItemResponse<Me>>('/auth/login', { email, password, remember })
      .pipe(tap((res) => { this.setMe(res.data); }));
  }

  logout(): void {
    this.http.post('/auth/logout', {}).subscribe({
      complete: () => { this.clear(); },
      error: () => { this.clear(); },
    });
  }

  setMe(me: Me): void {
    this.meSignal.set(me);
    this.loaded = true;
  }

  clear(): void {
    this.meSignal.set(null);
    void this.router.navigate(['/login']);
  }

  hasRole(role: string): boolean {
    return this.meSignal()?.roles.includes(role) ?? false;
  }

  hasPermission(permission: string): boolean {
    return this.meSignal()?.permissions.includes(permission) ?? false;
  }

  can(ability: keyof Me['abilities'] | string): boolean {
    const abilities = this.abilities();
    if (abilities && ability in abilities) {
      return Boolean((abilities as unknown as Record<string, unknown>)[ability]);
    }
    return this.hasPermission(String(ability));
  }

  isLoaded(): boolean {
    return this.loaded;
  }
}

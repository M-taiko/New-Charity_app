// temporary eslint probe — deleted immediately
import { inject } from '@angular/core';
import { AuthService } from '@core/auth/auth.service';
import type { Me } from '@shared/models/api.models';

export class Probe {
  private auth = inject(AuthService);

  direct = this.auth.can('x');

  plain = this.auth.hasRole('x');

  meAbilities: Me['abilities'] | null = null;
}

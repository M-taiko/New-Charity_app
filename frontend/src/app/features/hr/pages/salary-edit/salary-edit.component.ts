// Edit a salary calculation (plan 20): attendance inputs + recalculation server-side.
import { computed, OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

@Component({
  selector: 'app-salary-edit',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="'hr.salaries.edit_title' | translate" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="s-att">{{ 'hr.salaries.col_attendance' | translate }} *</label>
          <p-inputnumber inputId="s-att" name="att" [(ngModel)]="attendanceDays" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-abs">{{ 'hr.salaries.col_absence' | translate }} *</label>
          <p-inputnumber inputId="s-abs" name="abs" [(ngModel)]="absenceDays" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-late">{{ 'hr.salaries.detail.late_days' | translate }}</label>
          <p-inputnumber inputId="s-late" name="late" [(ngModel)]="lateDays" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-leave">{{ 'hr.salaries.detail.leave_days' | translate }}</label>
          <p-inputnumber inputId="s-leave" name="leave" [(ngModel)]="leaveDays" styleClass="u-full" />
        </div>
      </div>
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/hr/salaries" />
        <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class SalaryEditPage implements OnInit {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  id = input.required<number>();
  attendanceDays = signal<number | null>(null);
  absenceDays = signal<number | null>(null);
  lateDays = signal<number | null>(null);
  leaveDays = signal<number | null>(null);
  sending = signal(false);
  error = signal('');

  ngOnInit(): void {
    void firstValueFrom(this.http.get<ItemResponse<Record<string, unknown>>>('/hr/salaries/' + this.id())).then((r) => {
      this.attendanceDays.set(Number(r.data.attendance_days ?? 0));
      this.absenceDays.set(Number(r.data.absence_days ?? 0));
      this.lateDays.set(Number(r.data.late_days ?? 0));
      this.leaveDays.set(Number(r.data.leave_days ?? 0));
    });
  }

  save(): void {
    this.sending.set(true);
    void firstValueFrom(
      this.http.post<ActionResponse>('/hr/salaries/' + this.id() + '/update', {
        attendance_days: this.attendanceDays(),
        absence_days: this.absenceDays(),
        late_days: this.lateDays(),
        leave_days: this.leaveDays(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        history.back();
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(err.error?.message ?? 'errors.generic');
      });
  }
}

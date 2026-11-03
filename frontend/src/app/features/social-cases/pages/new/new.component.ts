import type { OnInit } from '@angular/core';
import { ChangeDetectorRef, ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { FileUploadModule } from 'primeng/fileupload';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import type { ActionResponse, ItemResponse, Lookups } from '@shared/models/api.models';

/** One family-members repeater row (controller: family_members[i][…]). */
interface MemberRow {
  name: string;
  relationship: string | null;
  gender: string | null;
  status: string;
  phone: string;
}

/** A picked-but-not-yet-uploaded document (controller: documents JSON [{name, type, file}]). */
interface PendingDoc {
  name: string;
  type: string;
  file: string; // data URL — decoded server-side
}

/** An already-uploaded document shown read-only while editing. */
interface ExistingDoc {
  id: number;
  name: string;
  path: string;
  type: string | null;
}

/** The API show payload mapped onto the form field names. */
interface CasePayload {
  id: number;
  name: string | null;
  phone: string | null;
  national_id: string | null;
  nationality: string | null;
  address: string | null;
  governorate: string | null;
  district: string | null;
  housing_type: string | null;
  housing_status: string | null;
  monthly_income: string | null;
  monthly_expenses: string | null;
  income_source: string | null;
  family_members_count: number | null;
  affiliated_to: string | null;
  description: string | null;
  researcher: string | null;
  documents: ExistingDoc[] | null;
}

interface FamilyMembersResponse {
  family_members: { name: string; relationship: string | null; gender: string | null; status: string | null; phone: string | null }[] | null;
}

const RELATIONS = ['father', 'mother', 'son', 'daughter', 'brother', 'sister', 'wife', 'husband', 'grandfather', 'grandmother', 'other'] as const;
const MEMBER_STATUSES = ['healthy', 'sick', 'disabled', 'deceased', 'unknown'] as const;
const GENDERS = ['male', 'female'] as const;
const NATIONALITIES = ['egyptian', 'other'] as const;
const HOUSE_TYPES = ['owned', 'rented', 'borrowed', 'shelter'] as const;
const HOUSE_CONDITIONS = ['excellent', 'good', 'fair', 'poor'] as const;
const INTAKE_STATUSES = ['searched_by_phone', 'completed_externally', 'needs_research'] as const;
const ASSISTANCE_TYPES = ['financial', 'medical', 'food', 'education', 'housing', 'other'] as const;
const DOC_TYPES = [
  'national_id', 'passport', 'family_book', 'birth_certificate', 'marriage_certificate',
  'divorce_certificate', 'school_certificate', 'medical_report', 'disability_certificate',
  'personal_photo', 'family_photo', 'house_exterior', 'house_interior', 'work_certificate',
  'income_certificate', 'utility_bill', 'other',
] as const;

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
const MAX_DOC_SIZE = 5_000_000;

/**
 * New/edit social case (T3-50): the full unified Blade form — 25 fields with
 * the controller's validation, the family-members repeater
 * (GET social-cases/{id}/family-members on edit) and base64 documents that
 * the store/update endpoints accept as a JSON string. One component, two
 * routes (/cases/new, /cases/:id/edit).
 */
@Component({
  selector: 'app-case-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, InputNumberModule, TextareaModule, SelectModule, FileUploadModule, PageHeaderComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="isEdit() ? ('case.form.edit_title' | translate) : ('case.form.create_title' | translate)" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__title">{{ 'case.form.section_basics' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-name">{{ 'case.form.name' | translate }} *</label>
          <input pInputText id="c-name" name="name" [(ngModel)]="model.name" required maxlength="200" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-phone">{{ 'case.form.phone' | translate }} *</label>
          <input pInputText id="c-phone" name="phone" type="tel" [(ngModel)]="model.phone" required maxlength="20" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-affiliated">{{ 'case.form.affiliated' | translate }} *</label>
          <input pInputText id="c-affiliated" name="affiliated_to" [(ngModel)]="model.affiliated_to" required maxlength="255" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-intake">{{ 'case.form.status' | translate }} *</label>
          <p-select appendTo="body" inputId="c-intake" [options]="intakeOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.case_intake_status" name="case_intake_status" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label" for="c-researcher">{{ 'case.form.researcher' | translate }} *</label>
          <p-select appendTo="body" inputId="c-researcher" [options]="researchers()" optionLabel="name" optionValue="id" [(ngModel)]="model.researcher_id" name="researcher_id" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          @if (researcherIsMe()) { <span class="field__hint">{{ 'case.form.researcher_auto' | translate }}</span> }</div>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_personal' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-nationality">{{ 'case.form.nationality' | translate }} *</label>
          <p-select appendTo="body" inputId="c-nationality" [options]="nationalityOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.nationality" name="nationality" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label" for="c-nid">{{ 'case.form.national_id' | translate }}{{ model.nationality === 'egyptian' ? ' *' : '' }}</label>
          <input pInputText id="c-nid" name="national_id" [(ngModel)]="model.national_id" [maxlength]="14" [pattern]="nationalIdPattern()" [required]="model.nationality === 'egyptian'" style="width: 100%" />
          @if (model.nationality === 'egyptian') { <span class="field__hint">{{ 'case.form.national_id_hint' | translate }}</span> }</div>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_housing' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-gov">{{ 'case.form.governorate' | translate }}</label>
          <p-select appendTo="body" inputId="c-gov" [options]="governorateOptions()" optionLabel="name" optionValue="name" [(ngModel)]="model.city" name="governorate" styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label" for="c-district">{{ 'case.form.district' | translate }}</label>
          <input pInputText id="c-district" name="district" [(ngModel)]="model.district" maxlength="100" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-house-type">{{ 'case.form.housing_type' | translate }} *</label>
          <p-select appendTo="body" inputId="c-house-type" [options]="houseTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.house_type" name="house_type" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label" for="c-house-condition">{{ 'case.form.housing_status' | translate }} *</label>
          <p-select appendTo="body" inputId="c-house-condition" [options]="houseConditionOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.house_condition" name="house_condition" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
      </div>
      <div class="field">
        <label class="field__label" for="c-address">{{ 'case.form.address' | translate }} *</label>
        <textarea pTextarea id="c-address" name="address" rows="2" [(ngModel)]="model.address" required style="width: 100%"></textarea>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_financial' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-income">{{ 'case.form.monthly_income' | translate }} *</label>
          <p-inputnumber inputId="c-income" name="income" [(ngModel)]="model.monthly_income" mode="decimal" [minFractionDigits]="2" [min]="0" required styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="c-income-src">{{ 'case.form.income_source' | translate }} *</label>
          <input pInputText id="c-income-src" name="income_source" [(ngModel)]="model.income_source" required maxlength="255" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-expenses">{{ 'case.form.monthly_expenses' | translate }} *</label>
          <p-inputnumber inputId="c-expenses" name="expenses" [(ngModel)]="model.monthly_expenses" mode="decimal" [minFractionDigits]="2" [min]="0" required styleClass="u-full" /></div>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_family' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-composition">{{ 'case.form.family_composition' | translate }} *</label>
          <input pInputText id="c-composition" name="family_composition" [(ngModel)]="model.family_composition" required maxlength="255" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-children">{{ 'case.form.children_count' | translate }} *</label>
          <p-inputnumber inputId="c-children" name="children_count" [(ngModel)]="model.children_count" [min]="0" required styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="c-disabled">{{ 'case.form.disabled_count' | translate }} *</label>
          <p-inputnumber inputId="c-disabled" name="disabled_count" [(ngModel)]="model.disabled_count" [min]="0" required (ngModelChange)="syncDisabilityHint()" styleClass="u-full" /></div>
        @if (showDisabilityType()) {
          <div class="field"><label class="field__label" for="c-disability-type">{{ 'case.form.disability_type' | translate }}</label>
            <input pInputText id="c-disability-type" name="disability_type" [(ngModel)]="model.disability_type" maxlength="255" style="width: 100%" /></div>
        }
        <div class="field"><label class="field__label" for="c-family-count">{{ 'case.form.family_members_count' | translate }} *</label>
          <p-inputnumber inputId="c-family-count" name="family_members_count" [(ngModel)]="model.family_members_count" [min]="0" [max]="20" required (ngModelChange)="syncMemberRows($event)" styleClass="u-full" />
          <span class="field__hint">{{ 'case.form.family_count_hint' | translate }}</span></div>
      </div>

      @for (m of members(); track $index; let i = $index) {
        <div class="form-section__grid u-mt-2">
          <div class="field"><label class="field__label" for="member-name-{{ i }}">{{ 'case.form.member_name' | translate }} *</label>
            <input pInputText [id]="'member-name-' + i" [name]="'member_name_' + i" [(ngModel)]="m.name" required maxlength="100" style="width: 100%" /></div>
          <div class="field"><label class="field__label" for="member-rel-{{ i }}">{{ 'case.form.relation' | translate }} *</label>
            <p-select appendTo="body" [inputId]="'member-rel-' + i" [options]="relationOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.relationship" [name]="'member_relationship_' + i" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
          <div class="field"><label class="field__label" for="member-gender-{{ i }}">{{ 'case.form.member_gender' | translate }} *</label>
            <p-select appendTo="body" [inputId]="'member-gender-' + i" [options]="genderOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.gender" [name]="'member_gender_' + i" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
          <div class="field"><label class="field__label" for="member-status-{{ i }}">{{ 'case.form.member_status' | translate }}</label>
            <p-select appendTo="body" [inputId]="'member-status-' + i" [options]="memberStatusOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.status" [name]="'member_status_' + i" styleClass="u-full" /></div>
          <div class="field"><label class="field__label" for="member-phone-{{ i }}">{{ 'case.form.member_phone' | translate }}</label>
            <input pInputText [id]="'member-phone-' + i" [name]="'member_phone_' + i" type="tel" [(ngModel)]="m.phone" maxlength="20" style="width: 100%" /></div>
          <div class="field">
            <span class="field__label">&nbsp;</span>
            <p-button [label]="'common.remove' | translate" size="small" severity="danger" [text]="true" (onClick)="removeMember(i)" />
          </div>
        </div>
      }
      <p-button [label]="'case.form.add_member' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="addMember()" />

      <div class="form-section__title u-mt-4">{{ 'case.form.section_health' | translate }}</div>
      <div class="field">
        <label class="field__label" for="c-health">{{ 'case.form.health_conditions' | translate }}</label>
        <textarea pTextarea id="c-health" name="health_conditions" rows="2" [(ngModel)]="model.health_conditions" maxlength="500" style="width: 100%"></textarea>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_assistance' | translate }}</div>
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-assistance">{{ 'case.form.assistance_type' | translate }} *</label>
          <p-select appendTo="body" inputId="c-assistance" [options]="assistanceOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.assistance_type" name="assistance_type" required styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        @if (model.assistance_type === 'other') {
          <div class="field"><label class="field__label" for="c-other">{{ 'case.form.other_assistance' | translate }}</label>
            <input pInputText id="c-other" name="other_assistance" [(ngModel)]="model.other_assistance" maxlength="255" style="width: 100%" /></div>
        }
      </div>
      <div class="field">
        <label class="field__label" for="c-reason">{{ 'case.form.assistance_reason' | translate }}</label>
        <textarea pTextarea id="c-reason" name="assistance_reason" rows="2" [(ngModel)]="model.assistance_reason" maxlength="500" style="width: 100%"></textarea>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_notes' | translate }}</div>
      <div class="field">
        <label class="field__label" for="c-description">{{ 'case.form.description' | translate }}</label>
        <textarea pTextarea id="c-description" name="description" rows="3" [(ngModel)]="model.description" maxlength="1000" style="width: 100%"></textarea>
      </div>

      <div class="form-section__title u-mt-4">{{ 'case.form.section_documents' | translate }}</div>
      @if (existingDocs().length) {
        <div class="u-column u-mt-2">
          <span class="field__label">{{ 'case.form.existing_documents' | translate }}</span>
          @for (d of existingDocs(); track d.id) {
            <div class="attachments__file">
              <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-paperclip" /></svg>
              <span class="attachments__name u-bidi">{{ d.name }}</span>
              <a [href]="storageUrl(d.path)" target="_blank" rel="noopener">{{ 'common.open' | translate }}</a>
            </div>
          }
        </div>
      }
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-doc-type">{{ 'case.form.document_type' | translate }}</label>
          <p-select appendTo="body" inputId="c-doc-type" [options]="docTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="docType" name="doc_type" styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label">{{ 'case.form.document_file' | translate }}</label>
          <p-fileupload mode="basic" [customUpload]="true" (onSelect)="onDocSelect($event)" [chooseLabel]="'common.choose_file' | translate" [auto]="true" [maxFileSize]="5000000" />
          @if (docError()) { <p class="field__error">{{ docError() | translate }}</p> }</div>
      </div>
      @if (pendingDocs().length) {
        <div class="u-column u-mt-2">
          @for (d of pendingDocs(); track $index; let i = $index) {
            <div class="attachments__file">
              <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-paperclip" /></svg>
              <span class="attachments__name u-bidi">{{ d.name }} — {{ label('case.enums.doc_type.' + d.type) }}</span>
              <p-button [label]="'common.remove' | translate" size="small" severity="danger" [text]="true" (onClick)="removeDoc(i)" />
            </div>
          }
        </div>
      }

      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/cases" />
        <p-button type="submit" [label]="isEdit() ? ('case.form.submit_update' | translate) : ('case.form.submit_create' | translate)" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class CaseFormPage implements OnInit {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private auth = inject(AuthService);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  private cdr = inject(ChangeDetectorRef);

  id = input<number | undefined>(undefined);
  isEdit = computed(() => this.id() !== undefined);
  sending = signal(false);
  error = signal('');
  docError = signal('');
  docType = signal<string | null>(null);
  pendingDocs = signal<PendingDoc[]>([]);
  existingDocs = signal<ExistingDoc[]>([]);
  members = signal<MemberRow[]>([]);
  model = {
    name: '',
    phone: '',
    affiliated_to: '',
    case_intake_status: null as string | null,
    researcher_id: null as number | null,
    nationality: null as string | null,
    national_id: '',
    city: null as string | null,
    district: '',
    address: '',
    house_type: null as string | null,
    house_condition: null as string | null,
    monthly_income: null as number | null,
    income_source: '',
    monthly_expenses: null as number | null,
    family_composition: '',
    children_count: null as number | null,
    disabled_count: null as number | null,
    disability_type: '',
    family_members_count: null as number | null,
    health_conditions: '',
    assistance_type: null as string | null,
    other_assistance: '',
    assistance_reason: '',
    description: '',
  };

  readonly relationOptions = RELATIONS.map((v) => ({ value: v, label: '' }));
  readonly memberStatusOptions = MEMBER_STATUSES.map((v) => ({ value: v, label: '' }));
  readonly genderOptions = GENDERS.map((v) => ({ value: v, label: '' }));
  readonly nationalityOptions = NATIONALITIES.map((v) => ({ value: v, label: '' }));
  readonly houseTypeOptions = HOUSE_TYPES.map((v) => ({ value: v, label: '' }));
  readonly houseConditionOptions = HOUSE_CONDITIONS.map((v) => ({ value: v, label: '' }));
  readonly intakeOptions = INTAKE_STATUSES.map((v) => ({ value: v, label: '' }));
  readonly assistanceOptions = ASSISTANCE_TYPES.map((v) => ({ value: v, label: '' }));
  readonly docTypeOptions = DOC_TYPES.map((v) => ({ value: v, label: '' }));

  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
  });

  researchers = computed(() => this.lookups.value()?.researchers ?? []);
  /** Blade modern-create: the researcher defaults to the current account when it is one. */
  researcherIsMe = computed(() => {
    const me = this.auth.me();
    return me !== null && this.model.researcher_id === me.id;
  });
  showDisabilityType = signal(false);

  constructor() {
    // option labels resolve once — translations are loaded before the app starts
    const label = (prefix: string, value: string) => this.label(`${prefix}.${value}`);
    for (const opt of this.relationOptions) { opt.label = label('case.enums.relation', opt.value); }
    for (const opt of this.memberStatusOptions) { opt.label = label('case.enums.member_status', opt.value); }
    for (const opt of this.genderOptions) { opt.label = label('case.enums.gender', opt.value); }
    for (const opt of this.nationalityOptions) { opt.label = label('case.enums.nationality', opt.value); }
    for (const opt of this.houseTypeOptions) { opt.label = label('case.enums.house_type', opt.value); }
    for (const opt of this.houseConditionOptions) { opt.label = label('case.enums.house_condition', opt.value); }
    for (const opt of this.intakeOptions) { opt.label = label('case.enums.intake', opt.value); }
    for (const opt of this.assistanceOptions) { opt.label = label('case.enums.assistance', opt.value); }
    for (const opt of this.docTypeOptions) { opt.label = label('case.enums.doc_type', opt.value); }
  }

  ngOnInit(): void {
    const id = this.id();
    if (id === undefined) {
      this.seedResearcher();
      return;
    }
    void Promise.all([
      firstValueFrom(this.http.get<ItemResponse<CasePayload>>(`/social-cases/${id}`)),
      firstValueFrom(this.http.get<FamilyMembersResponse>(`/social-cases/${id}/family-members`)),
      firstValueFrom(this.api.loadLookups()),
    ])
      .then(([detail, family, lookups]) => {
        const d = detail.data;
        this.model = {
          ...this.model,
          name: d.name ?? '',
          phone: d.phone ?? '',
          national_id: d.national_id ?? '',
          nationality: d.nationality ?? null,
          address: d.address ?? '',
          city: d.governorate ?? null,
          district: d.district ?? '',
          house_type: d.housing_type ?? null,
          house_condition: d.housing_status ?? null,
          monthly_income: d.monthly_income !== null ? Number(d.monthly_income) : null,
          monthly_expenses: d.monthly_expenses !== null ? Number(d.monthly_expenses) : null,
          income_source: d.income_source ?? '',
          family_members_count: d.family_members_count ?? null,
          affiliated_to: d.affiliated_to ?? '',
          description: d.description ?? '',
          // the show payload carries the researcher name only — resolve it back to an id
          researcher_id: lookups.researchers.find((r) => r.name === d.researcher)?.id ?? null,
        };
        this.existingDocs.set(d.documents ?? []);
        const rows = (family.family_members ?? []).map((m) => ({
          name: m.name ?? '',
          relationship: m.relationship ?? null,
          gender: m.gender ?? null,
          status: m.status ?? 'healthy',
          phone: m.phone ?? '',
        }));
        this.members.set(rows);
        if (this.model.family_members_count === null && rows.length) {
          this.model.family_members_count = rows.length;
        }
        this.syncDisabilityHint();
        this.cdr.markForCheck();
      })
      .catch(() => { this.error.set('errors.generic'); });
  }

  /** localized enum label with the raw-code fallback (never blank) */
  label(key: string): string {
    const translated = this.translate.instant(key);
    return translated !== key ? translated : key.slice(key.lastIndexOf('.') + 1);
  }

  /** T34: a stored non-standard governorate stays selectable so editing never wipes it. */
  governorateOptions(): { name: string }[] {
    const list = (this.lookups.value()?.governorates ?? []).map((name) => ({ name }));
    const current = this.model.city;
    if (current && !list.some((g) => g.name === current)) {
      list.unshift({ name: current });
    }
    return list;
  }

  storageUrl(path: string): string {
    return `/storage/${path}`;
  }

  nationalIdPattern(): string {
    // 14 digits for Egyptians (controller phase-2 rule); free text otherwise
    return this.model.nationality === 'egyptian' ? '\\d{14}' : '.*';
  }

  syncDisabilityHint(): void {
    this.showDisabilityType.set((this.model.disabled_count ?? 0) > 0);
  }

  addMember(): void {
    this.members.update((rows) => [...rows, { name: '', relationship: null, gender: null, status: 'healthy', phone: '' }]);
    this.model.family_members_count = this.members().length;
  }

  removeMember(index: number): void {
    this.members.update((rows) => rows.filter((_, i) => i !== index));
    this.model.family_members_count = this.members().length;
  }

  /** Blade generateFamilyMembersInputs: the count drives the repeater rows. */
  syncMemberRows(count: number | null): void {
    const target = Math.min(Math.max(count ?? 0, 0), 20);
    this.members.update((rows) => {
      const next = [...rows];
      while (next.length < target) {
        next.push({ name: '', relationship: null, gender: null, status: 'healthy', phone: '' });
      }
      return next.slice(0, target);
    });
  }

  onDocSelect(event: { files?: File[] }): void {
    const file = event.files?.[0];
    if (!file) {
      return;
    }
    if (!ALLOWED_MIME.includes(file.type)) {
      this.docError.set('validation.file_type');
      return;
    }
    if (file.size > MAX_DOC_SIZE) {
      this.docError.set('case.form.doc_too_large');
      return;
    }
    const type = this.docType();
    if (!type) {
      this.docError.set('case.form.doc_type_required');
      return;
    }
    this.docError.set('');
    const reader = new FileReader();
    reader.onload = () => {
      const dataUrl = typeof reader.result === 'string' ? reader.result : '';
      this.pendingDocs.update((docs) => [...docs, { name: file.name, type, file: dataUrl }]);
    };
    reader.readAsDataURL(file);
  }

  removeDoc(index: number): void {
    this.pendingDocs.update((docs) => docs.filter((_, i) => i !== index));
  }

  save(): void {
    this.sending.set(true);
    this.error.set('');
    const id = this.id();
    const body: Record<string, unknown> = {
      ...this.model,
      family_members: this.members().filter((m) => m.name.trim() !== ''),
      // the controller accepts documents as a JSON string of {name, type, file} entries
      documents: this.pendingDocs().length ? JSON.stringify(this.pendingDocs()) : undefined,
    };
    const request$ = id
      ? this.http.post<ActionResponse>(`/social-cases/${id}/update`, body)
      : this.http.post<ActionResponse>('/social-cases', body);
    void firstValueFrom(request$)
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(id ? `/cases/${id}` : '/cases');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }

  private seedResearcher(): void {
    const me = this.auth.me();
    if (me && this.researchers().some((r) => r.id === me.id)) {
      this.model.researcher_id = me.id;
      return;
    }
    // lookups may still be loading — retry once they resolve
    void firstValueFrom(this.api.loadLookups()).then((lookups) => {
      if (this.model.researcher_id === null && me && lookups.researchers.some((r) => r.id === me.id)) {
        this.model.researcher_id = me.id;
        this.cdr.markForCheck();
      }
    });
  }
}

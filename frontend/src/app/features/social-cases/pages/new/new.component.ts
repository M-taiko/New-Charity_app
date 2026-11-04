import type { OnInit } from '@angular/core';
import { ChangeDetectorRef, ChangeDetectionStrategy, Component, computed, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
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
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import type { LoadableResource } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
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
 * routes (/cases/new, /cases/:id/edit). T4-04.1: already-uploaded documents
 * gain a per-document remove via POST /case-documents/{id}/delete.
 */
@Component({
  selector: 'app-case-form',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, InputNumberModule,
    TextareaModule, SelectModule, FileUploadModule, PageHeaderComponent,
    FieldComponent, FormSectionComponent, ConfirmDeleteComponent, RowActionsComponent, AsyncPageComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="isEdit() ? ('case.form.edit_title' | translate) : ('case.form.create_title' | translate)">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/cases" />
      </div>
    </app-page-header>
    <!-- T5-07: a failed edit-load shows the error state with retry instead of a
         blank form that could be saved over the real case -->
    <app-async-page [resource]="pageState" variant="form" [backLink]="['/cases']">
      <form (ngSubmit)="save()" #f="ngForm">
        <app-form-section [title]="'case.form.section_basics'">
          <app-field label="case.form.name" controlId="c-name" [required]="true" [error]="serverFieldError(error(), 'name')">
            <input pInputText id="c-name" name="name" class="u-full" [(ngModel)]="model.name" required maxlength="200" />
          </app-field>
          <app-field label="case.form.phone" controlId="c-phone" [required]="true" [error]="serverFieldError(error(), 'phone')">
            <input pInputText id="c-phone" name="phone" type="tel" class="u-full" [(ngModel)]="model.phone" required maxlength="20" />
          </app-field>
          <app-field label="case.form.affiliated" controlId="c-affiliated" [required]="true" [error]="serverFieldError(error(), 'affiliated_to')">
            <input pInputText id="c-affiliated" name="affiliated_to" class="u-full" [(ngModel)]="model.affiliated_to" required maxlength="255" />
          </app-field>
          <app-field label="case.form.status" controlId="c-intake" [required]="true" [error]="serverFieldError(error(), 'case_intake_status')">
            <p-select appendTo="body" inputId="c-intake" [options]="intakeOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.case_intake_status" name="case_intake_status" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.researcher" controlId="c-researcher" [required]="true" [hint]="researcherIsMe() ? 'case.form.researcher_auto' : ''" [error]="serverFieldError(error(), 'researcher_id')">
            <p-select appendTo="body" inputId="c-researcher" [options]="researchers()" optionLabel="name" optionValue="id" [(ngModel)]="model.researcher_id" name="researcher_id" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_personal'">
          <app-field label="case.form.nationality" controlId="c-nationality" [required]="true" [error]="serverFieldError(error(), 'nationality')">
            <p-select appendTo="body" inputId="c-nationality" [options]="nationalityOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.nationality" name="nationality" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.national_id" controlId="c-nid" [required]="model.nationality === 'egyptian'" [hint]="model.nationality === 'egyptian' ? 'case.form.national_id_hint' : ''" [error]="serverFieldError(error(), 'national_id')">
            <input pInputText id="c-nid" name="national_id" class="u-full" [(ngModel)]="model.national_id" [maxlength]="14" [pattern]="nationalIdPattern()" [required]="model.nationality === 'egyptian'" />
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_housing'">
          <app-field label="case.form.governorate" controlId="c-gov" [error]="serverFieldError(error(), 'city')">
            <p-select appendTo="body" inputId="c-gov" [options]="governorateOptions()" optionLabel="name" optionValue="name" [(ngModel)]="model.city" name="governorate" styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.district" controlId="c-district" [error]="serverFieldError(error(), 'district')">
            <input pInputText id="c-district" name="district" class="u-full" [(ngModel)]="model.district" maxlength="100" />
          </app-field>
          <app-field label="case.form.housing_type" controlId="c-house-type" [required]="true" [error]="serverFieldError(error(), 'house_type')">
            <p-select appendTo="body" inputId="c-house-type" [options]="houseTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.house_type" name="house_type" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.housing_status" controlId="c-house-condition" [required]="true" [error]="serverFieldError(error(), 'house_condition')">
            <p-select appendTo="body" inputId="c-house-condition" [options]="houseConditionOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.house_condition" name="house_condition" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.address" controlId="c-address" [required]="true" [error]="serverFieldError(error(), 'address')">
            <textarea pTextarea id="c-address" name="address" rows="2" class="u-full" [(ngModel)]="model.address" required></textarea>
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_financial'">
          <app-field label="case.form.monthly_income" controlId="c-income" [required]="true" [error]="serverFieldError(error(), 'monthly_income')">
            <p-inputnumber inputId="c-income" name="income" [(ngModel)]="model.monthly_income" mode="decimal" [minFractionDigits]="2" [min]="0" required styleClass="u-full" />
          </app-field>
          <app-field label="case.form.income_source" controlId="c-income-src" [required]="true" [error]="serverFieldError(error(), 'income_source')">
            <input pInputText id="c-income-src" name="income_source" class="u-full" [(ngModel)]="model.income_source" required maxlength="255" />
          </app-field>
          <app-field label="case.form.monthly_expenses" controlId="c-expenses" [required]="true" [error]="serverFieldError(error(), 'monthly_expenses')">
            <p-inputnumber inputId="c-expenses" name="expenses" [(ngModel)]="model.monthly_expenses" mode="decimal" [minFractionDigits]="2" [min]="0" required styleClass="u-full" />
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_family'">
          <app-field label="case.form.family_composition" controlId="c-composition" [required]="true" [error]="serverFieldError(error(), 'family_composition')">
            <input pInputText id="c-composition" name="family_composition" class="u-full" [(ngModel)]="model.family_composition" required maxlength="255" />
          </app-field>
          <app-field label="case.form.children_count" controlId="c-children" [required]="true" [error]="serverFieldError(error(), 'children_count')">
            <p-inputnumber inputId="c-children" name="children_count" [(ngModel)]="model.children_count" [min]="0" required styleClass="u-full" />
          </app-field>
          <app-field label="case.form.disabled_count" controlId="c-disabled" [required]="true" [error]="serverFieldError(error(), 'disabled_count')">
            <p-inputnumber inputId="c-disabled" name="disabled_count" [(ngModel)]="model.disabled_count" [min]="0" required (ngModelChange)="syncDisabilityHint()" styleClass="u-full" />
          </app-field>
          @if (showDisabilityType()) {
            <app-field label="case.form.disability_type" controlId="c-disability-type" [error]="serverFieldError(error(), 'disability_type')">
              <input pInputText id="c-disability-type" name="disability_type" class="u-full" [(ngModel)]="model.disability_type" maxlength="255" />
            </app-field>
          }
          <app-field label="case.form.family_members_count" controlId="c-family-count" [required]="true" hint="case.form.family_count_hint" [error]="serverFieldError(error(), 'family_members_count')">
            <p-inputnumber inputId="c-family-count" name="family_members_count" [(ngModel)]="model.family_members_count" [min]="0" [max]="20" required (ngModelChange)="syncMemberRows($event)" styleClass="u-full" />
          </app-field>
        </app-form-section>

        @for (m of members(); track $index; let i = $index) {
          <div class="form-section__grid u-mt-2">
            <app-field label="case.form.member_name" [controlId]="'member-name-' + i" [required]="true">
              <input pInputText [id]="'member-name-' + i" [name]="'member_name_' + i" class="u-full" [(ngModel)]="m.name" required maxlength="100" />
            </app-field>
            <app-field label="case.form.relation" [controlId]="'member-rel-' + i" [required]="true">
              <p-select appendTo="body" [inputId]="'member-rel-' + i" [options]="relationOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.relationship" [name]="'member_relationship_' + i" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
            </app-field>
            <app-field label="case.form.member_gender" [controlId]="'member-gender-' + i" [required]="true">
              <p-select appendTo="body" [inputId]="'member-gender-' + i" [options]="genderOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.gender" [name]="'member_gender_' + i" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
            </app-field>
            <app-field label="case.form.member_status" [controlId]="'member-status-' + i">
              <p-select appendTo="body" [inputId]="'member-status-' + i" [options]="memberStatusOptions" optionLabel="label" optionValue="value" [(ngModel)]="m.status" [name]="'member_status_' + i" styleClass="u-full" />
            </app-field>
            <app-field label="case.form.member_phone" [controlId]="'member-phone-' + i">
              <input pInputText [id]="'member-phone-' + i" [name]="'member_phone_' + i" type="tel" class="u-full" [(ngModel)]="m.phone" maxlength="20" />
            </app-field>
            <div class="field">
              <span class="field__label">&nbsp;</span>
              <p-button [label]="'common.remove' | translate" size="small" severity="danger" [text]="true" (onClick)="removeMember(i)" />
            </div>
          </div>
        }
        <p-button [label]="'case.form.add_member' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="addMember()" />

        <app-form-section [title]="'case.form.section_health'">
          <app-field label="case.form.health_conditions" controlId="c-health" [error]="serverFieldError(error(), 'health_conditions')">
            <textarea pTextarea id="c-health" name="health_conditions" rows="2" class="u-full" [(ngModel)]="model.health_conditions" maxlength="500"></textarea>
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_assistance'">
          <app-field label="case.form.assistance_type" controlId="c-assistance" [required]="true" [error]="serverFieldError(error(), 'assistance_type')">
            <p-select appendTo="body" inputId="c-assistance" [options]="assistanceOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.assistance_type" name="assistance_type" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          @if (model.assistance_type === 'other') {
            <app-field label="case.form.other_assistance" controlId="c-other" [error]="serverFieldError(error(), 'other_assistance')">
              <input pInputText id="c-other" name="other_assistance" class="u-full" [(ngModel)]="model.other_assistance" maxlength="255" />
            </app-field>
          }
          <app-field label="case.form.assistance_reason" controlId="c-reason" [error]="serverFieldError(error(), 'assistance_reason')">
            <textarea pTextarea id="c-reason" name="assistance_reason" rows="2" class="u-full" [(ngModel)]="model.assistance_reason" maxlength="500"></textarea>
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_notes'">
          <app-field label="case.form.description" controlId="c-description" [error]="serverFieldError(error(), 'description')">
            <textarea pTextarea id="c-description" name="description" rows="3" class="u-full" [(ngModel)]="model.description" maxlength="1000"></textarea>
          </app-field>
        </app-form-section>

        <app-form-section [title]="'case.form.section_documents'">
          @if (existingDocs().length) {
            <div class="u-column">
              <span class="field__label">{{ 'case.form.existing_documents' | translate }}</span>
              @for (d of existingDocs(); track d.id) {
                <div class="attachments__file">
                  <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-paperclip" /></svg>
                  <span class="attachments__name u-bidi">{{ d.name }}</span>
                  <a [href]="storageUrl(d.path)" target="_blank" rel="noopener">{{ 'common.open' | translate }}</a>
                  @if (canManage()) {
                    <app-row-actions [actions]="docRemoveActions(d)" />
                  }
                </div>
              }
            </div>
          }
          <app-field label="case.form.document_type" controlId="c-doc-type">
            <p-select appendTo="body" inputId="c-doc-type" [options]="docTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="docType" name="doc_type" styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="case.form.document_file" [error]="docError() ? (docError() | translate) : null">
            <p-fileupload mode="basic" [customUpload]="true" (onSelect)="onDocSelect($event)" [chooseLabel]="'common.choose_file' | translate" [auto]="true" [maxFileSize]="5000000" />
          </app-field>
          @if (pendingDocs().length) {
            <div class="u-column">
              @for (d of pendingDocs(); track $index; let i = $index) {
                <div class="attachments__file">
                  <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-paperclip" /></svg>
                  <span class="attachments__name u-bidi">{{ d.name }} — {{ label('case.enums.doc_type.' + d.type) }}</span>
                  <p-button [label]="'common.remove' | translate" size="small" severity="danger" [text]="true" (onClick)="removeDoc(i)" />
                </div>
              }
            </div>
          }
        </app-form-section>

        @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
        <div class="sticky-actions">
          <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/cases" />
          <p-button type="submit" [label]="isEdit() ? ('case.form.submit_update' | translate) : ('case.form.submit_create' | translate)" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>

    <app-confirm-delete #docDelete [path]="docDeletePath()" [entity]="docDeleteEntity()" (deleted)="onDocDeleted()" />
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
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;
  docError = signal('');
  docType = signal<string | null>(null);
  pendingDocs = signal<PendingDoc[]>([]);
  existingDocs = signal<ExistingDoc[]>([]);
  members = signal<MemberRow[]>([]);

  /** T4-04.1: the pending per-document delete (POST /case-documents/{id}/delete) */
  docDeletePath = signal('');
  docDeleteEntity = signal('');
  private docDelete = viewChild<ConfirmDeleteComponent>('docDelete');

  /** removeDocument authorizes manage_social_cases server-side (T4-04.1) */
  readonly canManage = computed(() => this.auth.can('may_manage_social_cases'));

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
    if (!this.isEdit()) {
      this.pageLoading.set(false);
      this.seedResearcher();
      return;
    }
    this.load();
  }

  private pageLoading = signal(true);
  private loadError = signal<unknown>(undefined);
  private loaded = signal(false);

  /** adapter so app-async-page drives this page's load/error/skeleton states (T5-07) */
  readonly pageState: LoadableResource = {
    value: () => (this.isEdit() ? this.loaded() : true),
    error: () => this.loadError(),
    isLoading: () => this.pageLoading(),
    reload: () => { this.load(); },
  };

  /** edit mode: the case, its family members and the lookups, all before the form renders */
  load(): void {
    const id = this.id();
    if (id === undefined) {
      return;
    }
    this.pageLoading.set(true);
    this.loadError.set(undefined);
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
        this.pageLoading.set(false);
        this.loaded.set(true);
        this.cdr.markForCheck();
      })
      .catch((err: unknown) => {
        // a load failure is NOT a save error — the page state shows the
        // error + retry UI and the form stays hidden (T5-07)
        this.pageLoading.set(false);
        this.loadError.set(err);
      });
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

  /** the per-document delete action (T5-04) — shared row-actions instead of a raw button */
  docRemoveActions(doc: ExistingDoc): RowActionItem[] {
    return [{ icon: 'trash', label: 'common.delete', severity: 'danger', handler: () => { this.askDocRemove(doc); } }];
  }

  /** T4-04.1: remove one uploaded document via the shared confirm-delete flow */
  askDocRemove(doc: ExistingDoc): void {
    this.docDeletePath.set(`/case-documents/${doc.id}/delete`);
    this.docDeleteEntity.set(doc.name);
    this.docDelete()?.open();
  }

  /** T4-04.1: re-read the case after a document delete so the list reflects the server */
  onDocDeleted(): void {
    const id = this.id();
    if (id === undefined) {
      return;
    }
    void firstValueFrom(this.http.get<ItemResponse<CasePayload>>(`/social-cases/${id}`))
      .then((res) => {
        this.existingDocs.set(res.data.documents ?? []);
      })
      .catch((err: unknown) => { this.error.set(err instanceof HttpErrorResponse ? err : null); });
  }

  /** the general alert text — the server message or the generic fallback key */
  alert(): string | null {
    const err = this.error();
    if (err === null) {
      return null;
    }
    return err.error?.message ?? 'errors.generic';
  }

  save(): void {
    this.sending.set(true);
    this.error.set(null);
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
        this.error.set(err instanceof HttpErrorResponse ? err : null);
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

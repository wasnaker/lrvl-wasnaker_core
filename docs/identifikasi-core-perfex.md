# Identifikasi Core PerfexCRM — Kandidat Modul di Laravel

File ini berisi hasil audit modul yang tertanam di **core PerfexCRM**
(`application/controllers/`, `application/models/`, dan tabel `tbl*`).

> **Catatan:** Modul di folder `modules/` sudah benar-benar module (third-party) → **diabaikan**.
> Yang diidentifikasi di sini adalah modul yang **tertanam di core** dan perlu diekstraksi
> menjadi module di backend Laravel baru.

---

## Kelompok 1 — CRM / Sales

| Modul | Controller | Model | Tabel utama |
|-------|-----------|-------|-------------|
| Customers (Clients) | `Clients.php` | `Clients_model` | `tblclients`, `tblcontacts`, `tblcustomer_admins`, `tblcustomer_groups`, `tblvault`, `tblshared_customer_files` |
| Contacts | (di Clients) | — | `tblcontacts`, `tblcontact_permissions` |
| Leads | `Leads.php` | `Leads_model` | `tblleads`, `tblleads_status`, `tblleads_sources`, `tblleads_email_integration`, `tbllead_activity_log`, `tblweb_to_lead` |
| Proposals | `Proposals.php` | `Proposals_model` | `tblproposals`, `tblproposal_comments` |
| Estimates | `Estimates.php` | `Estimates_model` | `tblestimates`, `tblestimate_requests`, `tblestimate_request_forms`, `tblestimate_request_status` |
| Invoices | `Invoices.php` | `Invoices_model` | `tblinvoices`, `tblinvoicepaymentrecords`, `tblitem_tax` |
| Credit Notes | `Credit_notes.php` | `Credit_notes_model` | `tblcreditnotes`, `tblcreditnote_refunds`, `tblcredits` |
| Payments | `Payments.php` | `Payments_model` | (via `tblinvoicepaymentrecords`) |
| Subscriptions | `Subscription.php` | `Subscriptions_model` | `tblsubscriptions` |
| Contracts | `Contracts.php` | `Contracts_model` | `tblcontracts`, `tblcontracts_types`, `tblcontract_comments`, `tblcontract_renewals` |
| Expenses | `Expenses.php` | `Expenses_model` | `tblexpenses`, `tblexpenses_categories` |
| Invoice Items / Catalog | `Invoice_items.php` | `Invoice_items_model` | `tblitems`, `tblitems_groups`, `tblitemable` |
| Taxes | `Taxes.php` | `Taxes_model` | `tbltaxes` |
| Currencies | `Currencies.php` | `Currencies_model` | `tblcurrencies` |

---

## Kelompok 2 — Project Management

| Modul | Controller | Model | Tabel |
|-------|-----------|-------|-------|
| Projects | `Projects.php` | `Projects_model` | `tblprojects`, `tblmilestones`, `tblproject_members`, `tblproject_files`, `tblproject_notes`, `tblproject_activity`, `tblproject_settings`, `tblprojectdiscussions`, `tblprojectdiscussioncomments`, `tblpinned_projects` |
| Tasks | `Tasks.php` | `Tasks_model` | `tbltasks`, `tbltask_assigned`, `tbltask_checklist_items`, `tbltask_comments`, `tbltask_followers`, `tbltaskstimers`, `tbltasks_checklist_templates` |

---

## Kelompok 3 — Ticket & Support

| Modul | Controller | Model | Tabel |
|-------|-----------|-------|-------|
| Tickets | `Tickets.php` | `Tickets_model` | `tbltickets`, `tblticket_replies`, `tblticket_attachments`, `tbltickets_priorities`, `tbltickets_status`, `tbltickets_predefined_replies`, `tbltickets_pipe_log` |

---

## Kelompok 4 — HR / Staff & Organisasi

| Modul | Controller | Model | Tabel |
|-------|-----------|-------|-------|
| Staff | `Staff.php` | `Staff_model` | `tblstaff`, `tblstaff_departments`, `tblstaff_permissions` |
| Roles & Permissions | `Roles.php` | `Roles_model` | `tblroles` |
| Departments | `Departments.php` | `Departments_model` | `tbldepartments` |

---

## Kelompok 5 — Content / Komunikasi

| Modul | Controller | Model | Tabel |
|-------|-----------|-------|-------|
| Knowledge Base | `Knowledge_base.php` | `Knowledge_base_model` | `tblknowledge_base`, `tblknowledge_base_groups`, `tblknowedge_base_article_feedback` |
| Announcements | `Announcements.php` | `Announcements_model` | `tblannouncements`, `tbldismissed_announcements` |
| Newsfeed | `Newsfeed.php` | `Newsfeed_model` | `tblnewsfeed_posts`, `tblnewsfeed_post_comments`, `tblnewsfeed_post_likes`, `tblnewsfeed_comment_likes` |
| Email Templates | `Emails.php` | `Emails_model` | `tblemailtemplates`, `tblmail_queue`, `tbltracked_mails`, `tblscheduled_emails` |
| Surveys | — (modul internal) | — | `tblsurveys`, `tblsurveyresultsets`, `tblsurveysemailsendcron`, `tblsurveysendlog`, `tblform_questions`, `tblform_question_box`, `tblform_question_box_description`, `tblform_results` |
| Spam Filters | `Spam_filters.php` | `Spam_filters_model` | `tblspam_filters` |
| Email Lists | `Emails.php` | — | `tblemaillists`, `tblmaillistscustomfields`, `tblmaillistscustomfieldvalues`, `tbllistemails` |

---

## Kelompok 6 — Sistem / Utility (biasanya tetap di core sistem)

| Modul | Controller | Model | Tabel |
|-------|-----------|-------|-------|
| Custom Fields | `Custom_fields.php` | `Custom_fields_model` | `tblcustomfields`, `tblcustomfieldsvalues` |
| Reports | `Reports.php` | `Reports_model` | (agregasi berbagai tabel) |
| Todo | `Todo.php` | `Todo_model` | `tbltodos` |
| Settings | `Settings.php` | `Settings_model` | `tbloptions` |
| Utilities | `Utilities.php` | `Utilities_model` | `tblactivity_log` |
| Payment Modes | `Paymentmodes.php` | `Payment_modes_model` | `tblpayment_modes` |
| GDPR | `Gdpr.php` | `Gdpr_model` | `tblgdpr_requests`, `tblconsent_purposes`, `tblconsents` |
| Auth | `Authentication.php` | `Authentication_model` | `tblstaff`, `tblcontacts`, `tbluser_auto_login`, `tblsessions` |
| Check Emails / Cron | `Check_emails.php`, `Cron.php` | `Cron_model` | `tblmail_queue`, `tbltickets_pipe_log` |

---

## Catatan Tambahan

### Tabel pendukung core (shared) — biasanya tetap di core sistem
- `tblsessions` / `tbluser_auto_login` / `tbluser_meta`
- `tblfiles` (file manager)
- `tblnotes` (notes)
- `tblnotifications`
- `tblreminders`
- `tblevents` (kalender)
- `tbltags`, `tbltaggables`
- `tblmigrations`, `tblmodules`
- `tblcountries`
- `tbloptions`
- `tblactivity_log`

### Tabel K3L / Equipment (bisnis spesifik)
Tidak masuk core PerfexCRM standar — bagian modul inspeksi K3L:
`tblalarm_kebakaran`, `tblbejana_tekan`, `tblboiler`, `tblcrane*`, `tblmesin_*`,
`tbltangki`, `tbltanur`, `tblpetir`, `tblhydrant`, dst.
→ Sudah module tersendiri, sesuaikan pemetaannya di Laravel.

---

## Kesimpulan

- Modul yang **dihilangkan dari core** → menjadi **module di Laravel**: **Kelompok 1–5**
  (CRM/Sales, Project Mgmt, Support, HR, Content).
- **Kelompok 6** umumnya dipertahankan sebagai core sistem Laravel
  (auth, settings, custom fields, utility).
- Modul `modules/` third-party dan tabel K3L/equipment → sudah module, tinggal dipetakan.

---

*Dokumen dibuat otomatis berdasarkan audit struktur PerfexCRM pada LXC 107 (192.168.18.17), 27 Agustus 2026.*

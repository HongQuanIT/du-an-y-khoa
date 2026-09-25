# Phân tích tính năng & Testcase — Workflow quản lý câu hỏi (Module 35)

> **Nguồn sự thật:** `srs/modules/35-question-management.md`  
> **Không nhầm với** Module 07 (`07-question-review.md`) — đó là **học viên xem lại sau session**, không phải luồng soạn/duyệt nội dung.

Tài liệu này mô tả máy trạng thái duyệt 3 lớp đang chạy trên code, map actor → hành động, và catalogue testcase hiện có + khoảng trống cần bổ sung.

> **Cập nhật 2026-09-24:** luồng cờ đổi — chờ đủ 2 cờ; 2 đỏ auto-return + sticky skip GV; lệch → `flag_conflict` / tab Cảnh báo; Admin `question.reject` opt-in (seed mặc định không cấp). Chi tiết: canvas `question-flag-conflict-workflow` + SRS §4–5.3.

---

## 1. Tóm tắt tính năng

CRUD + **workflow duyệt 3 lớp** trước khi câu hỏi lên Qbank:

| Lớp | Actor | Portal | Việc làm | Version tăng? |
|-----|--------|--------|----------|---------------|
| **0** | Content Editor (`content_editor`) | `/admin` | Soạn / sửa working copy, gửi duyệt, rút nháp, clone, import | Không |
| **1a** | Instructor được gán | `/teach` | Duyệt chuyên môn: approve / reject | Không |
| **1b** | Reviewer (`question.flag`) | `/admin/questions/flags` | Gắn cờ xanh / đỏ (đỏ bắt buộc ghi chú) | Không |
| **2** | Admin / Super Admin (`question.publish`) | `/admin` | Xuất bản / trả về / private / retire + đánh giá QA | **Có** (chỉ khi publish) |

**Nguyên tắc cứng**

- Chỉ **publish** mới tạo `question_versions` và +1 `version` / `published_version`.
- 1 phiếu **reject GV** = fail ngay → `rejected`.
- ≥1 **cờ đỏ** → fail-fast vào `pending_publish`; Admin **không được publish**, phải trả Editor.
- Publisher **không** được trùng GV được gán hoặc 2 reviewer đã gắn cờ.
- Câu đã từng publish: học viên vẫn đọc snapshot `published_version` trong lúc working copy đi lại pipeline.

---

## 2. Máy trạng thái

```
                  submit          approve(GV)           2 xanh | ≥1 đỏ           publish
   draft ────────────────► in_review ──────► in_flag_review ──────► pending_publish ──────► published
    ▲                         │                                         │
    │           reject(GV)    │                                         │ admin trả về
    │◄── rejected ◄───────────┘                                         ▼
    │◄──────────────────────────────────────── rejected ◄───────────────┘
    └─ Editor: rejected → draft (sửa, không +version) ─┘

   in_review ──withdraw──► draft   (chỉ khi GV chưa quyết)
   in_flag_review / pending_publish: Editor KHÔNG sửa, KHÔNG rút nháp
```

| Status | Ý nghĩa | Ai đưa tới | Hiện Qbank? |
|--------|---------|------------|-------------|
| `draft` | Nháp / đang soạn | Tạo mới; withdraw; sau reject → draft | Không\* |
| `in_review` | Chờ GV được gán | Editor submit | Không\* |
| `in_flag_review` | Chờ 2 reviewer gắn cờ | GV approve | Không\* |
| `pending_publish` | Đủ 2 cờ xanh **hoặc** ≥1 đỏ | Flag #2 hoặc đỏ fail-fast | Không\* |
| `published` | Đã XB phiên bản | Admin publish | Có |
| `rejected` | Bị trả (GV hoặc Admin) | GV reject / Admin return | Không\* |
| `private` | Ẩn khỏi Qbank | Admin | Không |
| `retired` | Ngừng dùng (giữ attempt) | Admin | Không |

\* **Tái bản:** nếu `published_version >= 1`, Qbank vẫn phục vụ snapshot cũ đến khi Admin publish lần mới.

---

## 3. Phân tích luồng theo actor

### 3.1 Content Editor — soạn & gửi duyệt

**Portal:** `/admin/questions` · create / edit

| Bước | Hành động | Kết quả | Điều kiện / ràng buộc |
|------|-----------|---------|------------------------|
| Tạo mới | `POST /admin/questions` | `draft`, `version = 0` | Stem, 4 đáp án (1 đúng), ≥1 bài học (bắt buộc trước submit) |
| Sửa nháp | Save working copy | Giữ `draft`, **không** +version | Autosave / optimistic lock |
| Gán GV | Chọn `assigned_instructor_id` | Lưu trên form | GV phải khớp môn của bài học |
| Gửi duyệt | Transition → `in_review` | +1 `instructor_review_cycle`; ghi `question_workflow_events` (`submit`) | Có GV hợp lệ; đủ nội dung tối thiểu |
| Rút nháp | Withdraw khi `in_review` | → `draft`, xóa slot duyệt vòng hiện tại | Chỉ khi GV chưa approve/reject |
| Sau reject | Transition `rejected` → `draft` | Sửa rồi submit lại (vòng mới) | Không sửa trực tiếp khi đang `rejected` |
| Sửa câu đã XB | Save content | Working copy → `draft`; Qbank giữ bản cũ | Phải đi lại lớp 1 → 2 để XB mới |
| Clone | Clone từ câu / version | Câu **mới** `draft`, lifecycle riêng | `question.create` |
| Import | Excel/CSV | Hàng loạt `draft` | Không publish qua import |
| Khôi phục version | Restore snapshot | Áp vào working copy + `draft` | Không +version; Qbank chưa đổi |

**Không được:** publish, duyệt thay GV, gắn cờ, adjudicate QA.

**Khóa nội dung:** `in_review` / `in_flag_review` / `pending_publish` / `rejected` → không edit nội dung (trừ withdraw từ `in_review`, hoặc về `draft` sau reject).

---

### 3.2 Giảng viên — duyệt chuyên môn (lớp 1a)

**Portal:** `/teach/questions/reviews` · tabs pending / approved / rejected

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| Xem hàng đợi | List câu `in_review` gán cho mình | Không thấy phiếu của người khác trước khi mình quyết (tránh neo) |
| Approve | `POST …/approve` | → `in_flag_review`; ghi `question_instructor_reviews` |
| Reject | `POST …/reject` + `reason` | → `rejected` ngay; `rejected_by_role = instructor`; + `pipeline_reject_count` |

**Ràng buộc**

- Chỉ **GV được gán**; không phải creator của câu.
- 1 quyết định / vòng (`review_cycle`).
- Không publish, không +version, không sửa stem/options (read-only + note).

---

### 3.3 Reviewer — gắn cờ (lớp 1b)

**Portal:** `/admin/questions/flags`

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| Xem hàng đợi | Câu `in_flag_review` | Không mở được trước khi GV approve |
| Cờ xanh | Flag `green` | Ghi slot `reviewer_1_*` / `reviewer_2_*` + `question_reviewer_flags` |
| Cờ đỏ | Flag `red` + **note bắt buộc** | Fail-fast → `pending_publish` ngay khi có đỏ |
| Đủ 2 xanh | Flag thứ 2 xanh | → `pending_publish` (sẵn XB) |

**Ràng buộc**

- Không phải creator / không phải GV được gán.
- 1 cờ / người / vòng.
- Có `question.flag` (+ `question_flag.view`); **không** `question.publish` / `question.update`.

---

### 3.4 Admin / Super Admin — xuất bản & QA (lớp 2)

**Portal:** `/admin/questions` (filter `status=pending_publish` hoặc `review=must_reject`)  
> SRS từng có `/admin/questions/pending-publish` — **đã gỡ**; dùng list + form.

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| Publish | Transition → `published` | `version` +1; snapshot `question_versions` (+ `review_pipeline`); reset `pipeline_reject_count`; auto QA khi 1 vòng |
| Trả về | → `rejected` + lý do | Có thể chọn outcome cờ đỏ (`confirmed` / `false_positive`); + reject count |
| Private / Retire | Đổi trạng thái | Ẩn / ngừng dùng |
| QA thủ công | `POST …/review-outcomes` | `kind=instructor\|flag\|submit`; cần `question.adjudicate` |
| Bulk XB | `POST …/bulk-transition` | Max 20; **chỉ** publish; chỉ câu **1 vòng** + **2 xanh** |

**Gate publish**

1. Không còn cờ đỏ chưa xử lý (không publish khi có đỏ).
2. Lớp 1 đủ: GV approve + 2 cờ (hoặc legacy 2 slot GV).
3. Publisher ∉ {GV gán, 2 reviewer flag}.
4. **≥2 vòng** pipeline (sau XB gần nhất): chặn nếu còn outcome `pending` trên submit / phiếu GV / cờ — Admin phải adjudicate trước.

**Không được:** `question.create` / `question.update` (Admin không soạn nội dung).

---

### 3.5 Adjudicate QA (đánh giá chất lượng duyệt)

Outcome theo vòng (`review_cycle`) + actor:

| Actor | Outcome | UI gợi ý |
|-------|---------|----------|
| Reviewer (cờ) | `pending` / `confirmed` / `false_positive` / `inconclusive` | Gắn đúng / Gắn sai |
| GV | `pending` / `confirmed` / `miss` / `over_reject` / `inconclusive` | Duyệt đúng / Duyệt sai |
| Editor (submit) | `pending` / `confirmed` / `needs_rework` / `inconclusive` | Soạn đạt / Soạn lỗi |

**Cascade (ví dụ)**

- Admin xác nhận cờ đỏ đúng → red `confirmed` + GV approve cùng vòng → `miss` + Editor submit → `needs_rework`.
- Cờ đỏ gắn sai → red `false_positive`; Editor → `confirmed`.
- Publish 1 vòng, fingerprint khớp → auto `confirmed` cho xanh / approve / submit không tranh chấp.

Báo cáo: `content.review-qa` (KPI Reviewer / GV / Biên tập viên).

---

## 4. Class & route chính (tham chiếu code)

| Vai trò | Class |
|---------|--------|
| Save / demote draft | `Modules\Admin\Actions\SaveAdminQuestionAction` |
| Transition status | `Modules\Admin\Actions\TransitionQuestionStatusAction` |
| GV duyệt | `Modules\QuestionBank\Actions\InstructorReviewQuestionAction` |
| Gắn cờ | `Modules\QuestionBank\Actions\FlagQuestionReviewAction` |
| QA | `Modules\QuestionBank\Actions\AdjudicateReviewOutcomesAction` |
| Bulk publish | `Modules\Admin\Actions\BulkTransitionQuestionsAction` |
| Snapshot | `Modules\Admin\Actions\CaptureQuestionVersionAction` |
| Cycle helpers | `QuestionInstructorReviewCycle`, `QuestionReviewerFlagCycle`, `QuestionQaCompleteness` |

| Method | Path | Actor |
|--------|------|-------|
| POST | `/admin/questions`, `…/{id}` | Editor create/update |
| POST | `/admin/questions/{id}/transition` | submit / withdraw / publish / reject / draft |
| POST | `/admin/questions/bulk-transition` | Admin publish hàng loạt |
| POST | `/admin/questions/{id}/review-outcomes` | Admin adjudicate |
| GET/POST | `/admin/questions/flags`… | Reviewer |
| GET/POST | `/teach/questions/reviews`… `/approve` `/reject` | Instructor |

> SRS §7 còn liệt kê `/api/v1/admin/...` — hiện **chưa** expose API tương đương; workflow chạy qua web routes.

---

## 5. Ma trận quyền (tóm tắt)

| Permission | Editor | Instructor | Reviewer | Admin / SA |
|------------|:------:|:----------:|:--------:|:----------:|
| `question.create` / `update` / `submit` | ✅ | — | — | — |
| `question.review` / approve / reject | — | ✅ | — | — |
| `question.flag` | — | — | ✅ | — |
| `question.publish` | — | — | — | ✅ |
| `question.adjudicate` | — | — | — | ✅ |
| `question.delete` / retire | tùy seed | — | — | ✅ |

---

## 6. Catalogue testcase theo luồng

### 6.1 Editor — tạo / nháp / gửi / rút / khóa

| # | Testcase | File | Method |
|---|----------|------|--------|
| E1 | Tạo câu → `draft` | `AdminQuestionManagementTest` | `test_editor_can_create_draft_question` |
| E2 | Gửi duyệt được, không publish được |同上 | `test_editor_can_submit_for_review_but_cannot_publish` |
| E3 | Submit thiếu giải thích đáp án đúng vẫn được (정책 hiện tại) |同上 | `test_editor_can_submit_for_review_without_correct_option_explanation` |
| E4 | Submit thiếu GV → rollback |同上 | `test_submit_for_review_rolls_back_when_instructor_missing` |
| E5 | Save không gửi field GV → giữ assignment cũ |同上 | `test_save_without_assigned_instructor_field_keeps_existing_assignment` |
| E6 | Không sửa khi `in_review` |同上 | `test_editor_cannot_edit_question_while_in_review` |
| E7 | Withdraw `in_review` → `draft` (không cần save content) |同上 | `test_editor_can_withdraw_in_review_to_draft_without_content_save` |
| E8 | Sau GV approve → không withdraw |同上 | `test_editor_must_wait_after_instructor_approval_cannot_withdraw` |
| E9 | Sau reject GV: thấy lý do, phải về `draft` mới sửa |同上 | `test_editor_sees_instructor_rejection_and_must_return_to_draft` |
| E10 | `rejected` không edit đến khi về draft |同上 | `test_rejected_question_cannot_be_edited_until_back_to_draft` |
| E11 | Save draft không +version |同上 | `test_saving_draft_does_not_increment_version_until_admin_approves` |
| E12 | Sửa câu đã XB → working copy; Qbank giữ bản cũ |同上 | `test_edit_published_keeps_qbank_on_old_version_until_admin_publishes` / `test_creator_edit_published_becomes_working_copy_and_needs_instructor_then_admin` |
| E13 | Clone chỉ Editor; lifecycle riêng |同上 | `test_only_content_editor_can_clone_question` / `test_editor_clone_queues_admin_review_before_publish` |
| E14 | Restore version → draft, không +version admin |同上 | `test_editor_can_view_history_and_restore_an_old_question_version` |
| E15 | Scope: creator chỉ thấy câu của mình |同上 | `test_content_creator_only_sees_and_opens_own_questions` |
| E16 | Form: giải thích theo option, giữ options sau validation error |同上 | `test_question_form_uses_option_explanations_without_general_explanation_field` / `test_question_form_keeps_entered_options_after_validation_error` |
| E17 | Upload ảnh kèm câu |同上 | `test_editor_can_upload_question_image_and_save_it_with_question` |
| E18 | Lookup lessons trên form create |同上 | `test_editor_can_lookup_lessons_on_create_form_without_cct_empty_hint` |

**Unit — vòng duyệt GV**

| # | Testcase | File | Method |
|---|----------|------|--------|
| E19 | Start/reset cycle xóa decision + tăng cycle | `QuestionInstructorReviewCycleTest` | `test_start_or_reset_clears_decision_and_increments_cycle` |

---

### 6.2 Giảng viên — approve / reject

| # | Testcase | File | Method |
|---|----------|------|--------|
| I1 | List hàng đợi chờ duyệt | `TeachQuestionReviewTest` | `test_instructor_can_list_questions_awaiting_review` |
| I2 | Tab đã duyệt / đã từ chối |同上 | `test_instructor_can_view_approved_and_rejected_tabs` |
| I3 | Approve → không bump version |同上 | `test_instructor_can_approve_without_bumping_version` |
| I4 | Reject + lý do → `rejected` |同上 | `test_instructor_can_reject_with_reason` |
| I5 | Reject bắt buộc reason |同上 | `test_reject_requires_reason` |
| I6 | So sánh working copy vs snapshot đã XB |同上 | `test_review_detail_compares_working_copy_with_published_snapshot` |
| I7 | Câu mới: pane published trống |同上 | `test_new_question_review_shows_empty_published_pane` |
| I8 | Giữ HTML stem; ẩn key info trống |同上 | `test_review_detail_preserves_stem_html_and_hides_empty_key_info` |
| I9 | Student không vào queue |同上 | `test_student_cannot_access_review_queue` |
| I10 | Permission granularity approve/reject |同上 | `test_instructor_permission_granularity_for_question_review` |
| I11 | Approve → `in_flag_review` | `QuestionInstructorReviewCycleTest` | `test_assigned_instructor_approval_moves_to_flag_review` |
| I12 | 1 reject fail ngay |同上 | `test_one_reject_fails_the_question_immediately` |
| I13 | GV không được gán không approve |同上 | `test_unassigned_instructor_cannot_approve` |
| I14 | Creator không tự approve |同上 | `test_creator_cannot_approve_own_question` |
| I15 | Cùng GV không vote 2 lần |同上 | `test_same_instructor_cannot_vote_twice` |
| I16 | Admin không publish/reject câu đã bị GV reject | `AdminQuestionManagementTest` | `test_admin_cannot_publish_or_reject_instructor_rejected_question` |

---

### 6.3 Reviewer — gắn cờ

| # | Testcase | File | Method |
|---|----------|------|--------|
| R1 | Role reviewer: có flag, không publish/edit | `QuestionThreeLayerWorkflowTest` | `test_reviewer_has_flag_but_not_publish_or_edit` |
| R2 | Không xem câu trước khi GV approve |同上 | `test_reviewer_cannot_see_question_before_instructor_approval` |
| R3 | Không `question.view` → không mở workspace |同上 | `test_reviewer_without_question_view_cannot_open_question_workspace` |
| R4 | Happy path: GV → 2 xanh → publish |同上 | `test_happy_path_instructor_then_two_flags_then_publish` |
| R5 | Cờ đỏ fail-fast → `pending_publish`, chặn publish |同上 | `test_red_flag_fail_fast_to_pending_publish_and_blocks_publish` |
| R6 | Cờ đỏ bắt buộc note |同上 | `test_red_flag_requires_note` |
| R7 | Đỏ: không XB nhưng được trả về |同上 | `test_red_flag_blocks_publish_but_allows_return` |
| R8 | UI xác nhận cờ không lộ thứ tự slot |同上 | `test_reviewer_flag_confirmation_does_not_reveal_slot_order` |
| R9 | Màn flag show layout học viên (hint + explanation) |同上 | `test_reviewer_flag_show_uses_learner_layout_with_hints_and_explanations` |

---

### 6.4 Admin — publish / trả về / RBAC / snapshot

| # | Testcase | File | Method |
|---|----------|------|--------|
| A1 | Happy path 3 lớp rồi XB | `QuestionThreeLayerWorkflowTest` | `test_happy_path_instructor_then_two_flags_then_publish` |
| A2 | Admin/SA **không** create/edit nội dung | `QuestionTwoLayerPublishTest` | `test_admin_role_cannot_edit_or_create_questions` / `test_super_admin_cannot_edit_or_create_question_content` |
| A3 | Không XB trước khi đủ lớp 1 |同上 | `test_admin_cannot_publish_before_instructor_approval` |
| A4 | Cùng người không vừa GV vừa publisher |同上 | `test_same_person_cannot_be_both_instructor_and_publisher` |
| A5 | Legacy 2 slot GV vẫn XB được | `QuestionThreeLayerWorkflowTest` | `test_legacy_two_instructor_slots_still_publishable` |
| A6 | Reject publish giữ Qbank version cũ | `AdminQuestionManagementTest` | `test_admin_reject_publish_keeps_previous_qbank_version` |
| A7 | Publish lần đầu → học viên thấy trên Qbank |同上 | `test_admin_create_publish_and_student_can_find_question_in_qbank` |
| A8 | Đổi free→premium cần full pipeline |同上 | `test_turning_free_question_premium_requires_full_publish_pipeline` |
| A9 | Timeline + metadata pipeline trên version | `QuestionThreeLayerWorkflowTest` | `test_review_timeline_and_version_pipeline_metadata` |
| A10 | Nhãn «Vòng N · X lần trả về» theo pipeline sau XB | `QuestionPipelineProgressLabelTest` | `test_label_uses_pipeline_cycle_after_last_publish_not_lifetime` / `test_label_keeps_absolute_cycle_when_never_published` |
| A11 | Overlay phục vụ snapshot khi đang in_review | `ServePublishedQuestionTest` | `test_overlay_and_repository_serve_published_snapshot_while_in_review` (+ option id tests) |
| A12 | Diff working copy vs published | `QuestionReviewComparisonTest` | `test_highlights_working_copy_changes_against_published_snapshot` |

---

### 6.5 Admin — QA adjudicate & bulk

| # | Testcase | File | Method |
|---|----------|------|--------|
| Q1 | 1 vòng: không bắt buộc QA thủ công | `QuestionQaCompletenessTest` | `test_one_pipeline_cycle_does_not_require_manual_qa` |
| Q2 | ≥2 vòng + pending → chặn |同上 | `test_two_pipeline_cycles_block_when_outcomes_pending` |
| Q3 | ≥2 vòng đã adjudicate → đủ |同上 | `test_two_pipeline_cycles_complete_when_all_adjudicated` |
| Q4 | Feature gate: block / allow publish theo QA | `QuestionQaGateAndBulkTransitionTest` | `test_publish_blocked_when_pipeline_has_two_cycles_and_qa_pending` / `test_publish_allowed_when_pipeline_has_two_cycles_and_qa_complete` |
| Q5 | Bulk chỉ 1 vòng + 2 xanh |同上 | `test_bulk_publish_only_one_cycle_two_greens` |
| Q6 | Bulk bỏ qua câu có đỏ |同上 | `test_bulk_publish_skips_red_flag` |
| Q7 | Editor không bulk |同上 | `test_editor_cannot_bulk_transition` |
| Q8 | UI list: bulk publish, không bulk reject |同上 | `test_list_shows_bulk_publish_not_bulk_reject` |
| Q9 | Manual adjudicate + permission | `QuestionThreeLayerWorkflowTest` | `test_admin_can_adjudicate_review_outcomes_with_dedicated_permission` |
| Q10 | Báo cáo review-qa |同上 | `test_review_qa_report_is_available` |
| Q11 | Admin reject + đỏ đúng → instructor `miss` | `AdjudicateReviewOutcomesActionTest` | `test_admin_reject_confirms_red_flag_and_marks_instructor_miss` |
| Q12 | Publish: đỏ không đổi → `false_positive` |同上 | `test_publish_marks_unchanged_red_as_false_positive` |
| Q13 | Publish: xanh không tranh chấp → `confirmed` |同上 | `test_publish_confirms_undisputed_green_flags` |
| Q14 | Manual: GV reject sai → `over_reject` |同上 | `test_manual_marks_instructor_reject_as_over_reject` |
| Q15 | Admin reject → Editor `needs_rework` |同上 | `test_admin_reject_marks_editor_submit_needs_rework` |
| Q16 | Publish: fingerprint submit khớp → Editor `confirmed` |同上 | `test_publish_confirms_editor_submit_matching_fingerprint` |
| Q17 | Manual editor outcome |同上 | `test_manual_editor_outcome` |

---

### 6.6 Gán GV theo môn / curriculum

| # | Testcase | File | Method |
|---|----------|------|--------|
| S1 | Submit yêu cầu GV khớp subject | `QuestionThreeLayerWorkflowTest` | `test_submit_requires_assigned_instructor_matching_subject` |
| S2 | Lọc GV eligible theo lesson subjects |同上 | `test_eligible_instructors_filtered_by_lesson_subjects` |
| S3 | GV lệch môn không submit được |同上 | `test_mismatched_subject_instructor_cannot_be_submitted` |
| S4 | Form create không hiện heading curriculum suy luận |同上 | `test_create_form_omits_inferred_curriculum_heading` |

---

### 6.7 Import / Export / Duplicate / Feedback (vệ tinh workflow)

| # | Nhóm | File chính | Điểm cần biết |
|---|------|------------|---------------|
| X1 | Import luôn `draft`; Admin không mở wizard | `QuestionImportExportTest` | Không bypass lớp duyệt |
| X2 | Export filter / selection / rich text |同上 | Quyền `question.view` |
| X3 | Duplicate check ≥30% | `QuestionDuplicatesTest` | Cảnh báo, không chặn workflow |
| X4 | Feedback / report queue cơ bản | `AdminQuestionFeedbackTest` | Chưa cover full: report → sửa → gửi lại → resolve |

---

## 7. Kịch bản E2E nên nhớ khi QA thủ công

### Happy path (lần đầu)

1. Editor tạo draft → gán GV đúng môn → submit → `in_review`.
2. GV approve trên `/teach` → `in_flag_review`.
3. Reviewer A xanh, Reviewer B xanh → `pending_publish`.
4. Admin (khác GV/reviewers) publish → `published`, version = 1, xuất trên Qbank.

### Nhánh reject GV

1. Submit → GV reject + lý do → `rejected`.
2. Editor về draft → sửa → submit (cycle +1).
3. Đi lại lớp 1b → 2. Nếu ≥2 vòng: Admin phải QA trước khi XB.

### Nhánh cờ đỏ

1. GV approve → Reviewer gắn đỏ + note → `pending_publish` ngay.
2. Admin **không** XB được; trả về + chọn «Cờ đỏ đúng / gắn sai».
3. Editor sửa → gửi lại.

### Tái bản câu đã live

1. Editor sửa câu `published` → working copy `draft`; học viên vẫn thấy bản cũ.
2. Đi đủ pipeline → Admin XB → version +1; Qbank đổi snapshot.

### Bulk

1. Chỉ chọn câu `pending_publish`, đúng 1 vòng, 2 xanh → bulk XB OK.
2. Câu ≥2 vòng hoặc có đỏ → bỏ qua (duyệt tay trên form).

---

## 8. Khoảng trống / lệch SRS ↔ code ↔ test

| Hạng mục | SRS | Code | Test |
|----------|-----|------|------|
| Trang `/admin/questions/pending-publish` | Có | **Đã gỡ** → filter list | N/A |
| Dual GV approve (text cũ SRS §0) | 2 GV | **1 GV gán + 2 cờ** | Có; legacy 2-slot vẫn publishable |
| REST `/api/v1/admin/questions/...` | Có | Web transition only | Không |
| Media chưa ready chặn submit | Edge | Cần xác nhận | Yếu / thiếu |
| Meilisearch sync publish/retire | Có | Có job? | Chưa trong suite workflow |
| Optimistic lock 409 | Có | — | Chưa nổi bật |
| Report → sửa → resubmit → resolve | Có | Partial | Feedback nhẹ, thiếu E2E |
| Override khẩn SA bỏ lớp GV | Edge | Không happy-path | Không |
| `AdminQuestionManagementTest` vài case “admin publish trực tiếp” | Mâu thuẫn SRS 3 lớp | Có thể legacy | Nên rà lại khi refactor |

---

## 9. File test — index nhanh

| Suite | Path |
|-------|------|
| 3 lớp E2E + flag + QA UI | `Modules/Admin/tests/Feature/QuestionThreeLayerWorkflowTest.php` |
| QA gate + bulk | `Modules/Admin/tests/Feature/QuestionQaGateAndBulkTransitionTest.php` |
| RBAC publish lớp 2 | `Modules/Admin/tests/Feature/QuestionTwoLayerPublishTest.php` |
| Editor CRUD / lock / version / Qbank overlay | `Modules/Admin/tests/Feature/AdminQuestionManagementTest.php` |
| Teach approve/reject | `Modules/Classroom/tests/Feature/TeachQuestionReviewTest.php` |
| Cycle GV (unit) | `Modules/QuestionBank/tests/Unit/QuestionInstructorReviewCycleTest.php` |
| Adjudicate (unit) | `Modules/QuestionBank/tests/Unit/AdjudicateReviewOutcomesActionTest.php` |
| QA completeness (unit) | `Modules/QuestionBank/tests/Unit/QuestionQaCompletenessTest.php` |
| Snapshot serve (unit) | `Modules/QuestionBank/tests/Unit/ServePublishedQuestionTest.php` |
| Pipeline label (unit) | `Modules/QuestionBank/tests/Unit/QuestionPipelineProgressLabelTest.php` |
| Diff review (unit) | `Modules/QuestionBank/tests/Unit/QuestionReviewComparisonTest.php` |
| Import/Export | `Modules/Admin/tests/Feature/QuestionImportExportTest.php` |
| Duplicates | `Modules/Admin/tests/Feature/QuestionDuplicatesTest.php` |

---

## 10. Chạy test liên quan

```bash
# Toàn bộ workflow 3 lớp + QA + teach
php artisan test \
  Modules/Admin/tests/Feature/QuestionThreeLayerWorkflowTest.php \
  Modules/Admin/tests/Feature/QuestionQaGateAndBulkTransitionTest.php \
  Modules/Admin/tests/Feature/QuestionTwoLayerPublishTest.php \
  Modules/Classroom/tests/Feature/TeachQuestionReviewTest.php \
  Modules/QuestionBank/tests/Unit/QuestionInstructorReviewCycleTest.php \
  Modules/QuestionBank/tests/Unit/AdjudicateReviewOutcomesActionTest.php \
  Modules/QuestionBank/tests/Unit/QuestionQaCompletenessTest.php

# Editor management (suite lớn)
php artisan test Modules/Admin/tests/Feature/AdminQuestionManagementTest.php
```

---

*Cập nhật theo code hiện tại (pipeline: draft → in_review → in_flag_review → pending_publish → published). Khi đổi máy trạng thái hoặc quyền, sửa SRS 35 trước, rồi sync document này + test.*

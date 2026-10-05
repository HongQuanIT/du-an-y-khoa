<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Services\AdaptiveSessionBriefing;
use PHPUnit\Framework\TestCase;

final class AdaptiveSessionBriefingTest extends TestCase
{
    public function test_brief_v2_shows_buckets_and_due_band(): void
    {
        $log = <<<'LOG'
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] start {"user_id":7,"limit":3,"focus":"balanced","pipeline":"filter_group_quota_v2","blueprint_id":3,"trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] pool {"pool_size":10,"blueprint_id":3,"trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] filter {"active_count":10,"eligible_count":6,"unseen_count":4,"excluded":{"thrash":1,"cooldown":2,"version_mismatch":0},"resting":[{"question_id":"q-rest","reason":"cooldown","detail":"Vừa đưa vào phiên thích ứng 1.0 giờ trước — nghỉ serve 20 giờ","rest_until":"2026-10-04T05:00:00+00:00","due_at":"2026-10-05T00:00:00+00:00","is_due":false,"stability":3,"retention":0.85},{"question_id":"q-thrash","reason":"thrash","detail":"Sai liên tiếp 5 — tạm không đưa vào phiên 7 ngày","rest_until":"2026-10-11T00:00:00+00:00","due_at":"2026-09-30T00:00:00+00:00","is_due":true,"stability":1,"retention":0.5}],"trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] group {"weak_pool":3,"due_pool":4,"unseen_count":4,"overlap_weak_due":2,"trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] quota {"due_band":"mid","new_share":0.2,"new_count":1,"review_slots":2,"weak_quota":1,"due_quota":1,"focus":"balanced","trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] result {"pipeline":"filter_group_quota_v2","picked_count":3,"bucket_counts":{"yeu":1,"sap_quen":1,"moi":1,"lap_day":0},"shortfall":0,"items":[{"question_id":"q1","position":1,"bucket":"yeu","reason":"Sai 3/5 lần gần nhất (độ yếu 67%)","weakness":0.67,"days_since":2,"retention":0.8,"stability":3,"is_due":false,"due_at":"2026-10-05T00:00:00+00:00"},{"question_id":"q2","position":2,"bucket":"sap_quen","reason":"Đã 10 ngày chưa ôn","weakness":0.3,"days_since":10,"retention":0.4,"stability":7,"is_due":true,"due_at":"2026-09-24T00:00:00+00:00"},{"question_id":"q3","position":3,"bucket":"moi","reason":"Câu mới — bài học mới: Viêm phổi cộng đồng","weakness":null,"days_since":null,"retention":null,"stability":null,"is_due":null,"due_at":null}],"trace_id":"v2"}
[2026-10-04 10:00:00] testing.DEBUG: [adaptive] served {"session_id":"s1","user_id":7,"count":3,"question_ids":["q1","q2","q3"],"trace_id":"v2"}
[2026-10-04 10:20:00] testing.DEBUG: [adaptive] graded {"session_id":"s1","user_id":7,"total":3,"correct_count":2,"incorrect_count":1,"omitted_count":0,"items":[{"position":1,"question_id":"q1","result":"incorrect","valid":true,"s_before":3,"s_after":1,"t_days":2,"was_due":false,"weakness_after":0.67,"wrong_streak":1,"recent_results":[true,false],"due_at":"2026-10-05T10:20:00+00:00","time_spent_seconds":40,"note":"Sai → về bậc 1"},{"position":2,"question_id":"q2","result":"correct","valid":true,"s_before":7,"s_after":14,"t_days":10,"was_due":true,"weakness_after":0.3,"wrong_streak":0,"recent_results":[true,true],"due_at":"2026-10-18T10:20:00+00:00","time_spent_seconds":55,"note":"Đúng đúng hạn → lên bậc"},{"position":3,"question_id":"q3","result":"correct","valid":true,"s_before":null,"s_after":3,"t_days":null,"was_due":null,"weakness_after":0.33,"wrong_streak":0,"recent_results":[true],"due_at":"2026-10-07T10:20:00+00:00","time_spent_seconds":30,"note":"Lần đầu đúng → S = 3"}],"trace_id":"v2"}
LOG;

        $briefing = new AdaptiveSessionBriefing(
            learners: [7 => 'Mai Anh'],
            blueprints: [3 => 'Nội tổng quát'],
            questions: [
                'q1' => 'Q1042',
                'q2' => 'Q2201',
                'q3' => 'Q3001',
                'q-rest' => 'Q4001',
                'q-thrash' => 'Q4002',
            ],
        );
        $card = $briefing->brief($briefing->runs($log)[0]);

        $this->assertSame('v2', $card['pipeline']);
        $this->assertSame('Lọc → Phân nhóm → Phân suất', $card['pipeline_label']);
        $this->assertSame('Cân bằng', $card['focus']);
        $this->assertStringContainsString('① Lọc', $card['summary']);
        $this->assertStringContainsString('② Phân nhóm', $card['summary']);
        $this->assertStringContainsString('③ Phân suất', $card['summary']);
        $this->assertStringContainsString('Due vừa', $card['summary']);
        $this->assertStringContainsString('1×N', $card['summary']);
        $this->assertSame(['Lọc', 'Phân nhóm', 'Phân suất'], array_column($card['stages'], 'name'));
        $filterItems = implode(' ', $card['stages'][0]['items']);
        $quotaItems = implode(' ', $card['stages'][2]['items']);
        $this->assertStringContainsString('sai ≥3', $filterItems);
        $this->assertStringContainsString('≥5', $filterItems);
        $this->assertStringContainsString('Không gồm tỉ lệ câu mới', $filterItems);
        $this->assertStringContainsString('duePool < 1×N', $quotaItems);
        $this->assertStringContainsString('duePool ≥ 3×N', $quotaItems);
        $formulas = array_column($card['formulas'], 'expr', 'name');
        $this->assertStringContainsString('duePool < 1×N', $formulas['③ Phân suất — câu mới']);
        $this->assertSame(['Yếu', 'Sắp quên', 'Mới'], array_column($card['table'], 'bucket'));
        $this->assertStringContainsString('Đến hạn', $card['table'][0]['due']);
        $this->assertStringContainsString('Đã đến hạn', $card['table'][1]['due']);
        $this->assertSame('—', $card['table'][2]['due']);
        $this->assertSame(['Q4001', 'Q4002'], array_column($card['resting'], 'code'));
        $this->assertSame(['Nghỉ serve', 'Thrash'], array_column($card['resting'], 'reason'));
        $this->assertStringContainsString('Đến hạn', $card['resting'][0]['due']);
        $this->assertStringContainsString('Đã đến hạn', $card['resting'][1]['due']);
        $this->assertSame(['Sai', 'Đúng', 'Đúng'], array_column($card['graded'], 'result'));
        $this->assertSame(['3,00', '7,00', '—'], array_column($card['graded'], 's_before'));
        $this->assertSame(['1,00', '14,00', '3,00'], array_column($card['graded'], 's_after'));
        $this->assertStringContainsString('Sai → về bậc 1', $card['graded'][0]['note']);
        $this->assertStringContainsString('bảng chấm điểm', $card['closing']);
        $formulaNames = array_column($card['formulas'], 'name');
        $this->assertSame('Thứ tự pipeline', $formulaNames[0]);
        $this->assertStringStartsWith('① Lọc', $formulaNames[1]);
        $this->assertStringStartsWith('②', $formulaNames[2]);
        $this->assertStringStartsWith('③', $formulaNames[4]);
    }

    public function test_brief_tells_a_balanced_session_in_business_language(): void
    {
        $log = <<<'LOG'
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] path {"path":"weighted_selector","user_id":7,"limit":2,"focus":"balanced","blueprint_id":3,"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] start {"user_id":7,"limit":2,"focus":"balanced","w_weakness":0.55,"w_memory":0.45,"blueprint_id":3,"organ_system_ids":[],"subject_ids":[],"can_use_premium":true,"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] pool {"scope":"blueprint_matrix","lesson_ids_count":4,"pool_size":4,"blueprint_id":3,"organ_system_ids":[],"subject_ids":[],"premium_only_free":false,"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] coverage_split {"pool_size":4,"unseen_count":2,"seen_count":2,"unseen_ratio":0.5,"quota_unseen":1,"quota_review":1,"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] review_scores {"focus":"balanced","w_weakness":0.55,"w_memory":0.45,"candidates":2,"weight_min":0.2,"weight_max":0.7,"formula":"weight = max(0.01, (wW*weakness + wM*memory) * cooldown)","top":[{"question_id":"q-weak","correct":2,"wrong":8,"weakness":0.75,"days_since_seen":3,"memory":0.14,"base":0.48,"sessions_since_served":0,"cooldown":0.3,"weight":0.14}],"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] review_sampled {"limit":1,"picked":["q-weak"],"weights":{"q-weak":0.14},"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] result {"focus":"balanced","picked_count":2,"picked_unseen":1,"picked_review":1,"question_ids":["q-weak","q-new"],"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] served {"session_id":"s1","user_id":7,"count":2,"question_ids":["q-weak","q-new"],"trace_id":"run1"}
LOG;

        $briefing = new AdaptiveSessionBriefing(
            learners: [7 => 'Mai Anh'],
            blueprints: [3 => 'Nội tổng quát'],
            questions: ['q-weak' => 'Q1042', 'q-new' => 'Q2201'],
        );

        $card = $briefing->brief($briefing->runs($log)[0]);
        $text = $this->flatten($card);

        $this->assertSame('Mai Anh', $card['learner']);
        $this->assertSame('Cân bằng', $card['focus']);
        $this->assertStringContainsString('Mai Anh nhận 2 câu hướng Cân bằng từ 4 câu của Nội tổng quát.', $card['headline']);
        $this->assertStringContainsString('Q1042', $text);
        $this->assertStringContainsString('Hay trả lời sai (8 sai / 10 lần)', $text);
        $this->assertStringContainsString('Q2201', $text);
        $this->assertStringNotContainsString('weakness', $text);
        $this->assertStringNotContainsString('cooldown', $text);
        $this->assertStringNotContainsString('trace_id', $text);
        $this->assertStringNotContainsString('q-weak', $text);
        $this->assertStringNotContainsString('user_id', $text);
    }

    public function test_each_session_table_ranks_questions_by_priority(): void
    {
        $log = <<<'LOG'
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] path {"path":"weighted_selector","user_id":7,"trace_id":"run1"}
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] result {"picked_count":2,"question_ids":["q-low","q-new"],"ranking":[{"question_id":"q-high","kind":"review","selected":false,"correct":1,"wrong":1,"weakness":0.5,"days_since_seen":2,"memory":0.1,"cooldown":1,"weight":0.9},{"question_id":"q-low","kind":"review","selected":true,"correct":2,"wrong":8,"weakness":0.75,"days_since_seen":3,"memory":0.14,"cooldown":0.3,"weight":0.2},{"question_id":"q-new","kind":"fresh","selected":true,"correct":0,"wrong":0,"weakness":null,"weight":null}],"trace_id":"run1"}
[2026-09-26 14:06:01] testing.DEBUG: [adaptive] path {"path":"weighted_selector","user_id":7,"trace_id":"run2"}
[2026-09-26 14:06:01] testing.DEBUG: [adaptive] result {"picked_count":1,"question_ids":["q-only"],"ranking":[{"question_id":"q-only","kind":"review","selected":true,"correct":0,"wrong":4,"weakness":0.8,"days_since_seen":1,"memory":0.05,"cooldown":1,"weight":0.6}],"trace_id":"run2"}
LOG;

        $briefing = new AdaptiveSessionBriefing(
            learners: [7 => 'Mai Anh'],
            questions: [
                'q-high' => 'Q9000',
                'q-low' => 'Q1042',
                'q-new' => 'Q2201',
                'q-only' => 'Q3000',
            ],
        );
        $runs = $briefing->runs($log);
        $first = $briefing->brief($runs[0])['table'];
        $second = $briefing->brief($runs[1])['table'];

        $this->assertSame(['Q9000', 'Q1042', 'Q2201'], array_column($first, 'code'));
        $this->assertSame(['0,90', '0,20', 'Chọn đều'], array_column($first, 'priority'));
        $this->assertSame(['81,82%', '18,18%', '—'], array_column($first, 'share'));
        $this->assertSame(['Không', 'Có', 'Có'], array_column($first, 'chosen'));
        $this->assertSame(['Q3000'], array_column($second, 'code'));
        $this->assertSame('0,60', $second[0]['priority']);
        $this->assertSame('100,00%', $second[0]['share']);
    }

    public function test_brief_uses_the_forgetting_curve_and_counts_top_up(): void
    {
        $log = <<<'LOG'
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] path {"path":"weighted_selector","user_id":7,"focus":"retention","trace_id":"curve"}
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] start {"user_id":7,"focus":"retention","w_weakness":0.3,"w_memory":0.7,"trace_id":"curve"}
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] pool {"scope":"blueprint_and_content","pool_size":6,"trace_id":"curve"}
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] coverage_split {"pool_size":6,"unseen_count":3,"seen_count":3,"quota_unseen":2,"quota_review":1,"trace_id":"curve"}
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] top_up {"need":1,"filled":1,"trace_id":"curve"}
[2026-09-29 16:00:00] testing.DEBUG: [adaptive] result {"picked_count":4,"picked_unseen":2,"picked_review":1,"sheet":[{"question_id":"q-graded","kind":"review","weakness":0.75,"days_since_seen":10.5,"stability_days":4,"memory":0.93,"cooldown":1,"weight":0.8,"correct":1,"wrong":3},{"question_id":"q-fresh-1","kind":"fresh","weight":null},{"question_id":"q-fresh-2","kind":"fresh","weight":null},{"question_id":"q-fresh-3","kind":"fresh","weight":null}],"trace_id":"curve"}
LOG;

        $card = (new AdaptiveSessionBriefing(
            questions: ['q-graded' => 'Q1', 'q-fresh-1' => 'Q2', 'q-fresh-2' => 'Q3', 'q-fresh-3' => 'Q4'],
        ))->brief((new AdaptiveSessionBriefing)->runs($log)[0]);

        $this->assertSame('3', $card['stats'][1]['value']);
        $this->assertSame('Câu chưa chấm', $card['stats'][1]['label']);
        $this->assertSame('1', $card['stats'][2]['value']);
        $this->assertStringContainsString('phần giao giữa đề thi', $card['summary']);
        $this->assertStringContainsString('3 câu chưa chấm và 3 câu đã chấm', $card['summary']);
        $this->assertStringContainsString('bốc thêm 1 câu', $card['summary']);
        $this->assertStringContainsString('độ bền thấp hoặc đã lâu kể từ lần chấm', $card['summary']);

        $formulas = array_column($card['formulas'], 'expr', 'name');
        $this->assertStringContainsString('**(số lần sai + 1) / (số lần đúng + số lần sai + 2)**', $formulas['Độ yếu']);
        $this->assertStringContainsString('Câu bỏ qua thì không cộng vào', $formulas['Độ yếu']);
        $this->assertStringContainsString('67%', $formulas['Độ yếu']);
        $this->assertStringContainsString('**0,5 đến 365 ngày**', $formulas['Mức cần ôn']);
        $this->assertStringContainsString('0,30 × độ yếu + 0,70 × mức cần ôn', $formulas['Điểm cần ôn']);
        $this->assertStringContainsString('đã quá 2 ngày', $formulas['Tránh lặp']);
        $this->assertStringContainsString('từ hai phiên tạo sau lần chọn', $formulas['Tránh lặp']);

        $graded = $card['table'][0];
        $this->assertSame('10,50', $graded['days']);
        $this->assertSame('4,00', $graded['stability']);
        $this->assertSame('0,93', $graded['memory']);
        $this->assertSame('Ôn lại', $graded['role']);
        $this->assertSame('Chưa chấm', $card['table'][1]['role']);
    }

    public function test_runs_stay_separate_when_two_learners_are_logged(): void
    {
        $log = <<<'LOG'
[2026-09-26 14:05:01] testing.DEBUG: [adaptive] path {"path":"weighted_selector","user_id":1,"focus":"weak_focus","trace_id":"a"}
[2026-09-26 14:05:02] testing.DEBUG: [adaptive] path {"path":"legacy_incorrect_first","user_id":2,"trace_id":"b"}
[2026-09-26 14:05:02] testing.DEBUG: [adaptive] result {"picked_count":3,"trace_id":"a"}
LOG;

        $briefing = new AdaptiveSessionBriefing(learners: [1 => 'An', 2 => 'Bình']);
        $runs = $briefing->runs($log);

        $this->assertCount(2, $runs);
        $this->assertArrayHasKey('result', $runs[0]['steps']);
        $this->assertArrayNotHasKey('result', $runs[1]['steps']);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function flatten(array $value): string
    {
        $parts = [];
        array_walk_recursive($value, function (mixed $item) use (&$parts): void {
            if (is_string($item)) {
                $parts[] = $item;
            }
        });

        return implode(' ', $parts);
    }
}

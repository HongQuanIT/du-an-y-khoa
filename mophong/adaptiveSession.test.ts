/**
 * Bộ test nghiệm thu cho thuật toán mẫu.
 * Chạy: node --test adaptiveSession.test.ts   (Node.js 22.18+ chạy thẳng file .ts)
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  applyAttempt, buildSession, newQuestionCount, retention, seededRng, weakness,
  DEFAULT_PARAMS, type QuestionMeta, type QuestionState,
} from './adaptiveSession.ts';

const D = (day: number, hour = 9) => new Date(Date.UTC(2027, 0, day, hour));
const meta = (id: string, topicId = 'HTM01', v = 1): QuestionMeta =>
  ({ questionId: id, topicId, isPublished: true, hasOpenReport: false, contentVersion: v });
const st = (id: string, step: number, answeredDay: number, results: boolean[], v = 1): QuestionState => ({
  questionId: id, ladderStep: step, lastAnsweredAt: D(answeredDay), lastSelectedAt: D(answeredDay),
  recentResults: results, wrongCount: results.filter(r => !r).length, questionVersion: v,
});
const att = (id: string, day: number, isCorrect: boolean, responseMs = 40_000, submitted = true) =>
  ({ questionId: id, answeredAt: D(day), isCorrect, responseMs, submitted });

test('độ yếu: (sai + 1) / (số lần + 2)', () => {
  assert.equal(weakness([false]), 2 / 3);               // sai 1/1 -> 67%
  assert.equal(weakness([true]), 1 / 3);                // đúng 1/1 -> 33%
  assert.equal(weakness([false, false, false, true, false]), 5 / 7); // sai 4/5 -> 71%
});

test('khả năng ghi nhớ: R = 0,9 ^ (t / S)', () => {
  assert.ok(Math.abs(retention(st('q', 1, 1, [false]), D(5)) - 0.6561) < 1e-9); // S=1, t=4
  assert.ok(Math.abs(retention(st('q', 3, 1, [true]), D(8)) - 0.9) < 1e-9);     // S=7, t=7: đúng hạn
});

test('thang độ bền: 5 quy tắc đổi bậc', () => {
  const m = meta('q');
  assert.equal(applyAttempt(undefined, att('q', 1, true), m).state!.ladderStep, 2);   // lần đầu đúng
  assert.equal(applyAttempt(undefined, att('q', 1, false), m).state!.ladderStep, 1);  // lần đầu sai
  assert.equal(applyAttempt(st('q', 4, 1, [true]), att('q', 3, false), m).state!.ladderStep, 1); // sai -> bậc 1
  assert.equal(applyAttempt(st('q', 2, 1, [true]), att('q', 5, true), m).state!.ladderStep, 3);  // đúng, t=4 >= 3
  assert.equal(applyAttempt(st('q', 2, 1, [true]), att('q', 2, true), m).state!.ladderStep, 2);  // đúng, t=1 < 3: giữ
  assert.equal(applyAttempt(st('q', 6, 1, [true]), att('q', 80, true), m).state!.ladderStep, 6); // trần bậc 6
});

test('lượt không hợp lệ không đổi độ yếu và độ bền, nhưng tính thời gian nghỉ', () => {
  const prev = st('q', 3, 1, [true, true]);
  const fast = applyAttempt(prev, att('q', 9, false, 3_000), meta('q'));
  assert.equal(fast.log.isValid, false);
  assert.equal(fast.state!.ladderStep, 3);
  assert.deepEqual(fast.state!.recentResults, [true, true]);
  assert.deepEqual(fast.state!.lastSelectedAt, D(9));
  const unsubmitted = applyAttempt(prev, att('q', 9, false, 60_000, false), meta('q'));
  assert.equal(unsubmitted.log.isValid, false);
  assert.equal(unsubmitted.log.isCorrect, null);
});

test('nhật ký ghi độ bền và R dự đoán trước khi làm', () => {
  const { log } = applyAttempt(st('q', 1, 1, [false]), att('q', 5, true), meta('q'));
  assert.equal(log.sBefore, 1);
  assert.ok(Math.abs(log.rBefore! - 0.6561) < 1e-9);
});

test('câu sửa nội dung: trạng thái cũ bị bỏ, coi như lần đầu', () => {
  const { state } = applyAttempt(st('q', 5, 1, [true, true, true], 1), att('q', 3, false), meta('q', 'HTM01', 2));
  assert.equal(state!.ladderStep, 1);
  assert.deepEqual(state!.recentResults, [false]);
});

test('số câu mới theo tồn đọng — mọi mode giống nhau (phiên 10 câu)', () => {
  const p = DEFAULT_PARAMS;
  assert.equal(newQuestionCount(0, 50, 0, 10, p), 10);  // học viên mới: toàn câu mới
  assert.equal(newQuestionCount(9, 50, 20, 10, p), 3);  // due thấp: 30%
  assert.equal(newQuestionCount(10, 50, 20, 10, p), 2); // due vừa: 20%
  assert.equal(newQuestionCount(30, 50, 40, 10, p), 1); // due cao: 10%
});

test('câu trong thời gian nghỉ, câu đang có báo lỗi, câu ở phiên khác đều bị loại', () => {
  const qs = [meta('a'), meta('b'), { ...meta('c'), hasOpenReport: true }, meta('d')];
  const states = [st('a', 1, 1, [false]), st('b', 1, 1, [false]), st('c', 1, 1, [false]), st('d', 1, 1, [false])];
  states[0].lastSelectedAt = D(5, 0); // 'a' vừa được đưa vào phiên 9 giờ trước
  const res = buildSession({
    mode: 'diem_yeu', size: 10, now: D(5), states, questions: qs, topics: [],
    questionIdsInOpenSessions: new Set(['d']), rng: seededRng(1),
  });
  assert.deepEqual(res.items.map(i => i.questionId), ['b']);
  assert.equal(res.shortfall, 9);
  assert.ok(res.message);
});

test('chia suất theo loại phiên khi hai nhóm đủ lớn', () => {
  // 20 câu yếu nhưng chưa đến hạn, 40 câu chắc nhưng đã quá hạn, 30 câu chưa làm
  const qs: QuestionMeta[] = [], states: QuestionState[] = [];
  for (let i = 0; i < 20; i++) { qs.push(meta(`y${i}`)); states.push(st(`y${i}`, 2, 9, [false, false, true])); }
  for (let i = 0; i < 40; i++) { qs.push(meta(`s${i}`)); states.push(st(`s${i}`, 3, 1, [true, true, true])); }
  for (let i = 0; i < 30; i++) qs.push(meta(`n${i}`, `T${i % 5}`));
  const topics = [0, 1, 2, 3, 4].map(i => ({ topicId: `T${i}`, weight: 1 }));
  const count = (mode: 'diem_yeu' | 'cung_co' | 'can_bang') => {
    const res = buildSession({ mode, size: 10, now: D(11), states, questions: qs, topics,
      questionIdsInOpenSessions: new Set(), rng: seededRng(7) });
    const c = { yeu: 0, sap_quen: 0, moi: 0, lap_day: 0 };
    for (const it of res.items) c[it.bucket]++;
    return c;
  };
  // due = 40 ≥ 3×10 → tồn đọng cao: 10% câu mới (1) + 9 suất ôn; mọi mode cùng newCount.
  assert.deepEqual(count('diem_yeu'), { yeu: 9, sap_quen: 0, moi: 1, lap_day: 0 });
  assert.deepEqual(count('cung_co'), { yeu: 0, sap_quen: 9, moi: 1, lap_day: 0 });
  assert.deepEqual(count('can_bang'), { yeu: 5, sap_quen: 4, moi: 1, lap_day: 0 });
});

test('học viên mới: câu mới mở bài trọng số cao rồi phủ nốt bài đó', () => {
  // Sau câu đầu thuộc bài "nang", bài đó thành dang dở → các suất mới còn lại phủ nốt "nang".
  const qs: QuestionMeta[] = [];
  for (let i = 0; i < 10; i++) qs.push(meta(`A${i}`, 'nang'));
  for (let i = 0; i < 10; i++) qs.push(meta(`B${i}`, 'nhe'));
  const res = buildSession({ mode: 'can_bang', size: 8, now: D(1), states: [], questions: qs,
    topics: [{ topicId: 'nang', weight: 3 }, { topicId: 'nhe', weight: 1 }],
    questionIdsInOpenSessions: new Set(), rng: seededRng(3) });
  assert.equal(res.items.length, 8);
  assert.equal(res.items.filter(i => i.questionId.startsWith('A')).length, 8);
  assert.equal(res.items[0].reason, 'Ca lâm sàng mới'); // câu mở bài
  assert.ok(res.items.slice(1).every(i => i.reason === 'Ca mới — tiếp tục bài đang học'));
});

test('câu mới ưu tiên bài học dang dở trước bài chưa mở', () => {
  // Đã làm 1 câu bài "nhe" (w=1); bài "nang" (w=3) chưa đụng → vẫn phủ nốt "nhe" trước.
  const qs: QuestionMeta[] = [];
  for (let i = 0; i < 5; i++) qs.push(meta(`A${i}`, 'nang'));
  for (let i = 0; i < 5; i++) qs.push(meta(`B${i}`, 'nhe'));
  const res = buildSession({
    mode: 'can_bang', size: 4, now: D(3),
    states: [st('B0', 2, 1, [true])],
    questions: qs,
    topics: [{ topicId: 'nang', weight: 3 }, { topicId: 'nhe', weight: 1 }],
    questionIdsInOpenSessions: new Set(), rng: seededRng(1),
  });
  const moi = res.items.filter(i => i.bucket === 'moi');
  assert.ok(moi.length >= 1);
  for (const it of moi) {
    assert.ok(it.questionId.startsWith('B'), `expected in-progress lesson, got ${it.questionId}`);
    assert.equal(it.reason, 'Ca mới — tiếp tục bài đang học');
  }
});

test('đảo đáp án: mỗi câu có một hoán vị đủ 5 đáp án', () => {
  const res = buildSession({ mode: 'can_bang', size: 3, now: D(1), states: [],
    questions: [meta('a'), meta('b'), meta('c')], topics: [{ topicId: 'HTM01', weight: 1 }],
    questionIdsInOpenSessions: new Set(), rng: seededRng(9) });
  for (const it of res.items) assert.deepEqual([...it.optionOrder].sort(), [0, 1, 2, 3, 4]);
});

test('cùng hạt giống thì cùng kết quả', () => {
  const input = () => ({ mode: 'can_bang' as const, size: 5, now: D(1), states: [],
    questions: Array.from({ length: 20 }, (_, i) => meta(`q${i}`, `T${i % 4}`)),
    topics: [0, 1, 2, 3].map(i => ({ topicId: `T${i}`, weight: i + 1 })),
    questionIdsInOpenSessions: new Set<string>(), rng: seededRng(42) });
  assert.deepEqual(buildSession(input()).items, buildSession(input()).items);
});

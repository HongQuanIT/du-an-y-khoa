/**
 * Chạy thử: 1 học viên, bể 50 câu thuộc 5 chủ đề, 10 phiên Cân bằng × 10 câu.
 * Chạy: node demo.ts
 * Kết quả Đúng/Sai do một học viên giả lập sinh ra; phần còn lại là thuật toán thật.
 */
import {
  applyAttempt, buildSession, retention, seededRng,
  type QuestionMeta, type QuestionState,
} from './adaptiveSession.ts';

const rng = seededRng(2026);
const day = (d: number) => new Date(Date.UTC(2027, 0, 1) + (d - 1) * 86_400_000 + 9 * 3_600_000);

const topics = [
  { topicId: 'Tim mạch', weight: 3 }, { topicId: 'Hô hấp', weight: 2 }, { topicId: 'Tiêu hóa', weight: 2 },
  { topicId: 'Thận', weight: 2 }, { topicId: 'Nội tiết', weight: 1 },
];
const questions: QuestionMeta[] = Array.from({ length: 50 }, (_, i) => ({
  questionId: `Q${String(i + 1).padStart(2, '0')}`, topicId: topics[i % 5].topicId,
  isPublished: true, hasOpenReport: false, contentVersion: 1,
}));
const metaOf = new Map(questions.map(q => [q.questionId, q]));

// Học viên giả lập: mức hiểu ban đầu mỗi câu 35–90%, tăng sau mỗi lần đọc giải thích.
const understanding = new Map(questions.map(q => [q.questionId, 0.35 + 0.55 * rng()]));
const states = new Map<string, QuestionState>();

const schedule = [1, 2, 3, 4, 5, 5.5, 8, 10, 13, 17];
console.log('Phiên | Ngày | Nhóm Yếu | Nhóm Sắp quên | Câu mới | Chọn Yếu/Sắp quên/Mới/Lấp | Đúng');
schedule.forEach((d, k) => {
  const now = day(d);
  const res = buildSession({
    mode: 'can_bang', size: 10, now, states: [...states.values()], questions, topics,
    questionIdsInOpenSessions: new Set(), rng,
  });
  const c = { yeu: 0, sap_quen: 0, moi: 0, lap_day: 0 };
  let correct = 0;
  for (const it of res.items) {
    c[it.bucket]++;
    const prev = states.get(it.questionId);
    const pTrue = understanding.get(it.questionId)! * (prev ? retention(prev, now) : 1);
    const isCorrect = rng() < pTrue;
    correct += isCorrect ? 1 : 0;
    understanding.set(it.questionId, Math.min(0.97, understanding.get(it.questionId)! * 0.75 + 0.25));
    const { state } = applyAttempt(prev, { questionId: it.questionId, answeredAt: now, submitted: true,
      isCorrect, responseMs: 60_000 }, metaOf.get(it.questionId)!);
    if (state) states.set(it.questionId, state);
  }
  console.log(`${String(k + 1).padStart(5)} | ${String(d).padStart(4)} | ${String(res.debug.weakPool).padStart(8)} | ` +
    `${String(res.debug.duePool).padStart(13)} | ${String(res.debug.newCount).padStart(7)} | ` +
    `${c.yeu}/${c.sap_quen}/${c.moi}/${c.lap_day}`.padStart(26) + ` | ${correct}/10` +
    (res.shortfall ? `  (thiếu ${res.shortfall}: ${res.message})` : ''));
});

const end = day(17);
const steps = new Map<number, number>();
for (const s of states.values()) steps.set(s.ladderStep, (steps.get(s.ladderStep) ?? 0) + 1);
console.log(`\nĐã làm ${states.size}/50 câu. Số câu theo bậc độ bền:`,
  Object.fromEntries([...steps.entries()].sort((a, b) => a[0] - b[0]).map(([k, v]) => [`bậc ${k}`, v])));
const seenTopics = new Map<string, number>();
for (const s of states.values()) {
  const t = metaOf.get(s.questionId)!.topicId;
  seenTopics.set(t, (seenTopics.get(t) ?? 0) + 1);
}
console.log('Số câu đã gặp theo chủ đề (trọng số 3:2:2:2:1):', Object.fromEntries(seenTopics));
void end;

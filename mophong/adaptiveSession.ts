/**
 * Thuật toán mẫu: chọn câu cho phiên ôn tập thích ứng
 * ----------------------------------------------------
 * Bản tham chiếu cho kỹ sư, viết bằng TypeScript thuần, không phụ thuộc thư viện,
 * không đọc/ghi cơ sở dữ liệu. Tầng dịch vụ tự nạp dữ liệu từ Postgres, gọi các hàm
 * thuần ở đây, rồi ghi kết quả trở lại.
 *
 * Hai hàm chính:
 *   - applyAttempt(): cập nhật trạng thái sau MỘT lượt làm (mục 3 của đặc tả).
 *   - buildSession(): dựng một phiên N câu (mục 4 của đặc tả).
 *
 * Quy ước: 1 ca lâm sàng = 1 điểm kiến thức; mọi trạng thái tính theo cặp học viên – câu.
 */

// ===================== Kiểu dữ liệu =====================

export type Mode = 'diem_yeu' | 'cung_co' | 'can_bang';
export type Bucket = 'yeu' | 'sap_quen' | 'moi' | 'lap_day';

export interface Params {
  ladderDays: number[];          // thang độ bền, bậc 1..n
  retentionBase: number;         // R = base ^ (t / S)
  weakWindow: number;            // số lần làm hợp lệ gần nhất dùng tính độ yếu
  weakThreshold: number;         // vào nhóm Yếu khi độ yếu >= ngưỡng
  cooldownHours: number;         // thời gian nghỉ tối thiểu kể từ lần được đưa vào phiên
  minResponseMs: number;         // lượt nhanh hơn ngưỡng này là không hợp lệ
  newShareLowBacklog: number;    // tỷ lệ câu mới khi tồn đọng thấp (mọi mode)
  newShareMidBacklog: number;    // tỷ lệ câu mới khi tồn đọng vừa
  newShareHighBacklog: number;   // tỷ lệ câu mới khi tồn đọng cao (vẫn giữ phủ bài)
  midBacklogFactor: number;      // tồn đọng vừa khi nhóm Sắp quên >= factor × số câu phiên
  highBacklogFactor: number;     // tồn đọng cao khi nhóm Sắp quên >= factor × số câu phiên
  diversityFactor: number;       // bốc ngẫu nhiên trong top (factor × suất) của mỗi nhóm
  optionCount: number;           // số đáp án mỗi câu
}

export const DEFAULT_PARAMS: Params = {
  ladderDays: [1, 3, 7, 14, 30, 60],
  retentionBase: 0.9,
  weakWindow: 5,
  weakThreshold: 0.5,
  cooldownHours: 20,
  minResponseMs: 5000,
  newShareLowBacklog: 0.3,   // due thấp → ~30% N (N=10 → 3)
  newShareMidBacklog: 0.2,   // due vừa  → ~20% N (N=10 → 2)
  newShareHighBacklog: 0.1,  // due cao  → ~10% N (N=10 → 1)
  midBacklogFactor: 1,
  highBacklogFactor: 3,
  diversityFactor: 2,
  optionCount: 5,
};

/** Một dòng user_question_state. */
export interface QuestionState {
  questionId: string;
  ladderStep: number;            // 1..ladderDays.length
  lastAnsweredAt: Date;          // lần làm HỢP LỆ gần nhất
  lastSelectedAt: Date;          // lần gần nhất được đưa vào phiên (kể cả lượt không hợp lệ)
  recentResults: boolean[];      // tối đa weakWindow phần tử, cũ -> mới
  wrongCount: number;
  questionVersion: number;       // phiên bản nội dung của câu lúc học viên làm
}

/** Thông tin câu hỏi lấy từ ngân hàng câu hỏi. */
export interface QuestionMeta {
  questionId: string;
  topicId: string;               // chủ đề trong ma trận 128 chủ đề
  isPublished: boolean;          // đang ở trạng thái phát hành cho học viên
  hasOpenReport: boolean;        // đang có báo lỗi chưa xử lý
  contentVersion: number;        // CHỈ tăng khi sửa nội dung (đổi đáp án, đổi dữ kiện)
}

/** Trọng số chủ đề lấy từ ma trận đề thi (QĐ 22). */
export interface TopicWeight {
  topicId: string;
  weight: number;
}

/** Một lượt làm do ứng dụng gửi về. */
export interface AttemptInput {
  questionId: string;
  answeredAt: Date;
  submitted: boolean;            // false = câu chưa nộp trong phiên bị bỏ dở
  isCorrect: boolean;
  responseMs: number;
}

/** Dòng ghi vào question_attempts. */
export interface AttemptLog {
  questionId: string;
  answeredAt: Date;
  questionVersion: number;
  isCorrect: boolean | null;
  responseMs: number;
  isValid: boolean;
  sBefore: number | null;        // độ bền trước khi làm (ngày)
  rBefore: number | null;        // khả năng ghi nhớ mô hình dự đoán trước khi làm
}

export interface SessionItem {
  questionId: string;
  position: number;
  bucket: Bucket;
  reason: string;                // hiển thị cho học viên: vì sao câu này vào phiên
  optionOrder: number[];         // hoán vị 0..optionCount-1 đã dùng để hiển thị đáp án
}

export interface SessionResult {
  items: SessionItem[];
  requested: number;
  shortfall: number;             // > 0 khi bể cạn
  message: string | null;        // thông báo cho học viên khi bể cạn
  debug: { weakPool: number; duePool: number; newCount: number; reviewSlots: number };
}

export interface BuildSessionInput {
  mode: Mode;
  size: number;
  now: Date;
  states: QuestionState[];               // mọi câu học viên đã từng làm
  questions: QuestionMeta[];             // toàn bộ ngân hàng câu hỏi
  topics: TopicWeight[];
  questionIdsInOpenSessions: Set<string>; // câu đang nằm trong phiên khác chưa xong
  rng?: () => number;                    // để test lặp lại được; mặc định Math.random
  params?: Params;
}

// ===================== Hàm tính các đại lượng =====================

const HOUR_MS = 3_600_000;
const DAY_MS = 86_400_000;

export function weakness(recentResults: boolean[]): number {
  const wrong = recentResults.filter(r => !r).length;
  return (wrong + 1) / (recentResults.length + 2);
}

export function stabilityDays(step: number, p: Params = DEFAULT_PARAMS): number {
  return p.ladderDays[step - 1];
}

export function daysSince(from: Date, now: Date): number {
  return (now.getTime() - from.getTime()) / DAY_MS;
}

export function retention(state: QuestionState, now: Date, p: Params = DEFAULT_PARAMS): number {
  const t = daysSince(state.lastAnsweredAt, now);
  return Math.pow(p.retentionBase, t / stabilityDays(state.ladderStep, p));
}

export function isDue(state: QuestionState, now: Date, p: Params = DEFAULT_PARAMS): boolean {
  return daysSince(state.lastAnsweredAt, now) >= stabilityDays(state.ladderStep, p);
}

export function isValidAttempt(a: AttemptInput, p: Params = DEFAULT_PARAMS): boolean {
  return a.submitted && a.responseMs >= p.minResponseMs;
}

// ===================== Cập nhật sau một lượt làm (mục 3) =====================

/**
 * Trả về trạng thái mới và dòng nhật ký. `prev` là undefined khi học viên chưa từng làm
 * câu này, hoặc khi trạng thái cũ thuộc phiên bản nội dung trước (câu đã được sửa nội dung).
 */
export function applyAttempt(
  prev: QuestionState | undefined,
  attempt: AttemptInput,
  meta: QuestionMeta,
  p: Params = DEFAULT_PARAMS,
): { state: QuestionState | undefined; log: AttemptLog } {
  const usable = prev && prev.questionVersion === meta.contentVersion ? prev : undefined;
  const valid = isValidAttempt(attempt, p);
  const log: AttemptLog = {
    questionId: attempt.questionId,
    answeredAt: attempt.answeredAt,
    questionVersion: meta.contentVersion,
    isCorrect: attempt.submitted ? attempt.isCorrect : null,
    responseMs: attempt.responseMs,
    isValid: valid,
    sBefore: usable ? stabilityDays(usable.ladderStep, p) : null,
    rBefore: usable ? retention(usable, attempt.answeredAt, p) : null,
  };

  if (!valid) {
    // Không tính vào độ yếu, độ bền; chỉ ghi nhận câu đã được đưa ra (cho thời gian nghỉ).
    if (!usable) return { state: undefined, log };
    return { state: { ...usable, lastSelectedAt: attempt.answeredAt }, log };
  }

  const top = p.ladderDays.length;
  let step: number;
  if (!usable) {
    step = attempt.isCorrect ? 2 : 1;
  } else if (!attempt.isCorrect) {
    step = 1;
  } else if (isDue(usable, attempt.answeredAt, p)) {
    step = Math.min(usable.ladderStep + 1, top);
  } else {
    step = usable.ladderStep; // đúng nhưng ôn sớm: giữ bậc
  }

  const recent = [...(usable?.recentResults ?? []), attempt.isCorrect].slice(-p.weakWindow);
  return {
    state: {
      questionId: attempt.questionId,
      ladderStep: step,
      lastAnsweredAt: attempt.answeredAt,
      lastSelectedAt: attempt.answeredAt,
      recentResults: recent,
      wrongCount: (usable?.wrongCount ?? 0) + (attempt.isCorrect ? 0 : 1),
      questionVersion: meta.contentVersion,
    },
    log,
  };
}

/** Hạn ôn để lưu vào cột due_at. */
export function dueAt(state: QuestionState, p: Params = DEFAULT_PARAMS): Date {
  return new Date(state.lastAnsweredAt.getTime() + stabilityDays(state.ladderStep, p) * DAY_MS);
}

// ===================== Dựng phiên (mục 4) =====================

function shuffled<T>(xs: T[], rng: () => number): T[] {
  const a = [...xs];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/** Lấy `count` câu: bốc ngẫu nhiên trong top (diversityFactor × count) của danh sách đã xếp hạng. */
function pickTop<T>(ranked: T[], count: number, rng: () => number, p: Params): T[] {
  if (count <= 0) return [];
  const window = ranked.slice(0, Math.max(count, Math.ceil(p.diversityFactor * count)));
  const chosen = new Set(shuffled(window, rng).slice(0, count));
  return ranked.filter(x => chosen.has(x)); // giữ thứ tự xếp hạng
}

function pct(x: number): string {
  return `${Math.round(x * 100)}%`;
}

/** Số câu mới — chung cho mọi mode; chỉ phụ thuộc tồn đọng Sắp quên. */
export function newQuestionCount(duePool: number, unseen: number, seen: number, size: number, p: Params): number {
  if (seen === 0) return Math.min(size, unseen);
  let share: number;
  if (duePool >= p.highBacklogFactor * size) share = p.newShareHighBacklog;
  else if (duePool >= p.midBacklogFactor * size) share = p.newShareMidBacklog;
  else share = p.newShareLowBacklog;
  const n = Math.max(0, Math.round(share * size));
  return Math.min(n, unseen);
}

export function buildSession(input: BuildSessionInput): SessionResult {
  const p = input.params ?? DEFAULT_PARAMS;
  const rng = input.rng ?? Math.random;
  const { now, size, mode } = input;

  // ---- Bước 1: Lọc ----
  const active = new Map<string, QuestionMeta>();
  for (const q of input.questions) {
    if (q.isPublished && !q.hasOpenReport && !input.questionIdsInOpenSessions.has(q.questionId)) {
      active.set(q.questionId, q);
    }
  }
  // Trạng thái chỉ còn hiệu lực khi đúng phiên bản nội dung hiện tại (quy tắc 3).
  const seen = new Map<string, QuestionState>();
  for (const s of input.states) {
    const meta = active.get(s.questionId);
    if (meta && s.questionVersion === meta.contentVersion) seen.set(s.questionId, s);
  }
  const unseen = [...active.values()].filter(q => !seen.has(q.questionId));
  const eligible = [...seen.values()].filter(
    s => (now.getTime() - s.lastSelectedAt.getTime()) / HOUR_MS >= p.cooldownHours,
  );

  // ---- Bước 2 + 3: Phân nhóm và xếp hạng ----
  const w = (s: QuestionState) => weakness(s.recentResults);
  const r = (s: QuestionState) => retention(s, now, p);
  const byId = (a: QuestionState, b: QuestionState) => a.questionId.localeCompare(b.questionId);
  const weakPool = eligible
    .filter(s => w(s) >= p.weakThreshold)
    .sort((a, b) => w(b) - w(a) || r(a) - r(b) || byId(a, b));
  const duePool = eligible
    .filter(s => isDue(s, now, p))
    .sort((a, b) => r(a) - r(b) || w(b) - w(a) || byId(a, b));

  // ---- Bước 4: Số câu mới theo tồn đọng ----
  const newCount = newQuestionCount(duePool.length, unseen.length, seen.size, size, p);
  const reviewSlots = size - newCount;

  // ---- Bước 5: Chia suất ôn tập theo loại phiên ----
  let weakQuota: number;
  if (mode === 'diem_yeu') weakQuota = reviewSlots;
  else if (mode === 'cung_co') weakQuota = 0;
  else weakQuota = Math.ceil(reviewSlots / 2);
  let dueQuota = reviewSlots - weakQuota;

  const taken = new Set<string>();
  const items: { s?: QuestionState; q?: QuestionMeta; bucket: Bucket; reason: string }[] = [];
  const takeFrom = (pool: QuestionState[], count: number, bucket: Bucket) => {
    const avail = pool.filter(s => !taken.has(s.questionId));
    for (const s of pickTop(avail, count, rng, p)) {
      taken.add(s.questionId);
      const reason = bucket === 'yeu'
        ? `Sai ${s.recentResults.filter(x => !x).length}/${s.recentResults.length} lần gần nhất (độ yếu ${pct(w(s))})`
        : `Đã ${Math.floor(daysSince(s.lastAnsweredAt, now))} ngày chưa ôn, khả năng ghi nhớ còn ${pct(r(s))}`;
      items.push({ s, bucket, reason });
    }
  };

  // Nhóm chính của loại phiên lấy trước; nhóm kia bù phần thiếu.
  if (mode === 'cung_co') {
    takeFrom(duePool, dueQuota, 'sap_quen');
    takeFrom(weakPool, reviewSlots - items.length, 'yeu');
  } else {
    takeFrom(weakPool, weakQuota, 'yeu');
    const weakShort = weakQuota - items.length;
    takeFrom(duePool, dueQuota + weakShort, 'sap_quen');
    takeFrom(weakPool, reviewSlots - items.length, 'yeu'); // nhóm Sắp quên thiếu thì quay lại nhóm Yếu
  }

  // ---- Bước 6: Câu mới — ưu tiên bài học dang dở, rồi ma trận đề thi ----
  // Đơn vị nhóm = topicId (trên production = bài học / lesson).
  // Tầng A: bài đã có ≥1 câu làm (dang dở) còn unseen → phủ nốt trước.
  // Tầng B: bài chưa mở. Trong cùng tầng: điểm = trọng số / (1 + đã gặp).
  const weightOf = new Map(input.topics.map(t => [t.topicId, t.weight]));
  const exposure = new Map<string, number>();
  for (const s of seen.values()) {
    const t = active.get(s.questionId)!.topicId;
    exposure.set(t, (exposure.get(t) ?? 0) + 1);
  }
  const unseenByTopic = new Map<string, QuestionMeta[]>();
  for (const q of shuffled(unseen, rng)) {
    if (!unseenByTopic.has(q.topicId)) unseenByTopic.set(q.topicId, []);
    unseenByTopic.get(q.topicId)!.push(q);
  }
  const newSlots = size - items.length;
  for (let i = 0; i < newSlots && unseenByTopic.size > 0; i++) {
    let best: string | null = null;
    let bestScore = -1;
    for (const topicId of [...unseenByTopic.keys()].sort()) {
      const seenInTopic = exposure.get(topicId) ?? 0;
      const inProgress = seenInTopic > 0 ? 1 : 0; // bài dang dở thắng bài chưa mở
      const score = inProgress * 1e9 + (weightOf.get(topicId) ?? 0) / (1 + seenInTopic);
      if (score > bestScore) { bestScore = score; best = topicId; }
    }
    const list = unseenByTopic.get(best!)!;
    const q = list.shift()!;
    if (list.length === 0) unseenByTopic.delete(best!);
    const wasInProgress = (exposure.get(best!) ?? 0) > 0;
    exposure.set(best!, (exposure.get(best!) ?? 0) + 1);
    items.push({
      q,
      bucket: 'moi',
      reason: wasInProgress ? 'Ca mới — tiếp tục bài đang học' : 'Ca lâm sàng mới',
    });
  }

  // ---- Bước 7: Lấp đầy, rồi báo bể cạn ----
  if (items.length < size) {
    const rest = eligible.filter(s => !taken.has(s.questionId)).sort((a, b) => r(a) - r(b) || byId(a, b));
    for (const s of rest.slice(0, size - items.length)) {
      taken.add(s.questionId);
      items.push({ s, bucket: 'lap_day', reason: `Ôn thêm, khả năng ghi nhớ còn ${pct(r(s))}` });
    }
  }
  const shortfall = size - items.length;

  // ---- Bước 8: Đảo thứ tự đáp án (quy tắc 1) ----
  const base = Array.from({ length: p.optionCount }, (_, i) => i);
  return {
    items: items.map((it, i) => ({
      questionId: (it.s?.questionId ?? it.q?.questionId)!,
      position: i + 1,
      bucket: it.bucket,
      reason: it.reason,
      optionOrder: shuffled(base, rng),
    })),
    requested: size,
    shortfall,
    message: shortfall > 0
      ? `Hôm nay bạn đã ôn hết ${items.length} câu phù hợp. Quay lại sau ${p.cooldownHours} giờ, hoặc mở rộng chủ đề để luyện thêm.`
      : null,
    debug: { weakPool: weakPool.length, duePool: duePool.length, newCount, reviewSlots },
  };
}

/** Bộ sinh số ngẫu nhiên có hạt giống (mulberry32), dùng cho test và mô phỏng. */
export function seededRng(seed: number): () => number {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

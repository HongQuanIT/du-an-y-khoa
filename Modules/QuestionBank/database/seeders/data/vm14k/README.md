# VM14K Demo Source

Nguon du lieu demo cho Question Bank dang dung `VM14K`:

- Project page: `https://venera-ai.github.io/VM14K/`
- Hugging Face dataset: `https://huggingface.co/datasets/venera-ai/VietnameseMedBench`

## File duoc dung de seed

Seeder hien tai doc 3 file JSONL sau:

- `data-processed-shuffled0.jsonl`
- `data-processed-shuffled1.jsonl`
- `data-processed-shuffled2.jsonl`

Chung duoc tai tu repo Hugging Face tai duong dan:

- `https://huggingface.co/datasets/venera-ai/VietnameseMedBench/resolve/main/tests/data-processed-shuffled0.jsonl`
- `https://huggingface.co/datasets/venera-ai/VietnameseMedBench/resolve/main/tests/data-processed-shuffled1.jsonl`
- `https://huggingface.co/datasets/venera-ai/VietnameseMedBench/resolve/main/tests/data-processed-shuffled2.jsonl`

## Cach tai lai

Tu repo root, chay:

```bash
mkdir -p Modules/QuestionBank/database/seeders/data/vm14k

base_url="https://huggingface.co/datasets/venera-ai/VietnameseMedBench/resolve/main"
for i in 0 1 2; do
  curl -k -L --fail \
    "$base_url/tests/data-processed-shuffled${i}.jsonl" \
    -o "Modules/QuestionBank/database/seeders/data/vm14k/data-processed-shuffled${i}.jsonl"
done
```

## Ghi chu ve license

Theo trang du an VM14K, dataset duoc cong bo theo `CC BY-NC 4.0` va danh cho muc dich nghien cuu / phi thuong mai.

Du lieu nay hien duoc dung de seed demo.

## Ghi chu ve seeder

- `TopicTaxonomySeeder` tao `Topic` truc tiep tu field `medical_topic` cua VM14K.
- `DemoLearningSeeder` seed cau hoi tu cac file JSONL o thu muc nay.
- So luong cau hoi demo co the gioi han bang env `QUESTIONBANK_VM14K_LIMIT`.

Chạy seeding:

  
```bash 
php artisan db:seed --class="Modules\\QuestionBank\\Database\\Seeders\\QuestionBankDatabaseSeeder"
```

